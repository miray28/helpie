'use strict';
const fs = require('fs');
const path = require('path');
const config = require('./config');

// Thin async wrapper so the rest of the app doesn't care whether it is
// talking to a hosted Postgres (through `pg`) or the embedded one used locally.
//
//   db.all(sql, params)  -> rows
//   db.get(sql, params)  -> first row or undefined
//   db.run(sql, params)  -> { rowCount, rows }
//   db.tx(async (tx) => { ... })   -> same three methods, inside a transaction
//
// `params` is either an array (placeholders written as `?`) or an object
// (placeholders written as `@name`).

function compile(sql, params) {
  if (!params) return { text: sql, values: [] };
  if (Array.isArray(params)) {
    let i = 0;
    return { text: sql.replace(/\?/g, () => `$${++i}`), values: params };
  }
  const names = [];
  const text = sql.replace(/@([A-Za-z_]\w*)/g, (_, name) => {
    if (!(name in params)) throw new Error(`Missing SQL parameter: ${name}`);
    let index = names.indexOf(name);
    if (index === -1) index = names.push(name) - 1;
    return `$${index + 1}`;
  });
  return { text, values: names.map((n) => (params[n] === undefined ? null : params[n])) };
}

function wrap(query) {
  const run = async (sql, params) => {
    const { text, values } = compile(sql, params);
    return query(text, values);
  };
  return {
    run,
    all: async (sql, params) => (await run(sql, params)).rows,
    get: async (sql, params) => (await run(sql, params)).rows[0],
  };
}

const MIGRATIONS_DIR = path.join(config.root, 'netlify', 'database', 'migrations');

function createHosted() {
  const { Pool, types } = require('pg');
  types.setTypeParser(20, Number); // bigint (timestamps, counts) -> JS number
  const pool = new Pool({
    connectionString: config.databaseUrl,
    ssl: /localhost|127\.0\.0\.1/.test(config.databaseUrl) ? false : { rejectUnauthorized: false },
    // Serverless: each function instance keeps one small pool for its lifetime.
    // The generous connect timeout covers the database waking from idle.
    max: 3,
    idleTimeoutMillis: 10000,
    connectionTimeoutMillis: 20000,
  });
  pool.on('error', (err) => console.error('Database pool error:', err.message));
  const normalise = (r) => ({ rows: r.rows || [], rowCount: r.rowCount || 0 });
  return {
    ...wrap(async (text, values) => normalise(await pool.query(text, values))),
    async tx(fn) {
      const client = await pool.connect();
      try {
        await client.query('BEGIN');
        const result = await fn(wrap(async (text, values) => normalise(await client.query(text, values))));
        await client.query('COMMIT');
        return result;
      } catch (err) {
        await client.query('ROLLBACK').catch(() => {});
        throw err;
      } finally {
        client.release();
      }
    },
    ready: Promise.resolve(), // Netlify applies the migrations on deploy
    close: () => pool.end(),
  };
}

function createEmbedded() {
  // Loaded through a variable so the Netlify bundler doesn't ship it.
  const moduleName = '@electric-sql/pglite';
  const { PGlite } = require(moduleName);
  const inMemory = process.env.DATA_DIR === ':memory:';
  if (!inMemory) fs.mkdirSync(config.dataDir, { recursive: true });
  const pg = new PGlite(inMemory ? undefined : path.join(config.dataDir, 'pgdata'), { parsers: { 20: Number } });
  const normalise = (r) => ({ rows: r.rows || [], rowCount: r.affectedRows || (r.rows ? r.rows.length : 0) });
  const ready = (async () => {
    for (const file of fs.readdirSync(MIGRATIONS_DIR).sort()) {
      await pg.exec(fs.readFileSync(path.join(MIGRATIONS_DIR, file), 'utf8'));
    }
  })();
  const query = async (text, values) => { await ready; return normalise(await pg.query(text, values)); };
  return {
    ...wrap(query),
    async tx(fn) {
      await ready;
      return pg.transaction((t) => fn(wrap(async (text, values) => normalise(await t.query(text, values)))));
    },
    ready,
    close: () => pg.close(),
  };
}

const db = config.databaseUrl ? createHosted() : createEmbedded();

const DEFAULT_SETTINGS = {
  site_name: 'Helpie',
  site_description: 'Connect and share medical supplies with those in need.',
  contact_email: '',
  posts_per_page: '12',
  enable_registration: '1',
  maintenance_mode: '0',
};

async function getSettings() {
  const out = { ...DEFAULT_SETTINGS };
  for (const row of await db.all('SELECT key, value FROM settings')) out[row.key] = row.value;
  return out;
}

function setSetting(key, value, runner = db) {
  return runner.run(
    'INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT (key) DO UPDATE SET value = excluded.value',
    [key, String(value)]);
}

module.exports = { db, getSettings, setSetting };
