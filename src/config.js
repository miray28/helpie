'use strict';
const path = require('path');

const fs = require('fs');

// The project root is wherever views/ lives; inside a Netlify Function the
// bundle layout differs from the repository, so probe the likely places.
const root = [path.join(__dirname, '..'), process.cwd(), path.join(__dirname, '..', '..')]
  .find((dir) => fs.existsSync(path.join(dir, 'views'))) || path.join(__dirname, '..');
const env = process.env;
const onNetlify = Boolean(env.NETLIFY || env.AWS_LAMBDA_FUNCTION_NAME);
const isProd = env.NODE_ENV === 'production' || onNetlify;

// Netlify Database (managed Postgres) publishes its connection string as
// NETLIFY_DB_URL. DATABASE_URL lets you point at any other Postgres instead.
function resolveDatabaseUrl() {
  if (env.DATABASE_URL) return env.DATABASE_URL;
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
if (onNetlify && !databaseUrl) {
  throw new Error('No database connection string found (expected NETLIFY_DB_URL or DATABASE_URL)');
}

module.exports = {
  root,
  isProd,
  onNetlify,
  port: Number(env.PORT) || 3000,
  // Empty for local development and tests, where an embedded Postgres stored
  // under DATA_DIR is used instead.
  databaseUrl,
  dataDir: env.DATA_DIR ? path.resolve(env.DATA_DIR) : path.join(root, 'data'),
  sessionSecret: env.SESSION_SECRET || 'helpie-dev-secret-change-me',
  // Netlify buffers function payloads at 6 MB (about 4.5 MB of binary).
  maxUploadBytes: 4 * 1024 * 1024,
};
