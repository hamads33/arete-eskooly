import {GatewayApi} from '../api/client';
import {ApiError, toApiError} from '../api/errors';
import SmsGateway, {type SimInfo} from '../native/SmsGateway';
import {jobRepository} from '../storage/jobRepository';
import {logRepository} from '../storage/logRepository';
import {prefs, type RuntimeInfo} from '../storage/prefs';
import {getDeviceToken} from '../storage/secureStore';
import type {BulkResultItem, ServerConfig} from '../types/api';
import type {GatewayJob, LocalSettings, PauseReason, SelectedSim} from '../types/gateway';
import {maskPhone} from '../utils/mask';
import {Mutex} from '../utils/mutex';
import {describeSmsError, toServerErrorCode} from '../utils/smsErrors';
import {isoWithOffset, sleep, startOfToday} from '../utils/time';
import {apiUrlFor} from '../utils/url';
import {gatewayEvents} from './events';
import {log} from './logger';
import {chooseSim, resolveSelectedSim} from './simService';
import {allStoredResults, clearStoredResult, sendAndAwait, type SmsOutcome} from './smsBridge';

export type CycleMode = 'tick' | 'manual' | 'maintenance' | 'startup';

const SEND_TIMEOUT_MS = 3 * 60_000;
const CYCLE_BUDGET_MS = 4 * 60_000;
const CONFIG_REFRESH_MS = 10 * 60_000;
const HOUSEKEEPING_MS = 60 * 60_000;
const LIMIT_PAUSE_MS = 15 * 60_000;
const ACK_BACKOFF_BASE_MS = 30_000;
const ACK_BACKOFF_MAX_MS = 30 * 60_000;
const DEFAULT_BATCH = 10;

/** Server-side "the device cannot send right now" code; the LMS re-queues these. */
const DEVICE_UNAVAILABLE = 'DEVICE_TEMPORARILY_UNAVAILABLE';
/** Radio-level failures after which the rest of the batch would fail the same way. */
const STOP_BATCH_ERRORS = new Set(['RADIO_OFF', 'NO_SERVICE', 'SIM_UNAVAILABLE', 'PERMISSION_DENIED', 'SMS_LIMIT_EXCEEDED']);

const mutex = new Mutex();
let lastHousekeepingAt = 0;

type Context = {
  api: GatewayApi;
  settings: LocalSettings;
  config: ServerConfig | null;
  runtime: RuntimeInfo;
};

class StopCycle extends Error {}

export function isCycleRunning(): boolean {
  return mutex.isLocked;
}

/** Entry point for the service tick, WorkManager maintenance, app start and "Sync now". */
export function runCycle(mode: CycleMode): Promise<void> {
  return mutex.run(async () => {
    try {
      await cycle(mode);
    } catch (e) {
      if (!(e instanceof StopCycle)) {
        await log.error('Cycle error', e instanceof Error ? e.message.slice(0, 200) : 'unknown');
      }
    } finally {
      gatewayEvents.emitChanged();
    }
  });
}

async function cycle(mode: CycleMode): Promise<void> {
  const pairing = await prefs.getPairing();
  const token = await getDeviceToken();
  if (!pairing || !token) {
    return;
  }
  const runtime = await prefs.getRuntime();
  if (runtime.connection === 'AUTH_ERROR') {
    // Stays stopped until the device is paired again; acknowledgments remain stored.
    return;
  }
  const ctx: Context = {
    api: new GatewayApi(apiUrlFor(pairing.baseUrl), getDeviceToken),
    settings: await prefs.getSettings(),
    config: await prefs.getServerConfig(),
    runtime,
  };

  // 1. SMS whose outcome JS missed (app killed mid-send) are settled from the native store.
  await reconcileInFlight();

  // 2. Acknowledgments first, so the LMS learns about already-sent SMS before anything new.
  await flushAcks(ctx);
  if (mode === 'maintenance') {
    await housekeeping(ctx.settings);
    return;
  }

  const running = await isGatewayRunning();
  if (running || mode === 'manual') {
    await refreshConfig(ctx, mode === 'manual');
  }
  if (running) {
    await heartbeat(ctx);
    await processQueue(ctx);
  }
  await housekeeping(ctx.settings);
  await prefs.patchRuntime({lastSyncAt: Date.now()});
}

// ---------------------------------------------------------------------------------------------
// Connection state

async function markConnected(): Promise<void> {
  const r = await prefs.getRuntime();
  if (r.connection !== 'CONNECTED') {
    await prefs.patchRuntime({connection: 'CONNECTED', connectionMessage: null});
    if (r.connection === 'SERVER_OFFLINE') {
      await log.info('Server back online');
    } else {
      await log.info('Connected to server');
    }
  }
}

async function markOffline(e: ApiError): Promise<void> {
  const r = await prefs.getRuntime();
  if (r.connection !== 'SERVER_OFFLINE') {
    await log.warn('Server offline', e.message);
  }
  await prefs.patchRuntime({connection: 'SERVER_OFFLINE', connectionMessage: e.message});
}

/** 401/403: stop fetching and sending at once. Pending acknowledgments stay in SQLite. */
async function handleAuthFailure(e: ApiError): Promise<never> {
  const disabled = e.kind === 'disabled';
  const before = (await prefs.getRuntime()).connection;
  await prefs.patchRuntime({
    connection: disabled ? 'DEVICE_DISABLED' : 'AUTH_ERROR',
    connectionMessage: disabled
      ? 'This gateway device was disabled in the LMS.'
      : 'LMS authentication was rejected. Pair this device again.',
  });
  if (before !== (disabled ? 'DEVICE_DISABLED' : 'AUTH_ERROR')) {
    await log.error(disabled ? 'Device disabled by LMS' : 'Authentication failed', e.code);
  }
  try {
    await SmsGateway.stopGateway();
  } catch {
    // Service may already be stopped.
  }
  throw new StopCycle();
}

async function onApiError(e: unknown): Promise<ApiError> {
  const err = toApiError(e);
  if (err.kind === 'auth' || err.kind === 'disabled') {
    await handleAuthFailure(err);
  }
  if (err.kind === 'network' || err.kind === 'server' || err.kind === 'invalid_response') {
    await markOffline(err);
  }
  if (err.kind === 'rate_limited') {
    await log.warn('Rate limited by LMS', 'Waiting for the next cycle');
  }
  return err;
}

async function isGatewayRunning(): Promise<boolean> {
  try {
    return (await SmsGateway.getGatewayServiceState()).desiredRunning;
  } catch {
    return false;
  }
}

// ---------------------------------------------------------------------------------------------
// Recovery of in-flight sends

async function reconcileInFlight(): Promise<void> {
  const inFlight = await jobRepository.byStatus(['SENDING']);
  if (!inFlight.length) {
    return;
  }
  const stored = await allStoredResults();
  for (const job of inFlight) {
    const r = stored.get(String(job.serverMessageId));
    if (!r) {
      // The native record is written before SmsManager is called, so no record means the SMS
      // never reached Android. Retrying is safe; the LMS rejects it if the reservation lapsed.
      await jobRepository.transition(job.serverMessageId, ['SENDING'], 'PROCESSING', {send_token: null});
      await log.warn(`Recovered #${job.serverMessageId}`, 'App stopped before the SMS was handed to Android', job.serverMessageId);
      continue;
    }
    if (r.status === 'sent') {
      await recordSent(job, r.sendToken, r.segmentCount, r.completedAt || Date.now());
      await log.info(`SMS #${job.serverMessageId} sent`, 'Confirmed by Android after restart', job.serverMessageId);
    } else if (r.status === 'failed') {
      await recordFailed(job, r.errorCode ?? 'GENERIC_FAILURE', 'radio', r.sendToken);
    } else if (Date.now() - r.startedAt > SEND_TIMEOUT_MS) {
      await recordFailed(job, 'RESULT_UNKNOWN', 'radio', r.sendToken);
    }
  }
}

// ---------------------------------------------------------------------------------------------
// Local outcome recording (always before talking to the LMS)

async function recordSent(job: GatewayJob, sendToken: string, segments: number, at: number): Promise<void> {
  await jobRepository.transition(job.serverMessageId, ['SENDING', 'PROCESSING'], 'SENT_LOCAL', {
    send_token: sendToken,
    segment_count: segments,
    sent_at: at,
    failure_code: null,
    failure_message: null,
  });
  // SQLite now holds the success, so the native backup record is no longer needed.
  clearStoredResult(String(job.serverMessageId));
}

async function recordFailed(
  job: GatewayJob,
  nativeCode: string,
  phase: 'rejected' | 'radio',
  sendToken: string | null,
): Promise<void> {
  // Rejected before Android took it: the phone, not the message, is the problem, so ask the
  // LMS to re-queue it (possibly for another gateway).
  const serverCode = phase === 'rejected' && (nativeCode === 'SIM_UNAVAILABLE' || nativeCode === 'PERMISSION_DENIED')
    ? DEVICE_UNAVAILABLE
    : toServerErrorCode(nativeCode);
  await jobRepository.transition(job.serverMessageId, ['SENDING', 'PROCESSING'], 'FAILED_LOCAL', {
    send_token: sendToken,
    failure_code: serverCode,
    failure_message: describeSmsError(nativeCode),
    completed_at: Date.now(),
  });
  if (nativeCode !== 'RESULT_UNKNOWN') {
    clearStoredResult(String(job.serverMessageId));
  }
  await log.warn(`SMS #${job.serverMessageId} failed`, `${nativeCode}: ${describeSmsError(nativeCode)}`, job.serverMessageId);
}

// ---------------------------------------------------------------------------------------------
// Acknowledgments

function backoff(retry: number): number {
  return Date.now() + Math.min(ACK_BACKOFF_BASE_MS * 2 ** Math.min(retry, 10), ACK_BACKOFF_MAX_MS);
}

function sentPayload(job: GatewayJob) {
  return {
    sent_at: isoWithOffset(job.sentAt ?? Date.now()),
    ...(job.simSlotUsed ? {sim_slot: job.simSlotUsed} : {}),
    ...(job.sendToken ? {android_message_reference: job.sendToken} : {}),
  };
}

function isSentState(job: GatewayJob) {
  return job.status === 'SENT_LOCAL' || job.status === 'ACK_PENDING';
}

async function settleAck(job: GatewayJob, ok: boolean, err: ApiError | null, serverStatus?: string): Promise<void> {
  const id = job.serverMessageId;
  const sent = isSentState(job);
  if (ok) {
    await jobRepository.transition(id, [job.status], sent ? 'COMPLETED' : 'FAILED_COMPLETE', {
      server_acknowledged: 1,
      completed_at: Date.now(),
      next_ack_at: null,
    });
    if (sent) {
      await log.info(`Server acknowledged #${id}`, null, id);
    } else {
      await log.info(
        `Failure reported for #${id}`,
        serverStatus === 'queued' ? 'LMS will retry it later' : 'LMS marked it failed',
        id,
      );
    }
    return;
  }
  if (err && (err.kind === 'not_assigned' || err.status === 404)) {
    // The LMS no longer holds this message for us (e.g. its reservation expired). Stop asking.
    await jobRepository.transition(id, [job.status], sent ? 'ACK_REJECTED' : 'FAILED_COMPLETE', {
      next_ack_at: null,
      failure_message: sent ? `LMS did not accept the sent confirmation: ${err.code}` : job.failureMessage,
    });
    await log.warn(sent ? `LMS rejected sent confirmation #${id}` : `LMS already closed #${id}`, err.code, id);
    return;
  }
  const pending = sent ? 'ACK_PENDING' : 'FAILURE_ACK_PENDING';
  await jobRepository.transition(id, [job.status], pending);
  await jobRepository.deferAck(id, job.ackRetryCount + 1, backoff(job.ackRetryCount));
  if (job.status === 'SENT_LOCAL') {
    await log.warn(`SMS #${id} sent; confirmation pending`, 'SMS was sent, but confirmation to LMS is waiting for internet connection.', id);
  }
}

/** Acknowledges one job right after sending (single endpoint, lowest latency). */
async function ackOne(ctx: Context, job: GatewayJob): Promise<void> {
  const fresh = (await jobRepository.get(job.serverMessageId)) ?? job;
  try {
    if (isSentState(fresh)) {
      await ctx.api.markSent(fresh.serverMessageId, sentPayload(fresh));
      await markConnected();
      await settleAck(fresh, true, null);
    } else {
      const res = await ctx.api.markFailed(fresh.serverMessageId, {
        error_code: fresh.failureCode ?? 'GENERIC_FAILURE',
        error_message: fresh.failureMessage ?? undefined,
      });
      await markConnected();
      await settleAck(fresh, true, null, res.status);
    }
  } catch (e) {
    const err = toApiError(e);
    await settleAck(fresh, false, err);
    await onApiError(err);
  }
}

/** Delivers every due acknowledgment in bulk (up to 100 per call). Never sends SMS. */
async function flushAcks(ctx: Context): Promise<void> {
  for (let round = 0; round < 5; round++) {
    const due = await jobRepository.dueAcks(Date.now(), 100);
    if (!due.length) {
      return;
    }
    const items: BulkResultItem[] = due.map(job =>
      isSentState(job)
        ? {id: job.serverMessageId, status: 'sent', ...sentPayload(job)}
        : {
            id: job.serverMessageId,
            status: 'failed',
            error_code: job.failureCode ?? 'GENERIC_FAILURE',
            error_message: job.failureMessage ?? undefined,
          },
    );
    let res;
    try {
      res = await ctx.api.bulkStatus(items);
      await markConnected();
    } catch (e) {
      const err = await onApiError(e);
      for (const job of due) {
        await settleAck(job, false, err);
      }
      return;
    }
    const byId = new Map(res.results.map(r => [r.id, r]));
    for (const job of due) {
      const r = byId.get(job.serverMessageId);
      if (r && r.success) {
        await settleAck(job, true, null, r.status);
      } else if (r && !r.success) {
        const err = new ApiError(
          r.error.code === 'MESSAGE_NOT_ASSIGNED' ? 'not_assigned' : 'client',
          r.error.code,
          r.error.message,
        );
        await settleAck(job, false, err);
      } else {
        await settleAck(job, false, new ApiError('invalid_response', 'MISSING_RESULT', 'No result for this id.'));
      }
    }
    if (due.length < 100) {
      return;
    }
  }
}

// ---------------------------------------------------------------------------------------------
// Heartbeat & config

async function refreshConfig(ctx: Context, force: boolean): Promise<void> {
  const stale = !ctx.runtime.lastConfigAt || Date.now() - ctx.runtime.lastConfigAt > CONFIG_REFRESH_MS;
  if (!force && ctx.config && !stale) {
    return;
  }
  try {
    const config = await ctx.api.getConfig();
    ctx.config = config;
    await prefs.setServerConfig(config);
    await prefs.patchRuntime({lastConfigAt: Date.now()});
    await markConnected();
    SmsGateway.setGatewayInterval(effectiveIntervalSeconds(ctx.settings, config));
  } catch (e) {
    await onApiError(e);
  }
}

export function effectiveIntervalSeconds(settings: LocalSettings, config: ServerConfig | null): number {
  const server = config?.poll_interval_seconds ?? 0;
  return Math.max(10, Math.min(600, Math.max(settings.syncIntervalSeconds, server)));
}

async function heartbeat(ctx: Context): Promise<void> {
  const every = (ctx.config?.heartbeat_interval_seconds ?? 60) * 1000;
  const last = ctx.runtime.lastHeartbeatAt ?? 0;
  // Slightly early so a 60 s heartbeat on a 60 s tick does not slip to every other tick.
  if (Date.now() - last < every - 5_000) {
    return;
  }
  let snapshot;
  try {
    snapshot = await SmsGateway.getDeviceSnapshot();
  } catch {
    snapshot = null;
  }
  const sim = await resolveSelectedSim();
  try {
    // The SIM is reported only when it changed, so an admin's slot choice in the LMS is not
    // overwritten by every heartbeat.
    if (sim.state === 'AVAILABLE' && sim.selected && ctx.runtime.lastSimReportedSubId !== sim.selected.subscriptionId) {
      const active = sim.sims.find(s => s.subscriptionId === sim.selected!.subscriptionId);
      await ctx.api.deviceInfo({
        ...(snapshot && snapshot.batteryLevel >= 0 ? {battery_level: snapshot.batteryLevel} : {}),
        ...(snapshot ? {network: snapshot.networkType, app_version: snapshot.appVersion} : {}),
        ...(snapshot ? {manufacturer: snapshot.manufacturer, model: snapshot.model, android_version: snapshot.androidVersion} : {}),
        sim_slot: sim.selected.slotIndex + 1,
        ...(active?.carrierName ? {sim_name: active.carrierName} : {}),
        ...(active?.phoneNumber ? {phone_number: active.phoneNumber} : {}),
      });
      await prefs.patchRuntime({lastSimReportedSubId: sim.selected.subscriptionId});
      await log.info('Device info sent', `SIM ${sim.selected.slotIndex + 1}`);
    }
    const res = await ctx.api.heartbeat({
      ...(snapshot && snapshot.batteryLevel >= 0 ? {battery_level: snapshot.batteryLevel} : {}),
      ...(snapshot ? {network: snapshot.networkType, app_version: snapshot.appVersion} : {}),
    });
    const now = Date.now();
    ctx.runtime = await prefs.patchRuntime({
      lastHeartbeatAt: now,
      lastHeartbeatOk: true,
      queuedOnServer: Number(res.queued_messages ?? 0),
    });
    await markConnected();
    await log.debug('Heartbeat successful', `Queued on LMS: ${res.queued_messages}`);
  } catch (e) {
    await prefs.patchRuntime({lastHeartbeatOk: false});
    await onApiError(e);
  }
}

// ---------------------------------------------------------------------------------------------
// Queue

async function setPause(reason: PauseReason, until: number | null = null): Promise<void> {
  const r = await prefs.getRuntime();
  if (r.pauseReason !== reason) {
    if (reason) {
      await log.warn('Sending paused', pauseText(reason));
    } else if (r.pauseReason) {
      await log.info('Sending resumed');
    }
  }
  await prefs.patchRuntime({pauseReason: reason, pausedUntil: until});
}

export function pauseText(reason: PauseReason | string | null): string {
  switch (reason) {
    case 'SIM_UNAVAILABLE':
      return 'The selected SIM is no longer available.';
    case 'PERMISSION_REQUIRED':
      return 'SMS permission is required.';
    case 'SMS_LIMIT_EXCEEDED':
      return 'Android SMS limit reached. Sending resumes automatically in a few minutes.';
    case 'SERVER_DISABLED':
      return 'SMS sending is switched off in the LMS.';
    case 'DAILY_LIMIT':
      return 'Local daily SMS limit reached.';
    default:
      return '';
  }
}

async function preflight(ctx: Context): Promise<{sims: SimInfo[]; selected: SelectedSim} | null> {
  const r = await prefs.getRuntime();
  if (r.pauseReason === 'SMS_LIMIT_EXCEEDED' && r.pausedUntil && r.pausedUntil > Date.now()) {
    return null;
  }
  if (ctx.config && (!ctx.config.sms_enabled || !ctx.config.queue_processing_enabled)) {
    await setPause('SERVER_DISABLED');
    return null;
  }
  const caps = await SmsGateway.getSmsCapabilityStatus();
  if (!caps.sendSmsPermission) {
    await setPause('PERMISSION_REQUIRED');
    return null;
  }
  const sim = await resolveSelectedSim();
  if (sim.state === 'PERMISSION_REQUIRED') {
    await setPause('PERMISSION_REQUIRED');
    return null;
  }
  if (sim.state !== 'AVAILABLE' || !sim.selected) {
    await setPause('SIM_UNAVAILABLE');
    return null;
  }
  if (ctx.settings.dailyLimit > 0 && (await jobRepository.sentSince(startOfToday())) >= ctx.settings.dailyLimit) {
    await setPause('DAILY_LIMIT');
    return null;
  }
  await setPause(null);
  return {sims: sim.sims, selected: sim.selected};
}

async function processQueue(ctx: Context): Promise<void> {
  const started = Date.now();
  const batch = Math.max(1, Math.min(50, ctx.config?.batch_size ?? DEFAULT_BATCH));
  const delayMs = Math.max(1, Math.min(30, ctx.settings.smsDelaySeconds)) * 1000;

  while (Date.now() - started < CYCLE_BUDGET_MS) {
    const ready = await preflight(ctx);
    if (!ready) {
      return;
    }
    // Messages already reserved for us are never returned by /messages again, so the local
    // backlog is worked off before asking for more.
    let backlog = await jobRepository.byStatus(['RECEIVED', 'PROCESSING'], batch);
    const fromBacklog = backlog.length > 0;
    let fetched = 0;
    if (!fromBacklog) {
      try {
        const messages = await ctx.api.fetchMessages(batch);
        await markConnected();
        fetched = messages.length;
        if (fetched) {
          await log.info(`Fetched ${fetched} job${fetched === 1 ? '' : 's'}`);
        }
        for (const m of messages) {
          const {job, existing} = await jobRepository.receive(m);
          if (!job && existing) {
            await log.warn(`Duplicate job #${m.id} ignored`, `Already ${existing.status}; not sending again`, m.id);
          }
        }
      } catch (e) {
        await onApiError(e);
        return;
      }
      backlog = await jobRepository.byStatus(['RECEIVED', 'PROCESSING'], batch);
    }
    if (!backlog.length) {
      return;
    }
    for (const job of backlog) {
      if (!(await isGatewayRunning())) {
        await log.info('Gateway stopped; remaining jobs kept for later');
        return;
      }
      const verdict = await processJob(ctx, job, ready.sims, ready.selected);
      if (verdict === 'stop') {
        return;
      }
      await sleep(delayMs);
    }
    if (!fromBacklog && fetched < batch) {
      return;
    }
  }
}

async function processJob(
  ctx: Context,
  job: GatewayJob,
  sims: SimInfo[],
  selected: SelectedSim,
): Promise<'next' | 'stop'> {
  const id = job.serverMessageId;
  const sim = chooseSim(job.simSlot, sims, selected);
  if (!sim) {
    await setPause('SIM_UNAVAILABLE');
    return 'stop';
  }

  const moved = await jobRepository.transition(id, ['RECEIVED', 'PROCESSING'], 'PROCESSING', {
    processing_at: Date.now(),
    subscription_id: sim.subscriptionId,
    sim_slot_used: sim.slotIndex >= 0 ? sim.slotIndex + 1 : null,
  });
  if (!moved) {
    return 'next';
  }
  await log.info(`Processing #${id}`, `To ${maskPhone(job.phoneNumber)} via SIM ${sim.slotIndex + 1}`, id);

  try {
    await ctx.api.markProcessing(id);
  } catch (e) {
    const err = toApiError(e);
    if (err.kind === 'not_assigned' || err.status === 404) {
      await jobRepository.transition(id, ['PROCESSING'], 'CANCELLED', {failure_code: err.code, completed_at: Date.now()});
      await log.warn(`Job #${id} dropped`, 'No longer assigned to this device by the LMS; not sent.', id);
      return 'next';
    }
    // Not confirmed as processing: do not send; it stays queued locally for the next cycle.
    await jobRepository.transition(id, ['PROCESSING'], 'RECEIVED');
    await onApiError(err);
    return 'stop';
  }

  // Persist SENDING *before* the SMS is handed to Android: from here on a crash can never
  // lead to a second send (see reconcileInFlight).
  const locked = await jobRepository.transition(id, ['PROCESSING'], 'SENDING');
  if (!locked) {
    return 'next';
  }
  const outcome: SmsOutcome = await sendAndAwait(
    {
      jobId: String(id),
      phoneNumber: job.phoneNumber,
      message: job.message,
      subscriptionId: sim.subscriptionId,
      requestDeliveryReport: ctx.settings.requestDeliveryReports,
    },
    SEND_TIMEOUT_MS,
  );

  const current = (await jobRepository.get(id)) ?? job;
  if (outcome.status === 'sent') {
    await recordSent(current, outcome.sendToken, outcome.segmentCount, outcome.completedAt);
    const parts = outcome.segmentCount > 1 ? ` (${outcome.segmentCount} parts)` : '';
    await log.info(`SMS #${id} sent`, `To ${maskPhone(job.phoneNumber)}${parts}`, id);
    gatewayEvents.emitChanged();
    await ackOne(ctx, (await jobRepository.get(id)) ?? current);
    return 'next';
  }

  const nativeCode = outcome.status === 'unknown' ? 'RESULT_UNKNOWN' : outcome.errorCode;
  const phase = outcome.status === 'failed' ? outcome.phase : 'radio';
  await recordFailed(current, nativeCode, phase, outcome.sendToken);
  gatewayEvents.emitChanged();
  await ackOne(ctx, (await jobRepository.get(id)) ?? current);

  if (nativeCode === 'SMS_LIMIT_EXCEEDED') {
    await setPause('SMS_LIMIT_EXCEEDED', Date.now() + LIMIT_PAUSE_MS);
  } else if (nativeCode === 'SIM_UNAVAILABLE') {
    await setPause('SIM_UNAVAILABLE');
  } else if (nativeCode === 'PERMISSION_DENIED') {
    await setPause('PERMISSION_REQUIRED');
  }
  return STOP_BATCH_ERRORS.has(nativeCode) ? 'stop' : 'next';
}

// ---------------------------------------------------------------------------------------------
// Housekeeping

async function housekeeping(settings: LocalSettings): Promise<void> {
  if (Date.now() - lastHousekeepingAt < HOUSEKEEPING_MS) {
    return;
  }
  lastHousekeepingAt = Date.now();
  const cutoff = Date.now() - Math.max(1, settings.logRetentionDays) * 24 * 60 * 60 * 1000;
  await logRepository.purge(cutoff);
  await jobRepository.purgeFinished(cutoff);
}
