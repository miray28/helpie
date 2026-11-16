'use strict';
const path = require('path');

const fs = require('fs');

// The project root is wherever views/ lives; inside a Netlify Function the
// bundle layout differs from the repository, so probe the likely places.
const root = [path.join(__dirname, '..'), process.cwd(), path.join(__dirname, '..', '..')]
  .find((dir) => fs.existsSync(path.join(dir, 'views'))) || path.join(__dirname, '..');
const env = process.env;
const onVercel = Boolean(env.VERCEL);
const onNetlify = !onVercel && Boolean(env.NETLIFY || env.AWS_LAMBDA_FUNCTION_NAME);
const isProd = env.NODE_ENV === 'production' || onNetlify || onVercel;

// DATABASE_URL (or POSTGRES_URL, which Vercel's Postgres integrations set)
// points at any Postgres. Netlify Database publishes NETLIFY_DB_URL instead.
function resolveDatabaseUrl() {
  if (env.DATABASE_URL) return env.DATABASE_URL;
  if (env.POSTGRES_URL) return env.POSTGRES_URL;
  if (env.NETLIFY_DB_URL) return env.NETLIFY_DB_URL;
  if (!onNetlify) return '';
  try {
    return require('@netlify/database').getConnectionString() || '';
  } catch (err) {
    return '';
  }
}
const databaseUrl = resolveDatabaseUrl();

if (isProd && !env.SESSION_SECRET) {
  throw new Error('SESSION_SECRET must be set in production');
}
if ((onNetlify || onVercel) && !databaseUrl) {
  throw new Error('No database connection string found. Set DATABASE_URL to a Postgres connection string.');
}

// Where uploaded images are kept: the host's blob store, or local disk.
const storage = onVercel ? 'vercel-blob' : onNetlify ? 'netlify-blobs' : 'disk';
if (storage === 'vercel-blob' && !env.BLOB_READ_WRITE_TOKEN) {
  console.warn('BLOB_READ_WRITE_TOKEN is not set: image uploads will fail until a Vercel Blob store is connected.');
}

module.exports = {
  root,
  isProd,
  onNetlify,
  onVercel,
  storage,
  // Netlify applies the SQL migrations itself for its own database; everywhere
  // else the app applies them on start-up.
  selfMigrate: !(onNetlify && !env.DATABASE_URL && !env.POSTGRES_URL),
  port: Number(env.PORT) || 3000,
  // Empty for local development and tests, where an embedded Postgres stored
  // under DATA_DIR is used instead.
  databaseUrl,
  dataDir: env.DATA_DIR ? path.resolve(env.DATA_DIR) : path.join(root, 'data'),
  sessionSecret: env.SESSION_SECRET || 'helpie-dev-secret-change-me',
  // Serverless hosts cap request bodies at roughly 4.5 MB.
  maxUploadBytes: 4 * 1024 * 1024,
};
