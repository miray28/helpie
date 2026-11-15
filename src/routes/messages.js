'use strict';
const express = require('express');
const { db } = require('../db');
const { requireLogin } = require('../middleware');
const { toId } = require('../helpers');
const chat = require('../chat');

const router = express.Router();

function conversationList(me) {
  return db.prepare(`
    SELECT c.id, c.updated_at, u.id AS user_id, u.username, u.profile_image,
      (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message,
      (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != @me AND read_at IS NULL) AS unread
    FROM conversations c
    JOIN users u ON u.id = CASE WHEN c.user1_id = @me THEN c.user2_id ELSE c.user1_id END
    WHERE (c.user1_id = @me OR c.user2_id = @me)
      AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id)
    ORDER BY c.updated_at DESC`).all({ me });
}

router.get('/messages', requireLogin, (req, res) => {
  res.render('messages', { title: 'Messages', conversations: conversationList(req.user.id), partner: null, messages: [] });
});

router.get('/messages/:userId', requireLogin, (req, res, next) => {
  const partnerId = toId(req.params.userId);
  const partner = partnerId && partnerId !== req.user.id
    ? db.prepare('SELECT id, username, profile_image, role FROM users WHERE id = ? AND blocked = 0').get(partnerId)
    : null;
  if (!partner) {
    const err = new Error('That member is not available to message.');
    err.status = 404;
    return next(err);
  }
  const messages = chat.history(req.user.id, partner.id);
  chat.markRead(req.user.id, partner.id);
  res.locals.badges.messages = db.prepare(`
    SELECT COUNT(*) AS n FROM messages m JOIN conversations c ON c.id = m.conversation_id
    WHERE (c.user1_id = @me OR c.user2_id = @me) AND m.sender_id != @me AND m.read_at IS NULL`).get({ me: req.user.id }).n;
  res.render('messages', { title: `Chat with ${partner.username}`, conversations: conversationList(req.user.id), partner, messages });
});

// HTTP fallback used when the WebSocket connection is unavailable.
router.post('/messages/:userId', requireLogin, (req, res) => {
  try {
    const message = chat.send(req.user.id, toId(req.params.userId), req.body.message);
    res.json({ success: true, message });
  } catch (err) {
    res.status(400).json({ success: false, message: err.message });
  }
});

router.post('/messages/:userId/read', requireLogin, (req, res) => {
  chat.markRead(req.user.id, toId(req.params.userId));
  res.json({ success: true });
});

module.exports = router;
