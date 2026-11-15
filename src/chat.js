'use strict';
const crypto = require('crypto');
const { db } = require('./db');
const config = require('./config');

// Private messaging. Messages are stored in Postgres; delivery is "poke and
// fetch": after a message is saved, both participants get a content-free
// broadcast on their personal Supabase Realtime topic and their browser then
// fetches the new messages over the normal authenticated API. Browsers also
// poll slowly, so chat keeps working if Realtime is unavailable.

const pair = (a, b) => (a < b ? [a, b] : [b, a]);
const newTopic = () => `helpie-${crypto.randomBytes(24).toString('hex')}`;

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

// Tells the given Realtime topics that something new is waiting. Never throws:
// the message is already saved and polling will pick it up regardless.
async function poke(topics) {
  if (!config.supabaseUrl || !config.supabaseKey || !topics.length) return;
  try {
    await fetch(`${config.supabaseUrl}/realtime/v1/api/broadcast`, {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        apikey: config.supabaseKey,
        authorization: `Bearer ${config.supabaseKey}`,
      },
      body: JSON.stringify({ messages: topics.map((topic) => ({ topic, event: 'poke', payload: {} })) }),
      signal: AbortSignal.timeout(2500),
    });
  } catch (err) {
    console.error('Realtime poke failed:', err.message);
  }
}

async function send(senderId, receiverId, text) {
  const fail = (message) => Object.assign(new Error(message), { userFacing: true });
  const body = typeof text === 'string' ? text.trim().slice(0, 2000) : '';
  if (!body) throw fail('Message cannot be empty.');
  if (!receiverId || receiverId === senderId) throw fail('Invalid recipient.');
  const receiver = await db.get('SELECT id, realtime_topic FROM users WHERE id = ? AND blocked = 0', [receiverId]);
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

  await poke([receiver.realtime_topic]);
  return message;
}

module.exports = { send, history, markRead, newTopic };
