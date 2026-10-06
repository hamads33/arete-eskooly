import {create} from 'zustand';
import SmsGateway, {type GatewayServiceState, type SimInfo} from '../native/SmsGateway';
import {jobRepository} from '../storage/jobRepository';
import {prefs, DEFAULT_RUNTIME, type RuntimeInfo} from '../storage/prefs';
import {getDeviceToken} from '../storage/secureStore';
import type {ServerConfig} from '../types/api';
import {
  DEFAULT_SETTINGS,
  type GatewayConnectionState,
  type GatewayCounters,
  type GatewayRunningState,
  type LocalSettings,
  type Pairing,
  type SelectedSim,
  type SimState,
} from '../types/gateway';
import {gatewayEvents} from '../services/events';
import {permissionSummary, type PermissionKey} from '../services/permissions';
import {resolveSelectedSim} from '../services/simService';

/**
 * UI read-model. The source of truth is SQLite / prefs / the native service; this store only
 * mirrors it for rendering. The device token is deliberately not part of it.
 */
type State = {
  loaded: boolean;
  paired: boolean;
  pairing: Pairing | null;
  connection: GatewayConnectionState;
  connectionMessage: string | null;
  running: GatewayRunningState;
  service: GatewayServiceState | null;
  simState: SimState;
  selectedSim: SelectedSim | null;
  sims: SimInfo[];
  counters: GatewayCounters;
  runtime: RuntimeInfo;
  settings: LocalSettings;
  serverConfig: ServerConfig | null;
  permissions: Record<PermissionKey, boolean>;
  batteryOptimizationIgnored: boolean;
  refresh: () => Promise<void>;
  setRunning: (r: GatewayRunningState) => void;
};

const EMPTY_COUNTERS: GatewayCounters = {sentToday: 0, failedToday: 0, pendingQueue: 0, pendingAcks: 0, lastSmsSentAt: null};

let refreshing: Promise<void> | null = null;
let queued = false;

export const useGatewayStore = create<State>((set, get) => ({
  loaded: false,
  paired: false,
  pairing: null,
  connection: 'DISCONNECTED',
  connectionMessage: null,
  running: 'STOPPED',
  service: null,
  simState: 'NOT_SELECTED',
  selectedSim: null,
  sims: [],
  counters: EMPTY_COUNTERS,
  runtime: DEFAULT_RUNTIME,
  settings: DEFAULT_SETTINGS,
  serverConfig: null,
  permissions: {sms: false, phoneState: false},
  batteryOptimizationIgnored: false,

  setRunning: r => set({running: r}),

  refresh: async () => {
    // Coalesce bursts of change events into at most one extra read.
    if (refreshing) {
      queued = true;
      return refreshing;
    }
    refreshing = (async () => {
      try {
        const [pairing, token, runtime, settings, serverConfig, counters, service, sim, permissions, battery] =
          await Promise.all([
            prefs.getPairing(),
            getDeviceToken(),
            prefs.getRuntime(),
            prefs.getSettings(),
            prefs.getServerConfig(),
            jobRepository.counters().catch(() => EMPTY_COUNTERS),
            SmsGateway.getGatewayServiceState().catch(() => null),
            resolveSelectedSim(),
            permissionSummary(),
            SmsGateway.isIgnoringBatteryOptimizations().catch(() => false),
          ]);
        const paired = !!pairing && !!token;
        const prev = get().running;
        let running: GatewayRunningState = service?.running ? 'RUNNING' : 'STOPPED';
        if (prev === 'STARTING' && !service?.running && service?.desiredRunning) {
          running = 'STARTING';
        }
        set({
          loaded: true,
          paired,
          pairing,
          connection: paired ? (runtime.connection as GatewayConnectionState) : 'DISCONNECTED',
          connectionMessage: runtime.connectionMessage,
          running,
          service,
          simState: sim.state,
          selectedSim: sim.selected,
          sims: sim.sims,
          counters,
          runtime,
          settings,
          serverConfig,
          permissions,
          batteryOptimizationIgnored: battery,
        });
      } finally {
        refreshing = null;
      }
    })();
    await refreshing;
    if (queued) {
      queued = false;
      await get().refresh();
    }
  },
}));

gatewayEvents.onChanged(() => {
  useGatewayStore.getState().refresh();
});

SmsGateway.onGatewayServiceEvent(() => {
  useGatewayStore.getState().refresh();
});
