import SmsGateway, {type SmsEvent, type StoredSmsResult} from '../native/SmsGateway';

export type SmsOutcome =
  | {status: 'sent'; sendToken: string; segmentCount: number; completedAt: number}
  // phase 'rejected': Android never accepted it, so it certainly did not go out.
  | {status: 'failed'; phase: 'rejected' | 'radio'; errorCode: string; sendToken: string | null; segmentCount: number}
  | {status: 'unknown'; sendToken: string; segmentCount: number};

type Waiter = {sendToken: string; resolve: (e: SmsEvent) => void};

const waiters = new Map<string, Waiter>();
const earlyFinals = new Map<string, SmsEvent>();
const deliveredListeners = new Set<(e: SmsEvent) => void>();

// One subscription per JS runtime. Events carry jobId + sendToken, so concurrent or stale
// attempts can never be confused (there is no "current message" global).
SmsGateway.onSmsEvent(e => {
  if (e.status === 'delivered' || e.status === 'delivery_failed') {
    deliveredListeners.forEach(l => l(e));
    return;
  }
  if (e.status !== 'sent' && e.status !== 'failed') {
    return;
  }
  const w = waiters.get(e.jobId);
  if (w && w.sendToken === e.sendToken) {
    waiters.delete(e.jobId);
    w.resolve(e);
  } else {
    earlyFinals.set(`${e.jobId}|${e.sendToken}`, e);
  }
});

export function onDelivery(listener: (e: SmsEvent) => void): () => void {
  deliveredListeners.add(listener);
  return () => deliveredListeners.delete(listener);
}

async function storedResult(jobId: string): Promise<StoredSmsResult | null> {
  try {
    const all = await SmsGateway.getStoredResults();
    return all.find(r => r.jobId === jobId) ?? null;
  } catch {
    return null;
  }
}

function fromFinal(status: string, errorCode: string | null, sendToken: string, segmentCount: number, at: number): SmsOutcome {
  return status === 'sent'
    ? {status: 'sent', sendToken, segmentCount, completedAt: at}
    : {status: 'failed', phase: 'radio', errorCode: errorCode ?? 'GENERIC_FAILURE', sendToken, segmentCount};
}

/**
 * Hands one SMS to Android and waits for the sent-intent verdict (all segments). Never reports
 * success just because SmsManager accepted the call. The native store is polled as a backstop
 * in case an event is missed (e.g. the JS runtime was reloaded mid-send).
 */
export async function sendAndAwait(
  req: {jobId: string; phoneNumber: string; message: string; subscriptionId: number; requestDeliveryReport: boolean},
  timeoutMs: number,
): Promise<SmsOutcome> {
  let accepted;
  try {
    accepted = await SmsGateway.sendSms(req);
  } catch (e) {
    const code = (e as {code?: string})?.code ?? 'SEND_EXCEPTION';
    return {status: 'failed', phase: 'rejected', errorCode: code, sendToken: null, segmentCount: 0};
  }
  const {sendToken, segmentCount} = accepted;

  const early = earlyFinals.get(`${req.jobId}|${sendToken}`);
  if (early) {
    earlyFinals.delete(`${req.jobId}|${sendToken}`);
    return fromFinal(early.status, early.errorCode, sendToken, segmentCount, early.timestamp);
  }

  const eventPromise = new Promise<SmsEvent>(resolve => waiters.set(req.jobId, {sendToken, resolve}));
  const deadline = Date.now() + timeoutMs;
  try {
    while (Date.now() < deadline) {
      let timer: ReturnType<typeof setTimeout> | undefined;
      const poll = new Promise<null>(resolve => {
        timer = setTimeout(() => resolve(null), 5_000);
      });
      const winner = await Promise.race([eventPromise, poll]);
      clearTimeout(timer);
      if (winner) {
        return fromFinal(winner.status, winner.errorCode, sendToken, segmentCount, winner.timestamp);
      }
      const stored = await storedResult(req.jobId);
      if (stored && stored.sendToken === sendToken && (stored.status === 'sent' || stored.status === 'failed')) {
        return fromFinal(stored.status, stored.errorCode, sendToken, segmentCount, stored.completedAt || Date.now());
      }
    }
    return {status: 'unknown', sendToken, segmentCount};
  } finally {
    const w = waiters.get(req.jobId);
    if (w && w.sendToken === sendToken) {
      waiters.delete(req.jobId);
    }
  }
}

export async function allStoredResults(): Promise<Map<string, StoredSmsResult>> {
  const list = await SmsGateway.getStoredResults();
  return new Map(list.map(r => [r.jobId, r]));
}

export function clearStoredResult(jobId: string): void {
  try {
    SmsGateway.clearStoredResult(jobId);
  } catch {
    // Best effort: stale records are pruned natively after a week.
  }
}
