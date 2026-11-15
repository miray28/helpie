'use strict';
const path = require('path');

const root = path.join(__dirname, '..');
const dataDir = process.env.DATA_DIR ? path.resolve(process.env.DATA_DIR) : path.join(root, 'data');
const isProd = process.env.NODE_ENV === 'production';

if (isProd && !process.env.SESSION_SECRET) {
  throw new Error('SESSION_SECRET must be set when NODE_ENV=production');
}

module.exports = {
  root,
  dataDir,
  isProd,
  port: Number(process.env.PORT) || 3000,
  dbFile: process.env.DB_FILE || path.join(dataDir, 'helpie.db'),
  uploadDir: path.join(dataDir, 'uploads'),
  sessionSecret: process.env.SESSION_SECRET || 'helpie-dev-secret-change-me',
  // Set TRUST_PROXY=1 when running behind a reverse proxy that terminates TLS.
  trustProxy: process.env.TRUST_PROXY === '1',
  maxUploadBytes: 5 * 1024 * 1024,
};
