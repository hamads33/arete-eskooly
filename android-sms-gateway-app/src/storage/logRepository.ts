import type {GatewayLog, LogLevel} from '../types/gateway';
import {getDb} from './database';

type Listener = () => void;
const listeners = new Set<Listener>();

export const logRepository = {
  async add(level: LogLevel, event: string, details?: string | null, serverMessageId?: number | null): Promise<void> {
    try {
      const db = await getDb();
      await db.execute(
        'INSERT INTO gateway_logs (timestamp, level, event, server_message_id, details) VALUES (?, ?, ?, ?, ?)',
        [Date.now(), level, event, serverMessageId ?? null, details ?? null],
      );
      listeners.forEach(l => l());
    } catch {
      // Logging must never break the gateway.
    }
  },

  async list(limit = 300, level?: LogLevel): Promise<GatewayLog[]> {
    const db = await getDb();
    const res = level
      ? await db.execute('SELECT * FROM gateway_logs WHERE level = ? ORDER BY id DESC LIMIT ?', [level, limit])
      : await db.execute('SELECT * FROM gateway_logs ORDER BY id DESC LIMIT ?', [limit]);
    return res.rows.map(r => ({
      id: Number(r.id),
      timestamp: Number(r.timestamp),
      level: r.level as LogLevel,
      event: String(r.event),
      serverMessageId: r.server_message_id === null ? null : Number(r.server_message_id),
      details: (r.details as string | null) ?? null,
    }));
  },

  async purge(olderThan: number): Promise<void> {
    const db = await getDb();
    await db.execute('DELETE FROM gateway_logs WHERE timestamp < ?', [olderThan]);
  },

  async clear(): Promise<void> {
    const db = await getDb();
    await db.execute('DELETE FROM gateway_logs');
    listeners.forEach(l => l());
  },

  subscribe(listener: Listener): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};
