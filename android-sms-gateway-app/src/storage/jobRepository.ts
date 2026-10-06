import type {ServerMessage} from '../types/api';
import type {GatewayCounters, GatewayJob, JobStatus} from '../types/gateway';
import {startOfToday} from '../utils/time';
import {getDb} from './database';

type Row = Record<string, unknown>;

function toJob(r: Row): GatewayJob {
  const num = (v: unknown) => (v === null || v === undefined ? null : Number(v));
  return {
    id: Number(r.id),
    serverMessageId: Number(r.server_message_id),
    phoneNumber: String(r.phone_number),
    message: String(r.message),
    priority: String(r.priority ?? 'normal'),
    simSlot: num(r.sim_slot),
    subscriptionId: num(r.subscription_id),
    simSlotUsed: num(r.sim_slot_used),
    status: r.status as JobStatus,
    attempt: Number(r.attempt ?? 1),
    sendToken: (r.send_token as string | null) ?? null,
    segmentCount: num(r.segment_count),
    receivedAt: Number(r.received_at),
    processingAt: num(r.processing_at),
    sentAt: num(r.sent_at),
    deliveredAt: num(r.delivered_at),
    completedAt: num(r.completed_at),
    failureCode: (r.failure_code as string | null) ?? null,
    failureMessage: (r.failure_message as string | null) ?? null,
    serverAcknowledged: Number(r.server_acknowledged) === 1,
    ackRetryCount: Number(r.ack_retry_count ?? 0),
    nextAckAt: num(r.next_ack_at),
    updatedAt: Number(r.updated_at),
  };
}

const TERMINAL: JobStatus[] = ['COMPLETED', 'ACK_REJECTED', 'FAILED_COMPLETE', 'CANCELLED'];
const RETRY_AFTER_FAILURE: JobStatus[] = ['FAILED_COMPLETE', 'FAILURE_ACK_PENDING', 'CANCELLED'];

function placeholders(n: number) {
  return new Array(n).fill('?').join(',');
}

export const jobRepository = {
  async get(serverMessageId: number): Promise<GatewayJob | null> {
    const db = await getDb();
    const res = await db.execute('SELECT * FROM gateway_jobs WHERE server_message_id = ?', [serverMessageId]);
    return res.rows.length ? toJob(res.rows[0]) : null;
  },

  /**
   * Records a message the server handed us. Returns the job to process, or null when the
   * existing local record forbids sending it (already sent, or currently being sent).
   *
   * A job we failed earlier and the server re-queued (retryable failure) comes back as a new
   * attempt. A job that already left the phone never does.
   */
  async receive(m: ServerMessage): Promise<{job: GatewayJob | null; existing: GatewayJob | null}> {
    const db = await getDb();
    const now = Date.now();
    const existing = await jobRepository.get(m.id);
    if (!existing) {
      await db.execute(
        `INSERT INTO gateway_jobs (server_message_id, phone_number, message, priority, sim_slot, status, received_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'RECEIVED', ?, ?)`,
        [m.id, m.phone_number, m.message, m.priority ?? 'normal', m.sim_slot ?? null, now, now],
      );
      return {job: await jobRepository.get(m.id), existing: null};
    }
    if (existing.status === 'RECEIVED' || existing.status === 'PROCESSING') {
      return {job: existing, existing};
    }
    if (RETRY_AFTER_FAILURE.includes(existing.status)) {
      await db.execute(
        `UPDATE gateway_jobs SET status='RECEIVED', attempt=attempt+1, phone_number=?, message=?, priority=?, sim_slot=?,
           send_token=NULL, segment_count=NULL, processing_at=NULL, failure_code=NULL, failure_message=NULL,
           server_acknowledged=0, ack_retry_count=0, next_ack_at=NULL, completed_at=NULL, received_at=?, updated_at=?
         WHERE server_message_id=? AND status IN (${placeholders(RETRY_AFTER_FAILURE.length)})`,
        [m.phone_number, m.message, m.priority ?? 'normal', m.sim_slot ?? null, now, now, m.id, ...RETRY_AFTER_FAILURE],
      );
      return {job: await jobRepository.get(m.id), existing};
    }
    return {job: null, existing};
  },

  /** Guarded transition: only applies when the job is in one of `from`. Returns whether it did. */
  async transition(
    serverMessageId: number,
    from: JobStatus[],
    to: JobStatus,
    fields: Partial<{
      subscription_id: number | null;
      sim_slot_used: number | null;
      send_token: string | null;
      segment_count: number | null;
      processing_at: number | null;
      sent_at: number | null;
      delivered_at: number | null;
      completed_at: number | null;
      failure_code: string | null;
      failure_message: string | null;
      server_acknowledged: number;
      next_ack_at: number | null;
    }> = {},
  ): Promise<boolean> {
    const db = await getDb();
    const sets = ['status = ?', 'updated_at = ?'];
    const args: Array<string | number | null> = [to, Date.now()];
    for (const [k, v] of Object.entries(fields)) {
      sets.push(`${k} = ?`);
      args.push(v as string | number | null);
    }
    const res = await db.execute(
      `UPDATE gateway_jobs SET ${sets.join(', ')} WHERE server_message_id = ? AND status IN (${placeholders(from.length)})`,
      [...args, serverMessageId, ...from],
    );
    return (res.rowsAffected ?? 0) > 0;
  },

  async setDelivered(serverMessageId: number, at: number): Promise<void> {
    const db = await getDb();
    await db.execute('UPDATE gateway_jobs SET delivered_at = ?, updated_at = ? WHERE server_message_id = ?', [
      at,
      Date.now(),
      serverMessageId,
    ]);
  },

  async deferAck(serverMessageId: number, retryCount: number, nextAckAt: number): Promise<void> {
    const db = await getDb();
    await db.execute(
      'UPDATE gateway_jobs SET ack_retry_count = ?, next_ack_at = ?, updated_at = ? WHERE server_message_id = ?',
      [retryCount, nextAckAt, Date.now(), serverMessageId],
    );
  },

  async byStatus(statuses: JobStatus[], limit = 500): Promise<GatewayJob[]> {
    const db = await getDb();
    const res = await db.execute(
      `SELECT * FROM gateway_jobs WHERE status IN (${placeholders(statuses.length)})
       ORDER BY CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END, received_at, id LIMIT ?`,
      [...statuses, limit],
    );
    return res.rows.map(toJob);
  },

  /** Acknowledgments that are due now. */
  async dueAcks(now: number, limit = 100): Promise<GatewayJob[]> {
    const db = await getDb();
    const res = await db.execute(
      `SELECT * FROM gateway_jobs WHERE status IN ('SENT_LOCAL','ACK_PENDING','FAILED_LOCAL','FAILURE_ACK_PENDING')
       AND (next_ack_at IS NULL OR next_ack_at <= ?) ORDER BY updated_at LIMIT ?`,
      [now, limit],
    );
    return res.rows.map(toJob);
  },

  async recent(limit = 50): Promise<GatewayJob[]> {
    const db = await getDb();
    const res = await db.execute('SELECT * FROM gateway_jobs ORDER BY updated_at DESC LIMIT ?', [limit]);
    return res.rows.map(toJob);
  },

  async counters(): Promise<GatewayCounters> {
    const db = await getDb();
    const today = startOfToday();
    const res = await db.execute(
      `SELECT
        SUM(CASE WHEN sent_at >= ? AND status IN ('SENT_LOCAL','ACK_PENDING','COMPLETED','ACK_REJECTED') THEN 1 ELSE 0 END) AS sent_today,
        SUM(CASE WHEN completed_at >= ? AND status IN ('FAILED_LOCAL','FAILURE_ACK_PENDING','FAILED_COMPLETE') THEN 1 ELSE 0 END) AS failed_today,
        SUM(CASE WHEN status IN ('RECEIVED','PROCESSING','SENDING') THEN 1 ELSE 0 END) AS pending_queue,
        SUM(CASE WHEN status IN ('SENT_LOCAL','ACK_PENDING','FAILED_LOCAL','FAILURE_ACK_PENDING') THEN 1 ELSE 0 END) AS pending_acks,
        MAX(sent_at) AS last_sent
       FROM gateway_jobs`,
      [today, today],
    );
    const r = res.rows[0] ?? {};
    return {
      sentToday: Number(r.sent_today ?? 0),
      failedToday: Number(r.failed_today ?? 0),
      pendingQueue: Number(r.pending_queue ?? 0),
      pendingAcks: Number(r.pending_acks ?? 0),
      lastSmsSentAt: r.last_sent === null || r.last_sent === undefined ? null : Number(r.last_sent),
    };
  },

  async sentSince(since: number): Promise<number> {
    const db = await getDb();
    const res = await db.execute(
      `SELECT COUNT(*) AS n FROM gateway_jobs WHERE sent_at >= ? AND status IN ('SENT_LOCAL','ACK_PENDING','COMPLETED','ACK_REJECTED')`,
      [since],
    );
    return Number(res.rows[0]?.n ?? 0);
  },

  /** Only finished jobs the server already knows about are ever deleted. */
  async purgeFinished(olderThan: number): Promise<void> {
    const db = await getDb();
    await db.execute(
      `DELETE FROM gateway_jobs WHERE status IN (${placeholders(TERMINAL.length)}) AND updated_at < ?`,
      [...TERMINAL, olderThan],
    );
  },
};
