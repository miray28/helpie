'use strict';
const express = require('express');
const { db } = require('../db');
const config = require('../config');
const { requireLogin } = require('../middleware');
const { toId } = require('../helpers');
const chat = require('../chat');

const router = express.Router();
router.use('/messages', requireLogin);

function conversationList(me) {
  return db.all(`
    SELECT c.id, c.updated_at, u.id AS user_id, u.username, u.profile_image,
      (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message,
      (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != @me AND read_at IS NULL) AS unread
    FROM conversations c
    JOIN users u ON u.id = CASE WHEN c.user1_id = @me THEN c.user2_id ELSE c.user1_id END
    WHERE (c.user1_id = @me OR c.user2_id = @me)
      AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id)
    ORDER BY c.updated_at DESC`, { me });
}

// What the browser needs to listen for "new message" pokes on Supabase Realtime.
function realtimeConfig(user) {
  if (!config.supabaseUrl || !config.supabaseKey) return null;
  return {
    url: `${config.supabaseUrl.replace(/^http/, 'ws')}/realtime/v1/websocket?apikey=${encodeURIComponent(config.supabaseKey)}&vsn=1.0.0`,
    topic: user.realtime_topic,
  };
}

router.get('/messages', async (req, res) => {
  res.render('messages', {
    title: 'Messages', conversations: await conversationList(req.user.id), partner: null, messages: [], realtime: null,
  });
});

router.get('/messages/:userId', async (req, res, next) => {
  const partnerId = toId(req.params.userId);
  const partner = partnerId && partnerId !== req.user.id
    ? await db.get('SELECT id, username, profile_image, role FROM users WHERE id = ? AND blocked = 0', [partnerId])
    : null;
  if (!partner) {
    const err = new Error('That member is not available to message.');
    err.status = 404;
    return next(err);
  }
  await chat.markRead(req.user.id, partner.id);
  const [messages, conversations, unread] = await Promise.all([
    chat.history(req.user.id, partner.id),
    conversationList(req.user.id),
    db.get(`
      SELECT COUNT(*) AS n FROM messages m JOIN conversations c ON c.id = m.conversation_id
      WHERE (c.user1_id = @me OR c.user2_id = @me) AND m.sender_id != @me AND m.read_at IS NULL`, { me: req.user.id }),
  ]);
  res.locals.badges.messages = unread.n;
  res.render('messages', {
    title: `Chat with ${partner.username}`, conversations, partner, messages, realtime: realtimeConfig(req.user),
  });
});

// New messages in a conversation since `after` (a message id). Opening the
// conversation also marks the partner's messages as read.
router.get('/messages/:userId/updates', async (req, res) => {
  const partnerId = toId(req.params.userId);
  const after = Math.max(0, parseInt(req.query.after, 10) || 0);
  const messages = await chat.history(req.user.id, partnerId, after);
  if (messages.some((m) => m.sender_id === partnerId)) await chat.markRead(req.user.id, partnerId);
  res.set('Cache-Control', 'no-store').json({ success: true, messages });
});

router.post('/messages/:userId', async (req, res) => {
  const message = await chat.send(req.user.id, toId(req.params.userId), req.body.message);
  res.json({ success: true, message });
});

module.exports = router;
