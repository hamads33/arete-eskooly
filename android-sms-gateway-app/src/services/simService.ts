import SmsGateway, {type SimInfo} from '../native/SmsGateway';
import {prefs} from '../storage/prefs';
import type {SelectedSim, SimState} from '../types/gateway';

export async function listSims(): Promise<{sims: SimInfo[]; state: 'ok' | 'PERMISSION_REQUIRED' | 'ERROR'}> {
  try {
    return {sims: await SmsGateway.getAvailableSims(), state: 'ok'};
  } catch (e) {
    const code = (e as {code?: string})?.code;
    return {sims: [], state: code === 'PERMISSION_REQUIRED' ? 'PERMISSION_REQUIRED' : 'ERROR'};
  }
}

export function simLabel(sim: {slotIndex: number; carrierName: string | null; displayName: string | null}): string {
  const name = sim.carrierName || sim.displayName || 'Unknown carrier';
  return sim.slotIndex >= 0 ? `SIM ${sim.slotIndex + 1} · ${name}` : name;
}

export function toSelected(sim: SimInfo): SelectedSim {
  return {
    subscriptionId: sim.subscriptionId,
    slotIndex: sim.slotIndex,
    carrierName: sim.carrierName,
    displayName: sim.displayName,
  };
}

/**
 * Subscription ids change when a SIM is removed/swapped, so a stored id that is no longer
 * active means "the selected SIM is gone" and sending must pause until someone reselects.
 */
export async function resolveSelectedSim(): Promise<{state: SimState; selected: SelectedSim | null; sims: SimInfo[]}> {
  const selected = await prefs.getSelectedSim();
  const {sims, state} = await listSims();
  if (state === 'PERMISSION_REQUIRED') {
    return {state: 'PERMISSION_REQUIRED', selected, sims};
  }
  if (!selected) {
    return {state: 'NOT_SELECTED', selected: null, sims};
  }
  const active = sims.find(s => s.subscriptionId === selected.subscriptionId);
  return {state: active ? 'AVAILABLE' : 'MISSING', selected, sims};
}

/**
 * The SIM for one LMS message. The LMS may name a slot (1-based); it is honoured when a SIM is
 * active in that slot, otherwise the admin-selected SIM is used.
 */
export function chooseSim(serverSlot: number | null, sims: SimInfo[], selected: SelectedSim): SimInfo | null {
  if (serverSlot && serverSlot > 0) {
    const bySlot = sims.find(s => s.slotIndex === serverSlot - 1);
    if (bySlot) {
      return bySlot;
    }
  }
  return sims.find(s => s.subscriptionId === selected.subscriptionId) ?? null;
}
