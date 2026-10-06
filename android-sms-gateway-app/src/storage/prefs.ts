import AsyncStorage from '@react-native-async-storage/async-storage';
import type {ServerConfig} from '../types/api';
import {
  DEFAULT_SETTINGS,
  type LocalSettings,
  type Pairing,
  type SelectedSim,
} from '../types/gateway';

/** Non-sensitive preferences only. Secrets live in secureStore.ts. */
const KEYS = {
  pairing: 'smsgw.pairing',
  sim: 'smsgw.selectedSim',
  settings: 'smsgw.settings',
  serverConfig: 'smsgw.serverConfig',
  runtime: 'smsgw.runtime',
} as const;

export type RuntimeInfo = {
  lastSyncAt: number | null;
  lastHeartbeatAt: number | null;
  lastHeartbeatOk: boolean;
  lastConfigAt: number | null;
  queuedOnServer: number | null;
  connection: string;
  connectionMessage: string | null;
  pauseReason: string | null;
  pausedUntil: number | null;
  lastSimReportedSubId: number | null;
};

export const DEFAULT_RUNTIME: RuntimeInfo = {
  lastSyncAt: null,
  lastHeartbeatAt: null,
  lastHeartbeatOk: false,
  lastConfigAt: null,
  queuedOnServer: null,
  connection: 'DISCONNECTED',
  connectionMessage: null,
  pauseReason: null,
  pausedUntil: null,
  lastSimReportedSubId: null,
};

let runtimeWrites: Promise<unknown> = Promise.resolve();

async function read<T>(key: string): Promise<T | null> {
  try {
    const raw = await AsyncStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : null;
  } catch {
    return null;
  }
}

async function write(key: string, value: unknown): Promise<void> {
  await AsyncStorage.setItem(key, JSON.stringify(value));
}

export const prefs = {
  getPairing: () => read<Pairing>(KEYS.pairing),
  setPairing: (p: Pairing) => write(KEYS.pairing, p),
  clearPairing: () => AsyncStorage.removeItem(KEYS.pairing),

  getSelectedSim: () => read<SelectedSim>(KEYS.sim),
  setSelectedSim: (s: SelectedSim) => write(KEYS.sim, s),
  clearSelectedSim: () => AsyncStorage.removeItem(KEYS.sim),

  async getSettings(): Promise<LocalSettings> {
    return {...DEFAULT_SETTINGS, ...((await read<Partial<LocalSettings>>(KEYS.settings)) ?? {})};
  },
  setSettings: (s: LocalSettings) => write(KEYS.settings, s),

  getServerConfig: () => read<ServerConfig>(KEYS.serverConfig),
  setServerConfig: (c: ServerConfig) => write(KEYS.serverConfig, c),

  async getRuntime(): Promise<RuntimeInfo> {
    return {...DEFAULT_RUNTIME, ...((await read<Partial<RuntimeInfo>>(KEYS.runtime)) ?? {})};
  },
  patchRuntime(patch: Partial<RuntimeInfo>): Promise<RuntimeInfo> {
    const run = runtimeWrites.then(async () => {
      const next = {...(await prefs.getRuntime()), ...patch};
      await write(KEYS.runtime, next);
      return next;
    });
    runtimeWrites = run.catch(() => undefined);
    return run;
  },

  async clearAll(): Promise<void> {
    await AsyncStorage.removeMany(Object.values(KEYS));
  },
};
