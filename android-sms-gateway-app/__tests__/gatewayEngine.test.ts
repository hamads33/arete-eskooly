/**
 * The job state machine and duplicate-prevention rules, exercised end to end: real engine,
 * real repositories, real SQL (node:sqlite). Only the Kotlin module and HTTP are faked.
 */
jest.mock('@op-engineering/op-sqlite', () => require('./helpers/sqliteAdapter'));
jest.mock('@react-native-async-storage/async-storage', () => require('./helpers/asyncStorage'));
jest.mock('react-native-keychain', () => {
  const store = new Map<string, string>();
  return {
    STORAGE_TYPE: {AES_GCM_NO_AUTH: 'KeystoreAESGCM_NoAuth'},
    ACCESSIBLE: {AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY: 'x'},
    getGenericPassword: async ({service}: {service: string}) =>
      store.has(service) ? {username: 'u', password: store.get(service)} : false,
    setGenericPassword: async (_u: string, p: string, {service}: {service: string}) => {
      store.set(service, p);
      return true;
    },
    resetGenericPassword: async ({service}: {service: string}) => store.delete(service),
  };
});
jest.mock('../src/native/NativeSmsGateway', () => require('./helpers/fakeNative'));
jest.mock('../src/api/client', () => {
  const api = {
    heartbeat: jest.fn(),
    deviceInfo: jest.fn(),
    getConfig: jest.fn(),
    fetchMessages: jest.fn(),
    markProcessing: jest.fn(),
    markSent: jest.fn(),
    markFailed: jest.fn(),
    bulkStatus: jest.fn(),
  };
  return {GatewayApi: jest.fn(() => api), __api: api};
});

jest.setTimeout(30_000);

type Api = Record<
  'heartbeat' | 'deviceInfo' | 'getConfig' | 'fetchMessages' | 'markProcessing' | 'markSent' | 'markFailed' | 'bulkStatus',
  jest.Mock
>;

const MSG = (id: number) => ({
  id,
  phone_number: '+923001234567',
  message: `Ali was absent today. #${id}`,
  priority: 'normal',
  sim_slot: 1,
});

const CONFIG = {
  api_version: '1',
  sms_enabled: true,
  queue_processing_enabled: true,
  batch_size: 10,
  heartbeat_interval_seconds: 60,
  poll_interval_seconds: 60,
  reservation_timeout_minutes: 10,
  default_sim_slot: null,
  quiet_hours: null,
  retryable_error_codes: [],
  server_time: '',
};

let api: Api;
let native: any;
let engine: typeof import('../src/services/gatewayEngine');
let jobs: typeof import('../src/storage/jobRepository').jobRepository;
let prefs: typeof import('../src/storage/prefs').prefs;
let ApiError: typeof import('../src/api/errors').ApiError;

async function status(id: number) {
  return (await jobs.get(id))?.status;
}

beforeEach(async () => {
  jest.resetModules();
  jest.restoreAllMocks();
  api = require('../src/api/client').__api;
  Object.values(api).forEach(fn => fn.mockReset());
  native = require('./helpers/fakeNative').default;
  native.__reset();
  engine = require('../src/services/gatewayEngine');
  jobs = require('../src/storage/jobRepository').jobRepository;
  prefs = require('../src/storage/prefs').prefs;
  ApiError = require('../src/api/errors').ApiError;
  const secure = require('../src/storage/secureStore');

  await require('@react-native-async-storage/async-storage').default.clear?.();
  await secure.setDeviceToken('smsgw_test_token');
  await prefs.setPairing({baseUrl: 'https://school.example.com', deviceName: 'Office', deviceId: 3, pairedAt: 1});
  await prefs.setSettings({...(await prefs.getSettings()), smsDelaySeconds: 1});
  await prefs.setSelectedSim({subscriptionId: 1, slotIndex: 0, carrierName: 'Jazz', displayName: 'SIM 1'});
  await prefs.setServerConfig(CONFIG);
  await prefs.patchRuntime({connection: 'CONNECTED', lastConfigAt: Date.now(), lastSimReportedSubId: 1});

  api.heartbeat.mockResolvedValue({success: true, server_time: '', queued_messages: 1, held_by_device: 0, next_heartbeat_seconds: 60});
  api.deviceInfo.mockResolvedValue({success: true, device: {}});
  api.getConfig.mockResolvedValue(CONFIG);
  api.fetchMessages.mockResolvedValue([]);
  api.markProcessing.mockResolvedValue({success: true});
  api.markSent.mockResolvedValue({success: true});
  api.markFailed.mockResolvedValue({success: true, status: 'queued', will_retry: true});
  api.bulkStatus.mockImplementation(async (items: Array<{id: number}>) => ({
    success: true,
    results: items.map(i => ({id: i.id, success: true, status: 'sent'})),
  }));
});

test('queued message is sent once, only after Android confirms, then acknowledged', async () => {
  api.fetchMessages.mockResolvedValueOnce([MSG(152)]);
  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(1);
  expect(native.__state.sent[0]).toMatchObject({jobId: '152', phoneNumber: '+923001234567', subscriptionId: 1});
  expect(api.markProcessing).toHaveBeenCalledWith(152);
  expect(api.markSent).toHaveBeenCalledWith(152, expect.objectContaining({sim_slot: 1}));
  expect(await status(152)).toBe('COMPLETED');
});

test('internet drops after the SMS went out: ACK_PENDING, then only the acknowledgment is retried', async () => {
  api.fetchMessages.mockResolvedValueOnce([MSG(152)]);
  api.markSent.mockRejectedValueOnce(new ApiError('network', 'NETWORK', 'offline'));
  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(1);
  expect(await status(152)).toBe('ACK_PENDING');
  expect((await prefs.getRuntime()).connection).toBe('SERVER_OFFLINE');

  // Later: the server is back and (wrongly) hands out #152 again.
  const later = Date.now() + 5 * 60_000;
  jest.spyOn(Date, 'now').mockReturnValue(later);
  api.fetchMessages.mockResolvedValueOnce([MSG(152)]);
  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(1); // never physically resent
  expect(api.bulkStatus).toHaveBeenCalledWith([expect.objectContaining({id: 152, status: 'sent'})]);
  expect(await status(152)).toBe('COMPLETED');
});

test('duplicate server job for an already completed message is ignored', async () => {
  api.fetchMessages.mockResolvedValueOnce([MSG(7)]);
  await engine.runCycle('tick');
  api.fetchMessages.mockResolvedValueOnce([MSG(7)]);
  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(1);
  expect(api.markProcessing).toHaveBeenCalledTimes(1);
  expect(await status(7)).toBe('COMPLETED');
});

test('restart with a job stuck in SENDING that Android did send: acknowledged, not resent', async () => {
  await jobs.receive(MSG(200));
  await jobs.transition(200, ['RECEIVED'], 'PROCESSING');
  await jobs.transition(200, ['PROCESSING'], 'SENDING');
  native.__state.stored.set('200', {
    jobId: '200',
    sendToken: 'old-token',
    status: 'sent',
    errorCode: null,
    resultCode: -1,
    segmentCount: 2,
    segmentsSent: 2,
    startedAt: Date.now() - 1000,
    completedAt: Date.now() - 500,
    deliveredAt: 0,
  });

  await engine.runCycle('startup');

  expect(native.__state.sent).toHaveLength(0);
  expect(api.bulkStatus).toHaveBeenCalledWith([
    expect.objectContaining({id: 200, status: 'sent', android_message_reference: 'old-token'}),
  ]);
  expect(await status(200)).toBe('COMPLETED');
  expect(native.__state.stored.has('200')).toBe(false);
});

test('restart with SENDING but no native record (never reached Android): retried only if the LMS still assigns it', async () => {
  await jobs.receive(MSG(300));
  await jobs.transition(300, ['RECEIVED'], 'PROCESSING');
  await jobs.transition(300, ['PROCESSING'], 'SENDING');
  api.markProcessing.mockRejectedValueOnce(new ApiError('not_assigned', 'MESSAGE_NOT_ASSIGNED', 'gone', 409));

  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(0);
  expect(await status(300)).toBe('CANCELLED');
});

test('radio failure is reported with the LMS code and stops the batch', async () => {
  native.__state.outcomes.push({status: 'failed', errorCode: 'NO_SERVICE'});
  api.fetchMessages.mockResolvedValueOnce([MSG(152), MSG(153)]);

  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(1);
  expect(api.markFailed).toHaveBeenCalledWith(152, expect.objectContaining({error_code: 'NO_SIGNAL'}));
  expect(await status(152)).toBe('FAILED_COMPLETE');
  expect(await status(153)).toBe('RECEIVED');
});

test('failure acknowledgment survives an outage as FAILURE_ACK_PENDING', async () => {
  native.__state.outcomes.push({status: 'failed', errorCode: 'GENERIC_FAILURE'});
  api.fetchMessages.mockResolvedValueOnce([MSG(410)]);
  api.markFailed.mockRejectedValueOnce(new ApiError('server', 'SERVER_ERROR', 'down', 500));

  await engine.runCycle('tick');
  expect(await status(410)).toBe('FAILURE_ACK_PENDING');
});

test('a retryable failure the LMS re-queues is sent again as a new attempt', async () => {
  native.__state.outcomes.push({status: 'failed', errorCode: 'GENERIC_FAILURE'});
  api.fetchMessages.mockResolvedValueOnce([MSG(500)]);
  await engine.runCycle('tick');
  expect(await status(500)).toBe('FAILED_COMPLETE');

  api.fetchMessages.mockResolvedValueOnce([MSG(500)]);
  await engine.runCycle('tick');
  expect(native.__state.sent).toHaveLength(2);
  const job = await jobs.get(500);
  expect(job?.status).toBe('COMPLETED');
  expect(job?.attempt).toBe(2);
});

test('SIM rejected before Android accepted it: re-queued on the LMS as device-unavailable', async () => {
  native.__state.outcomes.push({reject: 'SIM_UNAVAILABLE'});
  api.fetchMessages.mockResolvedValueOnce([MSG(610)]);

  await engine.runCycle('tick');

  expect(native.__state.sent).toHaveLength(0);
  expect(api.markFailed).toHaveBeenCalledWith(610, expect.objectContaining({error_code: 'DEVICE_TEMPORARILY_UNAVAILABLE'}));
  expect((await prefs.getRuntime()).pauseReason).toBe('SIM_UNAVAILABLE');
});

test('revoked token (401) stops the gateway and fetches nothing', async () => {
  api.heartbeat.mockRejectedValueOnce(new ApiError('auth', 'UNAUTHENTICATED', 'no', 401));
  await engine.runCycle('tick');

  expect((await prefs.getRuntime()).connection).toBe('AUTH_ERROR');
  expect(native.__state.stopCalls).toBe(1);
  expect(api.fetchMessages).not.toHaveBeenCalled();

  await engine.runCycle('tick');
  expect(api.heartbeat).toHaveBeenCalledTimes(1);
});

test('selected SIM removed: sending pauses and nothing is fetched', async () => {
  native.__state.sims = [{subscriptionId: 9, slotIndex: 1, carrierName: 'Zong', displayName: 'SIM 2', phoneNumber: null, countryIso: 'pk', isEmbedded: false}];
  await engine.runCycle('tick');

  expect(api.fetchMessages).not.toHaveBeenCalled();
  expect((await prefs.getRuntime()).pauseReason).toBe('SIM_UNAVAILABLE');
});

test('stopped gateway still flushes acknowledgments but never fetches or sends', async () => {
  await jobs.receive(MSG(800));
  await jobs.transition(800, ['RECEIVED'], 'PROCESSING');
  await jobs.transition(800, ['PROCESSING'], 'SENDING');
  await jobs.transition(800, ['SENDING'], 'ACK_PENDING', {sent_at: Date.now()});
  native.__state.desiredRunning = false;

  await engine.runCycle('maintenance');

  expect(api.bulkStatus).toHaveBeenCalledTimes(1);
  expect(api.fetchMessages).not.toHaveBeenCalled();
  expect(api.heartbeat).not.toHaveBeenCalled();
  expect(await status(800)).toBe('COMPLETED');
});

test('sent confirmation the LMS refuses (reservation expired) is not retried forever', async () => {
  api.fetchMessages.mockResolvedValueOnce([MSG(900)]);
  api.markSent.mockRejectedValueOnce(new ApiError('not_assigned', 'MESSAGE_NOT_ASSIGNED', 'gone', 409));

  await engine.runCycle('tick');
  expect(await status(900)).toBe('ACK_REJECTED');
  expect(native.__state.sent).toHaveLength(1);
});
