'use strict';
const { db } = require('./db');

// Private messaging. Messages are stored in Postgres and browsers fetch new
// ones with a short poll, which works on serverless hosting where there is no
// long-lived process to hold WebSocket connections.

const pair = (a, b) => (a < b ? [a, b] : [b, a]);

function findConversation(a, b, runner = db) {
  return runner.get('SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?', pair(a, b));
}

// Messages between two members, oldest first. With `afterId` only newer ones.
async function history(me, other, afterId = 0, limit = 200) {
  const conversation = other ? await findConversation(me, other) : null;
  if (!conversation) return [];
  return db.all(`
    SELECT * FROM (
      SELECT id, sender_id, body, created_at FROM messages
      WHERE conversation_id = ? AND id > ? ORDER BY id DESC LIMIT ?
    ) recent ORDER BY id ASC`, [conversation.id, afterId, limit]);
}

async function markRead(me, other) {
  const conversation = other ? await findConversation(me, other) : null;
  if (!conversation) return;
  await db.run('UPDATE messages SET read_at = ? WHERE conversation_id = ? AND sender_id = ? AND read_at IS NULL',
    [Date.now(), conversation.id, other]);
}

async function send(senderId, receiverId, text) {
  const fail = (message) => Object.assign(new Error(message), { userFacing: true });
  const body = typeof text === 'string' ? text.trim().slice(0, 2000) : '';
  if (!body) throw fail('Message cannot be empty.');
  if (!receiverId || receiverId === senderId) throw fail('Invalid recipient.');
  const receiver = await db.get('SELECT id FROM users WHERE id = ? AND blocked = 0', [receiverId]);
  if (!receiver) throw fail('That member is not available.');

  const now = Date.now();
  const message = await db.tx(async (tx) => {
    const [u1, u2] = pair(senderId, receiverId);
    const conversation = await tx.get(`
      INSERT INTO conversations (user1_id, user2_id, updated_at) VALUES (?, ?, ?)
      ON CONFLICT (user1_id, user2_id) DO UPDATE SET updated_at = excluded.updated_at
      RETURNING id`, [u1, u2, now]);
    const row = await tx.get(
      'INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?, ?, ?, ?) RETURNING id',
      [conversation.id, senderId, body, now]);
    return { id: row.id, sender_id: senderId, body, created_at: now };
  });

  return message;
}

module.exports = { send, history, markRead };
