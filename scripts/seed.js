#!/usr/bin/env node
'use strict';
// Fills an empty database with demo members and posts for local development.
//   npm run seed
// Every demo account uses the password "helpie123".
const bcrypt = require('bcryptjs');
const { db } = require('../src/db');
const chat = require('../src/chat');

const HOUR = 3600 * 1000;

async function main() {
  if ((await db.get('SELECT COUNT(*) AS n FROM users')).n > 0) {
    console.log('Database already has users — skipping seed.');
    return;
  }
  const hash = await bcrypt.hash('helpie123', 10);
  const now = Date.now();

  const user = async (name, role, about, address, verified, hoursAgo) => (await db.get(`
    INSERT INTO users (username, email, password_hash, role, about, address, verified, realtime_topic, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id`,
    [name, `${name}@example.com`, hash, role, about, address, verified, chat.newTopic(), now - hoursAgo * HOUR])).id;

  await user('admin', 'admin', 'Helpie moderator.', 'Remote', 1, 900);
  const sara = await user('sara', 'donor', 'Retired nurse clearing out a home-care cupboard.', 'Leeds', 1, 600);
  const omar = await user('omar', 'recipient', 'Caring for my father after his hip surgery.', 'Bradford', 0, 300);
  const lena = await user('lena', 'donor', 'Physio clinic owner. Happy to pass on spare equipment.', 'Manchester', 1, 200);

  const category = async (name) => (await db.get('SELECT id FROM categories WHERE name = ?', [name])).id;
  const mobility = await category('Mobility aids');
  const post = async (p, hoursAgo) => (await db.get(`
    INSERT INTO posts (user_id, type, title, content, category_id, condition_state, quantity, location, status, created_at, updated_at)
    VALUES (@user, @type, @title, @content, @category, @condition, @quantity, @location, @status, @at, @at) RETURNING id`,
    { condition: null, quantity: 1, status: 'available', ...p, at: now - hoursAgo * HOUR })).id;

  const wheelchair = await post({ user: sara, type: 'donation', title: 'Lightweight folding wheelchair', content: 'Used for about six months, folds flat for a car boot. Brakes and footrests all work. Collection only.', category: mobility, condition: 'good', location: 'Leeds' }, 50);
  await post({ user: lena, type: 'donation', title: 'Box of sterile gauze dressings', content: 'Unopened box of 100 sterile 10x10cm dressings, well within date. Surplus from the clinic.', category: await category('Wound care'), condition: 'new', quantity: 100, location: 'Manchester' }, 30);
  await post({ user: omar, type: 'request', title: 'Looking for a walking frame', content: 'My father needs a height-adjustable walking frame for the next few months while he recovers. Can collect anywhere in West Yorkshire.', category: mobility, location: 'Bradford' }, 20);
  await post({ user: lena, type: 'donation', title: 'Pair of adjustable elbow crutches', content: 'Adjustable aluminium crutches, new ferrules fitted last month. Suitable for heights 5\'2" to 6\'2".', category: mobility, condition: 'like_new', quantity: 2, location: 'Manchester' }, 8);
  await post({ user: sara, type: 'donation', title: 'Blood pressure monitor', content: 'Upper-arm digital monitor with standard cuff. Works perfectly, we simply have two.', category: await category('Medical equipment'), condition: 'good', location: 'Leeds', status: 'claimed' }, 100);

  const comment = (author, text, hoursAgo) => db.run('INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, ?)',
    [wheelchair, author, text, now - hoursAgo * HOUR]);
  await comment(omar, 'Is the seat width standard (about 18 inches)?', 40);
  await comment(sara, 'Yes, 18 inches. It takes up to 115 kg.', 39);
  await db.run('INSERT INTO donation_requests (post_id, requester_id, notes, created_at) VALUES (?, ?, ?, ?)',
    [wheelchair, omar, 'This would make hospital appointments so much easier for my dad.', now - 38 * HOUR]);
  await db.run('INSERT INTO saved_posts (user_id, post_id, saved_at) VALUES (?, ?, ?)', [omar, wheelchair, now - 38 * HOUR]);
  await chat.send(omar, sara, 'Hi Sara, I sent a request for the wheelchair. Would Saturday work for collection?');
  await chat.send(sara, omar, 'Hi Omar! Saturday morning is fine.');

  console.log('Seeded demo data. Sign in as admin, sara, omar or lena with password "helpie123".');
}

main()
  .catch((err) => { console.error(err); process.exitCode = 1; })
  .finally(() => db.close());
