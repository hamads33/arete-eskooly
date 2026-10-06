/**
 * Programmable stand-in for the Kotlin TurboModule. `outcomes` decides what Android "reports"
 * for each send; `sent` records every SMS that would physically have left the phone.
 */
const state = {
  sent: [],
  stored: new Map(),
  outcomes: [],
  listeners: [],
  desiredRunning: true,
  sims: [{subscriptionId: 1, slotIndex: 0, carrierName: 'Jazz', displayName: 'SIM 1', phoneNumber: null, countryIso: 'pk', isEmbedded: false}],
  sendSmsPermission: true,
  stopCalls: 0,
};

function emit(e) {
  state.listeners.forEach(l => l(e));
}

const module_ = {
  __state: state,
  __reset() {
    state.sent = [];
    state.stored = new Map();
    state.outcomes = [];
    state.desiredRunning = true;
    state.sendSmsPermission = true;
    state.stopCalls = 0;
    state.sims = [{subscriptionId: 1, slotIndex: 0, carrierName: 'Jazz', displayName: 'SIM 1', phoneNumber: null, countryIso: 'pk', isEmbedded: false}];
  },
  onSmsEvent(listener) {
    state.listeners.push(listener);
    return {remove() {}};
  },
  onGatewayServiceEvent() {
    return {remove() {}};
  },
  async getAvailableSims() {
    return state.sims;
  },
  async getSmsCapabilityStatus() {
    return {
      hasTelephony: true,
      sendSmsPermission: state.sendSmsPermission,
      phoneStatePermission: true,
      notificationPermission: true,
      activeSimCount: state.sims.length,
      defaultSmsSubscriptionId: 1,
      airplaneMode: false,
    };
  },
  async sendSms(req) {
    const outcome = state.outcomes.shift() ?? {status: 'sent'};
    if (outcome.reject) {
      const err = new Error(outcome.reject);
      err.code = outcome.reject;
      throw err;
    }
    const sendToken = `tok-${req.jobId}-${state.sent.length}`;
    state.sent.push(req);
    const record = {
      jobId: req.jobId,
      sendToken,
      status: outcome.status,
      errorCode: outcome.errorCode ?? null,
      resultCode: 0,
      segmentCount: 1,
      segmentsSent: outcome.status === 'sent' ? 1 : 0,
      startedAt: Date.now(),
      completedAt: Date.now(),
      deliveredAt: 0,
    };
    state.stored.set(req.jobId, record);
    setTimeout(() => emit({...record, segmentIndex: -1, timestamp: Date.now()}), 0);
    return {jobId: req.jobId, sendToken, segmentCount: 1};
  },
  async getStoredResults() {
    return [...state.stored.values()];
  },
  clearStoredResult(jobId) {
    state.stored.delete(jobId);
  },
  async getDeviceSnapshot() {
    return {
      batteryLevel: 80,
      charging: true,
      networkType: 'wifi',
      androidVersion: '14',
      sdkInt: 34,
      manufacturer: 'Test',
      model: 'Phone',
      appVersion: '1.0.0',
      appBuild: 1,
      debugBuild: true,
    };
  },
  generateUuid() {
    return 'uuid-test';
  },
  async startGateway() {
    state.desiredRunning = true;
    return true;
  },
  async stopGateway() {
    state.desiredRunning = false;
    state.stopCalls++;
    return true;
  },
  async getGatewayServiceState() {
    return {running: state.desiredRunning, desiredRunning: state.desiredRunning, startOnBoot: false, intervalSeconds: 30, lastTickAt: 0, reason: ''};
  },
  updateGatewayNotification() {},
  setGatewayInterval() {},
  setStartOnBoot() {},
  scheduleMaintenance() {},
  cancelMaintenance() {},
  async isIgnoringBatteryOptimizations() {
    return true;
  },
  requestIgnoreBatteryOptimizations() {},
  openAppSettings() {},
};

module.exports = {__esModule: true, default: module_};
