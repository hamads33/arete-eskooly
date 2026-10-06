import {open, type DB} from '@op-engineering/op-sqlite';

let db: DB | null = null;
let ready: Promise<DB> | null = null;

const MIGRATIONS: string[][] = [
  [
    `CREATE TABLE IF NOT EXISTS gateway_jobs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      server_message_id INTEGER NOT NULL UNIQUE,
      phone_number TEXT NOT NULL,
      message TEXT NOT NULL,
      priority TEXT NOT NULL DEFAULT 'normal',
      sim_slot INTEGER,
      subscription_id INTEGER,
      sim_slot_used INTEGER,
      status TEXT NOT NULL,
      attempt INTEGER NOT NULL DEFAULT 1,
      send_token TEXT,
      segment_count INTEGER,
      received_at INTEGER NOT NULL,
      processing_at INTEGER,
      sent_at INTEGER,
      delivered_at INTEGER,
      completed_at INTEGER,
      failure_code TEXT,
      failure_message TEXT,
      server_acknowledged INTEGER NOT NULL DEFAULT 0,
      ack_retry_count INTEGER NOT NULL DEFAULT 0,
      next_ack_at INTEGER,
      updated_at INTEGER NOT NULL
    )`,
    'CREATE INDEX IF NOT EXISTS idx_jobs_status ON gateway_jobs(status)',
    'CREATE INDEX IF NOT EXISTS idx_jobs_sent_at ON gateway_jobs(sent_at)',
    `CREATE TABLE IF NOT EXISTS gateway_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      timestamp INTEGER NOT NULL,
      level TEXT NOT NULL,
      event TEXT NOT NULL,
      server_message_id INTEGER,
      details TEXT
    )`,
    'CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON gateway_logs(timestamp)',
  ],
];

async function migrate(conn: DB): Promise<void> {
  await conn.execute('PRAGMA journal_mode = WAL');
  await conn.execute('PRAGMA synchronous = FULL');
  const res = await conn.execute('PRAGMA user_version');
  const current = Number(res.rows[0]?.user_version ?? 0);
  for (let v = current; v < MIGRATIONS.length; v++) {
    await conn.transaction(async tx => {
      for (const sql of MIGRATIONS[v]) {
        await tx.execute(sql);
      }
    });
    await conn.execute(`PRAGMA user_version = ${v + 1}`);
  }
}

/** Opens and migrates once; safe to call from UI and from headless tasks concurrently. */
export function getDb(): Promise<DB> {
  if (db) {
    return Promise.resolve(db);
  }
  if (!ready) {
    ready = (async () => {
      const conn = open({name: 'sms_gateway.db'});
      await migrate(conn);
      db = conn;
      return conn;
    })().catch(e => {
      ready = null;
      throw e;
    });
  }
  return ready;
}
