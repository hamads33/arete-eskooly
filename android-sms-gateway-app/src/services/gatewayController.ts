import {registerDevice, testConnection, GatewayApi} from '../api/client';
import {ApiError, toApiError} from '../api/errors';
import SmsGateway from '../native/SmsGateway';
import {getDb} from '../storage/database';
import {jobRepository} from '../storage/jobRepository';
import {prefs, DEFAULT_RUNTIME} from '../storage/prefs';
import {clearDeviceToken, getDeviceToken, getOrCreateDeviceUuid, setDeviceToken} from '../storage/secureStore';
import type {LocalSettings} from '../types/gateway';
import {apiUrlFor, normalizeServerUrl} from '../utils/url';
import {gatewayEvents} from './events';
import {effectiveIntervalSeconds, runCycle} from './gatewayEngine';
import {log} from './logger';
import {hasRequiredPermissions} from './permissions';
import {onDelivery} from './smsBridge';
import {resolveSelectedSim} from './simService';

let deliveryHooked = false;

function hookDeliveryReports() {
  if (deliveryHooked) {
    return;
  }
  deliveryHooked = true;
  onDelivery(async e => {
    const id = Number(e.jobId);
    if (!Number.isInteger(id)) {
      return;
    }
    if (e.status === 'delivered') {
      await jobRepository.setDelivered(id, e.timestamp);
      await log.info(`SMS #${id} delivered`, 'Carrier delivery report', id);
    } else {
      await log.warn(`SMS #${id} not delivered`, 'Carrier reported a delivery failure', id);
    }
    gatewayEvents.emitChanged();
  });
}

export type LaunchRoute = 'Pair' | 'Permissions' | 'SimSelection' | 'Dashboard';

/** App start: restore everything, then settle pending acknowledgments before any new work. */
export async function bootstrap(): Promise<LaunchRoute> {
  await getDb();
  hookDeliveryReports();
  const [pairing, token] = await Promise.all([prefs.getPairing(), getDeviceToken()]);
  if (!pairing || !token) {
    return 'Pair';
  }
  SmsGateway.scheduleMaintenance();
  if (!(await hasRequiredPermissions())) {
    return 'Permissions';
  }
  const sim = await resolveSelectedSim();
  if (sim.state === 'NOT_SELECTED') {
    return 'SimSelection';
  }
  // The service may have been killed without a restart (e.g. force-stop); bring it back if the
  // admin left it on.
  const svc = await SmsGateway.getGatewayServiceState();
  const runtime = await prefs.getRuntime();
  if (svc.desiredRunning && !svc.running && runtime.connection !== 'AUTH_ERROR') {
    await SmsGateway.startGateway(svc.intervalSeconds);
    await log.info('Gateway resumed on app start');
  } else {
    runCycle('startup');
  }
  return 'Dashboard';
}

/** Headless entry points (registered in index.js). */
export async function headlessTick(): Promise<void> {
  await getDb();
  hookDeliveryReports();
  await runCycle('tick');
}

export async function headlessMaintenance(): Promise<void> {
  await getDb();
  await runCycle('maintenance');
}

// ---------------------------------------------------------------------------------------------
// Pairing

export async function checkServer(url: string): Promise<{apiUrl: string; baseUrl: string}> {
  const n = normalizeServerUrl(url, __DEV__);
  if (!n.ok) {
    throw new ApiError('client', 'INVALID_URL', n.error);
  }
  await testConnection(n.apiUrl);
  return {apiUrl: n.apiUrl, baseUrl: n.baseUrl};
}

async function completePairing(baseUrl: string, token: string, deviceId: number | null, deviceName: string | null) {
  await setDeviceToken(token);
  await prefs.setPairing({baseUrl, deviceName, deviceId, pairedAt: Date.now()});
  await prefs.patchRuntime({
    ...DEFAULT_RUNTIME,
    connection: 'CONNECTED',
  });
  SmsGateway.scheduleMaintenance();
  gatewayEvents.emitChanged();
}

export async function pairWithCode(url: string, pairingCode: string, localName: string): Promise<void> {
  const {apiUrl, baseUrl} = await checkServer(url);
  const code = pairingCode.trim().toUpperCase();
  if (code.replace(/[^A-Z0-9]/g, '').length !== 12) {
    throw new ApiError('client', 'INVALID_PAIRING_CODE', 'The pairing code has 12 letters/digits, like ABCD-EFGH-JKLM.');
  }
  const uuid = await getOrCreateDeviceUuid();
  const snap = await SmsGateway.getDeviceSnapshot().catch(() => null);
  const sim = await resolveSelectedSim();
  const active = sim.selected ? sim.sims.find(s => s.subscriptionId === sim.selected!.subscriptionId) : undefined;
  const res = await registerDevice(apiUrl, {
    pairing_code: code,
    device_uuid: uuid,
    ...(snap
      ? {manufacturer: snap.manufacturer, model: snap.model, android_version: snap.androidVersion, app_version: snap.appVersion}
      : {}),
    ...(active ? {sim_slot: active.slotIndex + 1} : {}),
    ...(active?.carrierName ? {sim_name: active.carrierName} : {}),
    ...(active?.phoneNumber ? {phone_number: active.phoneNumber} : {}),
  });
  await completePairing(baseUrl, res.token, res.device?.id ?? null, res.device?.device_name || localName || null);
  if (res.config) {
    await prefs.setServerConfig(res.config);
    await prefs.patchRuntime({lastConfigAt: Date.now(), lastSimReportedSubId: active ? active.subscriptionId : null});
  }
  await log.info('Device paired', `Server ${baseUrl}`);
}

/** For a token an admin generated with "Generate Token" in the LMS. */
export async function pairWithToken(url: string, token: string, localName: string): Promise<void> {
  const {apiUrl, baseUrl} = await checkServer(url);
  const t = token.trim();
  if (!/^smsgw_[A-Za-z0-9]{16,}$/.test(t)) {
    throw new ApiError('client', 'INVALID_TOKEN', 'A device token starts with "smsgw_".');
  }
  const result = await testConnection(apiUrl, t);
  if (!result.authenticated) {
    throw new ApiError('auth', 'UNAUTHENTICATED', 'LMS authentication was rejected.');
  }
  const api = new GatewayApi(apiUrl, async () => t);
  const snap = await SmsGateway.getDeviceSnapshot().catch(() => null);
  const info = await api.deviceInfo(
    snap ? {manufacturer: snap.manufacturer, model: snap.model, android_version: snap.androidVersion, app_version: snap.appVersion} : {},
  );
  await completePairing(baseUrl, t, info.device?.id ?? null, info.device?.device_name || localName || null);
  await log.info('Device paired with token', `Server ${baseUrl}`);
}

export async function unpair(): Promise<void> {
  try {
    await SmsGateway.stopGateway();
  } catch {
    // not running
  }
  SmsGateway.cancelMaintenance();
  await clearDeviceToken();
  await prefs.clearPairing();
  await prefs.patchRuntime({...DEFAULT_RUNTIME});
  await log.warn('Device unpaired');
  gatewayEvents.emitChanged();
}

// ---------------------------------------------------------------------------------------------
// Start / stop

export type StartCheck = {ok: true} | {ok: false; message: string};

export async function canStart(): Promise<StartCheck> {
  const [pairing, token, runtime] = await Promise.all([prefs.getPairing(), getDeviceToken(), prefs.getRuntime()]);
  if (!pairing || !token) {
    return {ok: false, message: 'Pair this phone with the LMS first.'};
  }
  if (runtime.connection === 'AUTH_ERROR') {
    return {ok: false, message: 'LMS authentication was rejected. Pair this device again.'};
  }
  if (!(await hasRequiredPermissions())) {
    return {ok: false, message: 'SMS permission is required.'};
  }
  const sim = await resolveSelectedSim();
  if (sim.sims.length === 0) {
    return {ok: false, message: 'No active SIM detected.'};
  }
  if (sim.state !== 'AVAILABLE') {
    return {ok: false, message: 'The selected SIM is no longer available.'};
  }
  return {ok: true};
}

export async function startGateway(): Promise<StartCheck> {
  const check = await canStart();
  if (!check.ok) {
    return check;
  }
  const [settings, config] = await Promise.all([prefs.getSettings(), prefs.getServerConfig()]);
  if ((await prefs.getRuntime()).connection === 'DEVICE_DISABLED') {
    await prefs.patchRuntime({connection: 'CONNECTING', connectionMessage: null});
  }
  SmsGateway.setStartOnBoot(settings.startOnBoot);
  const started = await SmsGateway.startGateway(effectiveIntervalSeconds(settings, config));
  if (!started) {
    return {ok: false, message: 'Android did not allow the gateway service to start.'};
  }
  await log.info('Gateway started');
  gatewayEvents.emitChanged();
  return {ok: true};
}

export async function stopGateway(): Promise<void> {
  await SmsGateway.stopGateway();
  await log.info('Gateway stopped', 'Pending confirmations are kept and retried in the background');
  gatewayEvents.emitChanged();
}

export async function syncNow(): Promise<void> {
  await runCycle('manual');
}

export async function saveSettings(next: LocalSettings): Promise<void> {
  await prefs.setSettings(next);
  SmsGateway.setStartOnBoot(next.startOnBoot);
  SmsGateway.setGatewayInterval(effectiveIntervalSeconds(next, await prefs.getServerConfig()));
  gatewayEvents.emitChanged();
}

export function describeApiError(e: unknown): string {
  const err = toApiError(e);
  switch (err.code) {
    case 'INVALID_PAIRING_CODE':
      return 'The pairing code is invalid, expired (30 min) or already used. Create a new one in the LMS.';
    case 'DEVICE_ALREADY_REGISTERED':
      return 'This phone is already paired as another gateway device. Remove that registration in the LMS first.';
    case 'UNAUTHENTICATED':
      return 'LMS authentication was rejected.';
    case 'DEVICE_DISABLED':
      return 'This gateway device is disabled in the LMS.';
    case 'RATE_LIMITED':
      return 'Too many attempts. Wait a minute and try again.';
  }
  return err.message;
}

export async function pendingAckCount(): Promise<number> {
  return (await jobRepository.counters()).pendingAcks;
}

export {apiUrlFor};
