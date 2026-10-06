/**
 * Test double for @op-engineering/op-sqlite backed by Node's built-in SQLite, so repository and
 * engine tests run real SQL (constraints, guarded UPDATEs, rowsAffected) instead of a fake.
 */
const {DatabaseSync} = process.getBuiltinModule('node:sqlite');

function makeDb() {
  const db = new DatabaseSync(':memory:');
  const execute = async (sql, params = []) => {
    const stmt = db.prepare(sql);
    const args = params.map(p => (typeof p === 'boolean' ? (p ? 1 : 0) : p));
    if (stmt.columns().length > 0) {
      return {rows: stmt.all(...args), rowsAffected: 0};
    }
    const r = stmt.run(...args);
    return {rows: [], rowsAffected: Number(r.changes)};
  };
  const handle = {
    execute,
    transaction: async fn => {
      db.exec('BEGIN');
      try {
        await fn({execute});
        db.exec('COMMIT');
      } catch (e) {
        db.exec('ROLLBACK');
        throw e;
      }
    },
  };
  return handle;
}

let current = null;

module.exports = {
  open: () => {
    current = current ?? makeDb();
    return current;
  },
  __reset: () => {
    current = null;
  },
};
