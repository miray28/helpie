#!/usr/bin/env node
'use strict';
// Creates an administrator, or promotes an existing account.
//   npm run create-admin -- <username> <email> [password]
// Runs against DATABASE_URL when it is set, otherwise the local database.
// If the account already exists it is promoted and its password left alone.
// When no password is given one is generated and printed.
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const { db } = require('../src/db');
const { newTopic } = require('../src/chat');

async function main() {
  const [username, email, passwordArg] = process.argv.slice(2);
  if (!username || !email) throw new Error('Usage: npm run create-admin -- <username> <email> [password]');

  const existing = await db.get('SELECT id, username FROM users WHERE lower(email) = lower(?) OR lower(username) = lower(?)', [email, username]);
  if (existing) {
    await db.run("UPDATE users SET role = 'admin', verified = 1, blocked = 0 WHERE id = ?", [existing.id]);
    console.log(`Promoted existing account "${existing.username}" to admin.`);
    return;
  }
  const password = passwordArg || crypto.randomBytes(9).toString('base64url');
  if (password.length < 8) throw new Error('Password must be at least 8 characters.');
  await db.run(
    "INSERT INTO users (username, email, password_hash, role, verified, realtime_topic, created_at) VALUES (?, ?, ?, 'admin', 1, ?, ?)",
    [username, email.toLowerCase(), await bcrypt.hash(password, 11), newTopic(), Date.now()]);
  console.log(`Created admin "${username}".`);
  if (!passwordArg) console.log(`Generated password: ${password}`);
}

main()
  .catch((err) => { console.error(err.message); process.exitCode = 1; })
  .finally(() => db.close());
