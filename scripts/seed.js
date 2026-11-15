#!/usr/bin/env node
'use strict';
// Fills an empty database with demo members and posts for local development.
//   npm run seed
// Every demo account uses the password "helpie123".
const bcrypt = require('bcryptjs');
const { db } = require('../src/db');
const chat = require('../src/chat');

if (db.prepare('SELECT COUNT(*) AS n FROM users').get().n > 0) {
  console.log('Database already has users — skipping seed.');
  process.exit(0);
}

const hash = bcrypt.hashSync('helpie123', 10);
const HOUR = 3600 * 1000;
const now = Date.now();
const insertUser = db.prepare(`
  INSERT INTO users (username, email, password_hash, role, about, address, verified, created_at)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)`);
const user = (name, role, about, address, verified, hoursAgo) =>
  Number(insertUser.run(name, `${name}@example.com`, hash, role, about, address, verified, now - hoursAgo * HOUR).lastInsertRowid);

const admin = user('admin', 'admin', 'Helpie moderator.', 'Remote', 1, 900);
const sara = user('sara', 'donor', 'Retired nurse clearing out a home-care cupboard.', 'Leeds', 1, 600);
const omar = user('omar', 'recipient', 'Caring for my father after his hip surgery.', 'Bradford', 0, 300);
const lena = user('lena', 'donor', 'Physio clinic owner. Happy to pass on spare equipment.', 'Manchester', 1, 200);

const category = (name) => db.prepare('SELECT id FROM categories WHERE name = ?').get(name).id;
const insertPost = db.prepare(`
  INSERT INTO posts (user_id, type, title, content, category_id, condition_state, quantity, location, status, created_at, updated_at)
  VALUES (@user, @type, @title, @content, @category, @condition, @quantity, @location, @status, @at, @at)`);
const post = (p, hoursAgo) => Number(insertPost.run({ condition: null, quantity: 1, status: 'available', ...p, at: now - hoursAgo * HOUR }).lastInsertRowid);

const wheelchair = post({ user: sara, type: 'donation', title: 'Lightweight folding wheelchair', content: 'Used for about six months, folds flat for a car boot. Brakes and footrests all work. Collection only.', category: category('Mobility aids'), condition: 'good', location: 'Leeds' }, 50);
post({ user: lena, type: 'donation', title: 'Box of sterile gauze dressings', content: 'Unopened box of 100 sterile 10x10cm dressings, well within date. Surplus from the clinic.', category: category('Wound care'), condition: 'new', quantity: 100, location: 'Manchester' }, 30);
post({ user: omar, type: 'request', title: 'Looking for a walking frame', content: 'My father needs a height-adjustable walking frame for the next few months while he recovers. Can collect anywhere in West Yorkshire.', category: category('Mobility aids'), location: 'Bradford' }, 20);
post({ user: lena, type: 'donation', title: 'Pair of adjustable elbow crutches', content: 'Adjustable aluminium crutches, new ferrules fitted last month. Suitable for heights 5\'2" to 6\'2".', category: category('Mobility aids'), condition: 'like_new', quantity: 2, location: 'Manchester' }, 8);
post({ user: sara, type: 'donation', title: 'Blood pressure monitor', content: 'Upper-arm digital monitor with standard cuff. Works perfectly, we simply have two.', category: category('Medical equipment'), condition: 'good', location: 'Leeds', status: 'claimed' }, 100);

db.prepare('INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, ?)')
  .run(wheelchair, omar, 'Is the seat width standard (about 18 inches)?', now - 40 * HOUR);
db.prepare('INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, ?)')
  .run(wheelchair, sara, 'Yes, 18 inches. It takes up to 115 kg.', now - 39 * HOUR);
db.prepare('INSERT INTO donation_requests (post_id, requester_id, notes, created_at) VALUES (?, ?, ?, ?)')
  .run(wheelchair, omar, 'This would make hospital appointments so much easier for my dad.', now - 38 * HOUR);
db.prepare('INSERT INTO saved_posts (user_id, post_id, saved_at) VALUES (?, ?, ?)').run(omar, wheelchair, now - 38 * HOUR);
chat.send(omar, sara, 'Hi Sara, I sent a request for the wheelchair. Would Saturday work for collection?');
chat.send(sara, omar, 'Hi Omar! Saturday morning is fine.');

console.log('Seeded demo data. Sign in as admin, sara, omar or lena with password "helpie123".');
void admin;
