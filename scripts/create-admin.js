#!/usr/bin/env node
'use strict';
// Creates an administrator, or promotes an existing account.
//   npm run create-admin -- <username> <email> [password]
// If the email already belongs to an account it is promoted to admin and the
// password is left unchanged. When no password is given one is generated.
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const { db } = require('../src/db');

const [username, email, passwordArg] = process.argv.slice(2);
if (!username || !email) {
  console.error('Usage: npm run create-admin -- <username> <email> [password]');
  process.exit(1);
}

const existing = db.prepare('SELECT id, username FROM users WHERE email = ? OR username = ?').get(email, username);
if (existing) {
  db.prepare("UPDATE users SET role = 'admin', verified = 1, blocked = 0 WHERE id = ?").run(existing.id);
  console.log(`Promoted existing account "${existing.username}" to admin.`);
} else {
  const password = passwordArg || crypto.randomBytes(9).toString('base64url');
  if (password.length < 8) {
    console.error('Password must be at least 8 characters.');
    process.exit(1);
  }
  db.prepare("INSERT INTO users (username, email, password_hash, role, verified, created_at) VALUES (?, ?, ?, 'admin', 1, ?)")
    .run(username, email.toLowerCase(), bcrypt.hashSync(password, 12), Date.now());
  console.log(`Created admin "${username}".`);
  if (!passwordArg) console.log(`Generated password: ${password}`);
}
