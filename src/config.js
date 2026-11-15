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

if (isProd && !env.SESSION_SECRET) {
  throw new Error('SESSION_SECRET must be set in production');
}
if (onNetlify && !env.DATABASE_URL) {
  throw new Error('DATABASE_URL must be set to the Supabase connection string');
}

module.exports = {
  root,
  isProd,
  onNetlify,
  port: Number(env.PORT) || 3000,
  // Supabase Postgres connection string. When unset (local development and
  // tests) an embedded Postgres is used instead, stored under DATA_DIR.
  databaseUrl: env.DATABASE_URL || '',
  dataDir: env.DATA_DIR ? path.resolve(env.DATA_DIR) : path.join(root, 'data'),
  sessionSecret: env.SESSION_SECRET || 'helpie-dev-secret-change-me',
  // Supabase project URL + publishable key: used only for Realtime, so chat
  // messages arrive instantly. Without them chat falls back to polling.
  supabaseUrl: (env.SUPABASE_URL || '').replace(/\/+$/, ''),
  supabaseKey: env.SUPABASE_PUBLISHABLE_KEY || '',
  // Netlify buffers function payloads at 6 MB (about 4.5 MB of binary).
  maxUploadBytes: 4 * 1024 * 1024,
};
