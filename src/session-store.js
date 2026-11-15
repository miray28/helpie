'use strict';
const session = require('express-session');
const { db } = require('./db');

const DAY = 24 * 3600 * 1000;

// express-session store backed by the app's Postgres database, so sessions
// survive across serverless function instances.
class PostgresStore extends session.Store {
  get(sid, cb) {
    db.get('SELECT data FROM sessions WHERE sid = ? AND expires > ?', [sid, Date.now()])
      .then((row) => cb(null, row ? JSON.parse(row.data) : null), cb);
  }

  set(sid, sess, cb = () => {}) {
    const expires = sess.cookie && sess.cookie.expires ? new Date(sess.cookie.expires).getTime() : Date.now() + DAY;
    db.run(
      `INSERT INTO sessions (sid, user_id, data, expires) VALUES (?, ?, ?, ?)
       ON CONFLICT (sid) DO UPDATE SET user_id = excluded.user_id, data = excluded.data, expires = excluded.expires`,
      [sid, sess.userId || null, JSON.stringify(sess), expires])
      .then(() => {
        // Occasionally sweep out expired sessions and rate-limit counters.
        if (Math.random() < 0.02) {
          db.run('DELETE FROM sessions WHERE expires <= ?', [Date.now()]).catch(() => {});
          db.run('DELETE FROM rate_limits WHERE expires <= ?', [Date.now()]).catch(() => {});
        }
        cb(null);
      }, cb);
  }

  touch(sid, sess, cb = () => {}) {
    const expires = sess.cookie && sess.cookie.expires ? new Date(sess.cookie.expires).getTime() : Date.now() + DAY;
    db.run('UPDATE sessions SET expires = ? WHERE sid = ?', [expires, sid]).then(() => cb(null), cb);
  }

  destroy(sid, cb = () => {}) {
    db.run('DELETE FROM sessions WHERE sid = ?', [sid]).then(() => cb(null), cb);
  }
}

module.exports = PostgresStore;
