'use strict';
(function () {
  const log = document.querySelector('[data-chat-log]');
  const form = document.querySelector('[data-chat-form]');
  if (!log || !form) return;

  const me = Number(log.dataset.me);
  const partner = Number(log.dataset.partner);
  const input = form.querySelector('input');
  const status = document.querySelector('[data-chat-status]');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const seen = new Set();
  let socket = null;
  let retry = 0;

  const formatTime = (ms) => new Date(Number(ms)).toLocaleString([], { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' });
  log.querySelectorAll('time[data-time]').forEach((el) => { el.textContent = formatTime(el.dataset.time); });
  const scrollDown = () => { log.scrollTop = log.scrollHeight; };
  scrollDown();

  function addMessage(message) {
    if (seen.has(message.id)) return;
    seen.add(message.id);
    const empty = log.querySelector('[data-chat-empty]');
    if (empty) empty.remove();
    const bubble = document.createElement('div');
    bubble.className = 'bubble' + (message.sender_id === me ? ' mine' : '');
    bubble.textContent = message.body;
    const time = document.createElement('time');
    time.textContent = formatTime(message.created_at);
    bubble.appendChild(time);
    log.appendChild(bubble);
    scrollDown();
  }

  function connect() {
    const scheme = location.protocol === 'https:' ? 'wss' : 'ws';
    socket = new WebSocket(`${scheme}://${location.host}/ws`);
    socket.addEventListener('open', () => { retry = 0; status.textContent = 'Connected'; });
    socket.addEventListener('message', (event) => {
      const data = JSON.parse(event.data);
      if (data.type === 'error') { status.textContent = data.message; return; }
      if (data.type !== 'message') return;
      const m = data.message;
      const inThisChat = (m.sender_id === partner && m.receiver_id === me) || (m.sender_id === me && m.receiver_id === partner);
      if (!inThisChat) return;
      addMessage(m);
      if (m.sender_id === partner) socket.send(JSON.stringify({ type: 'read', from: partner }));
    });
    socket.addEventListener('close', () => {
      status.textContent = 'Reconnecting…';
      retry = Math.min(retry + 1, 6);
      setTimeout(connect, retry * 1000);
    });
  }
  connect();

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = input.value.trim();
    if (!body) return;
    input.value = '';
    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.send(JSON.stringify({ type: 'message', to: partner, body }));
      return;
    }
    // Fallback when the live connection is down.
    try {
      const res = await fetch(`/messages/${partner}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
        body: JSON.stringify({ message: body }),
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      addMessage(data.message);
    } catch (err) {
      input.value = body;
      status.textContent = 'Message not sent. Check your connection and try again.';
    }
  });
})();
