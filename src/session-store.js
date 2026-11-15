'use strict';
const session = require('express-session');
const { db } = require('./db');

const DAY = 24 * 3600 * 1000;

// Minimal express-session store backed by the app's SQLite database.
class SqliteStore extends session.Store {
  constructor() {
    super();
    this.getStmt = db.prepare('SELECT data FROM sessions WHERE sid = ? AND expires > ?');
    this.setStmt = db.prepare(
      'INSERT INTO sessions (sid, data, expires) VALUES (?, ?, ?) ' +
      'ON CONFLICT(sid) DO UPDATE SET data = excluded.data, expires = excluded.expires');
    this.delStmt = db.prepare('DELETE FROM sessions WHERE sid = ?');
    this.pruneStmt = db.prepare('DELETE FROM sessions WHERE expires <= ?');
    this.timer = setInterval(() => this.pruneStmt.run(Date.now()), 3600 * 1000);
    this.timer.unref();
  }

  get(sid, cb) {
    try {
      const row = this.getStmt.get(sid, Date.now());
      cb(null, row ? JSON.parse(row.data) : null);
    } catch (err) { cb(err); }
  }

  set(sid, sess, cb = () => {}) {
    try {
      const expires = sess.cookie && sess.cookie.expires
        ? new Date(sess.cookie.expires).getTime()
        : Date.now() + DAY;
      this.setStmt.run(sid, JSON.stringify(sess), expires);
      cb(null);
    } catch (err) { cb(err); }
  }

  touch(sid, sess, cb) { this.set(sid, sess, cb); }

  destroy(sid, cb = () => {}) {
    try { this.delStmt.run(sid); cb(null); } catch (err) { cb(err); }
  }
}

module.exports = SqliteStore;
