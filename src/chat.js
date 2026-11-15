'use strict';
const { WebSocketServer } = require('ws');
const { db } = require('./db');

// userId -> Set of open sockets (a member may have several tabs open).
const sockets = new Map();

const pair = (a, b) => (a < b ? [a, b] : [b, a]);

function findConversation(a, b) {
  return db.prepare('SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?').get(...pair(a, b));
}

function history(me, other, limit = 200) {
  const conversation = findConversation(me, other);
  if (!conversation) return [];
  return db.prepare(`
    SELECT * FROM (SELECT id, sender_id, body, created_at FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ?)
    ORDER BY id ASC`).all(conversation.id, limit);
}

function markRead(me, other) {
  const conversation = other ? findConversation(me, other) : null;
  if (!conversation) return;
  db.prepare('UPDATE messages SET read_at = ? WHERE conversation_id = ? AND sender_id = ? AND read_at IS NULL')
    .run(Date.now(), conversation.id, other);
}

function push(userId, payload) {
  const set = sockets.get(userId);
  if (!set) return;
  const data = JSON.stringify(payload);
  for (const ws of set) if (ws.readyState === ws.OPEN) ws.send(data);
}

// Stores a message and delivers it to both participants' open sockets.
function send(senderId, receiverId, text) {
  const body = typeof text === 'string' ? text.trim().slice(0, 2000) : '';
  if (!body) throw new Error('Message cannot be empty.');
  if (!receiverId || receiverId === senderId) throw new Error('Invalid recipient.');
  const receiver = db.prepare('SELECT id FROM users WHERE id = ? AND blocked = 0').get(receiverId);
  if (!receiver) throw new Error('That member is not available.');

  const now = Date.now();
  const message = db.transaction(() => {
    const [u1, u2] = pair(senderId, receiverId);
    db.prepare(`INSERT INTO conversations (user1_id, user2_id, updated_at) VALUES (?, ?, ?)
                ON CONFLICT(user1_id, user2_id) DO UPDATE SET updated_at = excluded.updated_at`).run(u1, u2, now);
    const conversation = findConversation(senderId, receiverId);
    const info = db.prepare('INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?, ?, ?, ?)')
      .run(conversation.id, senderId, body, now);
    return { id: Number(info.lastInsertRowid), sender_id: senderId, receiver_id: receiverId, body, created_at: now };
  })();

  const sender = db.prepare('SELECT username FROM users WHERE id = ?').get(senderId);
  const payload = { type: 'message', message: { ...message, sender_username: sender.username } };
  push(senderId, payload);
  push(receiverId, payload);
  return message;
}

// Attaches the chat WebSocket endpoint (/ws) to the HTTP server. The socket is
// authenticated from the same session cookie as the website, so a client can
// never claim to be someone else.
function attach(server, sessionParser) {
  const wss = new WebSocketServer({ noServer: true, maxPayload: 16 * 1024 });

  server.on('upgrade', (req, socket, head) => {
    const { pathname } = new URL(req.url, 'http://localhost');
    const origin = req.headers.origin;
    const sameOrigin = !origin || new URL(origin).host === req.headers.host;
    if (pathname !== '/ws' || !sameOrigin) return socket.destroy();

    sessionParser(req, {}, () => {
      const userId = req.session && req.session.userId;
      const user = userId ? db.prepare('SELECT id FROM users WHERE id = ? AND blocked = 0').get(userId) : null;
      if (!user) {
        socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
        return socket.destroy();
      }
      wss.handleUpgrade(req, socket, head, (ws) => wss.emit('connection', ws, user.id));
    });
  });

  wss.on('connection', (ws, userId) => {
    if (!sockets.has(userId)) sockets.set(userId, new Set());
    sockets.get(userId).add(ws);
    ws.isAlive = true;
    ws.on('pong', () => { ws.isAlive = true; });

    let recent = [];
    ws.on('message', (raw) => {
      let data;
      try { data = JSON.parse(raw.toString()); } catch { return; }
      try {
        if (data.type === 'message') {
          const now = Date.now();
          recent = recent.filter((t) => now - t < 10000);
          if (recent.length >= 20) throw new Error('You are sending messages too quickly.');
          recent.push(now);
          send(userId, Number(data.to), data.body);
        } else if (data.type === 'read') {
          markRead(userId, Number(data.from));
        }
      } catch (err) {
        ws.send(JSON.stringify({ type: 'error', message: err.message }));
      }
    });

    ws.on('close', () => {
      const set = sockets.get(userId);
      if (!set) return;
      set.delete(ws);
      if (set.size === 0) sockets.delete(userId);
    });
  });

  const heartbeat = setInterval(() => {
    for (const ws of wss.clients) {
      if (!ws.isAlive) { ws.terminate(); continue; }
      ws.isAlive = false;
      ws.ping();
    }
  }, 30000);
  heartbeat.unref();
  wss.on('close', () => clearInterval(heartbeat));

  return wss;
}

module.exports = { attach, send, history, markRead };
