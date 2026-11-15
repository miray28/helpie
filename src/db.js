'use strict';
const fs = require('fs');
const path = require('path');
const Database = require('better-sqlite3');
const config = require('./config');

if (config.dbFile !== ':memory:') fs.mkdirSync(path.dirname(config.dbFile), { recursive: true });

const db = new Database(config.dbFile);
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

// All timestamps are stored as integer milliseconds since the Unix epoch.
db.exec(`
CREATE TABLE IF NOT EXISTS users (
  id              INTEGER PRIMARY KEY,
  username        TEXT NOT NULL UNIQUE COLLATE NOCASE,
  email           TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash   TEXT NOT NULL,
  role            TEXT NOT NULL DEFAULT 'donor' CHECK (role IN ('donor','recipient','admin')),
  about           TEXT,
  phone           TEXT,
  address         TEXT,
  profile_image   TEXT,
  cover_image     TEXT,
  verified        INTEGER NOT NULL DEFAULT 0,
  profile_visible INTEGER NOT NULL DEFAULT 1,
  blocked         INTEGER NOT NULL DEFAULT 0,
  created_at      INTEGER NOT NULL,
  last_login      INTEGER
);

CREATE TABLE IF NOT EXISTS categories (
  id          INTEGER PRIMARY KEY,
  name        TEXT NOT NULL UNIQUE COLLATE NOCASE,
  description TEXT
);

CREATE TABLE IF NOT EXISTS posts (
  id              INTEGER PRIMARY KEY,
  user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type            TEXT NOT NULL CHECK (type IN ('donation','request')),
  title           TEXT NOT NULL,
  content         TEXT NOT NULL,
  category_id     INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  condition_state TEXT CHECK (condition_state IN ('new','like_new','good','fair')),
  quantity        INTEGER,
  expiry_date     TEXT,
  location        TEXT,
  image           TEXT,
  status          TEXT NOT NULL DEFAULT 'available' CHECK (status IN ('available','pending','claimed')),
  created_at      INTEGER NOT NULL,
  updated_at      INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS posts_created ON posts(created_at DESC);
CREATE INDEX IF NOT EXISTS posts_user ON posts(user_id);

CREATE TABLE IF NOT EXISTS comments (
  id         INTEGER PRIMARY KEY,
  post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  content    TEXT NOT NULL,
  created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS comments_post ON comments(post_id);

CREATE TABLE IF NOT EXISTS saved_posts (
  user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  post_id  INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  saved_at INTEGER NOT NULL,
  PRIMARY KEY (user_id, post_id)
);

CREATE TABLE IF NOT EXISTS donation_requests (
  id           INTEGER PRIMARY KEY,
  post_id      INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
  requester_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  notes        TEXT,
  status       TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected')),
  created_at   INTEGER NOT NULL,
  UNIQUE (post_id, requester_id)
);

CREATE TABLE IF NOT EXISTS conversations (
  id         INTEGER PRIMARY KEY,
  user1_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user2_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  updated_at INTEGER NOT NULL,
  UNIQUE (user1_id, user2_id),
  CHECK (user1_id < user2_id)
);

CREATE TABLE IF NOT EXISTS messages (
  id              INTEGER PRIMARY KEY,
  conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
  sender_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  body            TEXT NOT NULL,
  created_at      INTEGER NOT NULL,
  read_at         INTEGER
);
CREATE INDEX IF NOT EXISTS messages_conversation ON messages(conversation_id, id);

CREATE TABLE IF NOT EXISTS reports (
  id               INTEGER PRIMARY KEY,
  content_type     TEXT NOT NULL CHECK (content_type IN ('post','comment','user')),
  content_id       INTEGER NOT NULL,
  reporter_id      INTEGER REFERENCES users(id) ON DELETE SET NULL,
  reported_user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
  type             TEXT NOT NULL CHECK (type IN ('spam','inappropriate','harassment','other')),
  reason           TEXT,
  status           TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','resolved','dismissed')),
  action_taken     TEXT,
  resolved_by      INTEGER REFERENCES users(id) ON DELETE SET NULL,
  resolved_at      INTEGER,
  created_at       INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS contact_messages (
  id         INTEGER PRIMARY KEY,
  name       TEXT NOT NULL,
  email      TEXT NOT NULL,
  subject    TEXT NOT NULL,
  message    TEXT NOT NULL,
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sessions (
  sid     TEXT PRIMARY KEY,
  data    TEXT NOT NULL,
  expires INTEGER NOT NULL
);
`);

const DEFAULT_SETTINGS = {
  site_name: 'Helpie',
  site_description: 'Connect and share medical supplies with those in need.',
  contact_email: 'support@helpie.example',
  posts_per_page: '12',
  enable_registration: '1',
  maintenance_mode: '0',
};

const DEFAULT_CATEGORIES = [
  ['Mobility aids', 'Wheelchairs, walkers, crutches and canes'],
  ['Medical equipment', 'Monitors, nebulisers, oxygen and other devices'],
  ['Wound care', 'Bandages, dressings and first-aid supplies'],
  ['Personal care', 'Hygiene, incontinence and daily-living supplies'],
  ['Diabetes care', 'Glucose meters, test strips and related supplies'],
  ['Protective equipment', 'Masks, gloves and sanitiser'],
  ['Other', 'Anything that does not fit elsewhere'],
];

const insertSetting = db.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
for (const [k, v] of Object.entries(DEFAULT_SETTINGS)) insertSetting.run(k, v);

if (db.prepare('SELECT COUNT(*) AS n FROM categories').get().n === 0) {
  const insertCategory = db.prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
  for (const c of DEFAULT_CATEGORIES) insertCategory.run(...c);
}

function getSettings() {
  const out = { ...DEFAULT_SETTINGS };
  for (const row of db.prepare('SELECT key, value FROM settings').all()) out[row.key] = row.value;
  return out;
}

function setSetting(key, value) {
  db.prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
    .run(key, String(value));
}

module.exports = { db, getSettings, setSetting, DEFAULT_SETTINGS };
