'use strict';
// Chat client. New messages are fetched from /messages/:id/updates on a short poll.
(function () {
  const log = document.querySelector('[data-chat-log]');
  const form = document.querySelector('[data-chat-form]');
  if (!log || !form) return;

  const me = Number(log.dataset.me);
  const partner = Number(log.dataset.partner);
  const input = form.querySelector('input');
  const button = form.querySelector('button');
  const status = document.querySelector('[data-chat-status]');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const seen = new Set();
  let lastId = Number(log.dataset.last) || 0;
  let fetching = false;
  let again = false;

  const formatTime = (ms) => new Date(Number(ms)).toLocaleString([], { hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' });
  log.querySelectorAll('time[data-time]').forEach((el) => { el.textContent = formatTime(el.dataset.time); });
  const scrollDown = () => { log.scrollTop = log.scrollHeight; };
  scrollDown();

  function addMessage(message) {
    if (seen.has(message.id) || message.id <= Number(log.dataset.last)) return;
    seen.add(message.id);
    lastId = Math.max(lastId, message.id);
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

  async function fetchUpdates() {
    if (fetching) { again = true; return; }
    fetching = true;
    try {
      const res = await fetch(`/messages/${partner}/updates?after=${lastId}`, { headers: { Accept: 'application/json' } });
      if (res.status === 401) { location.reload(); return; }
      const data = await res.json();
      if (data.success) data.messages.forEach(addMessage);
    } catch (err) {
      /* offline: the next poll will catch up */
    } finally {
      fetching = false;
      if (again) { again = false; fetchUpdates(); }
    }
  }

  // Poll for new messages: quickly while the tab is visible, slowly otherwise.
  (function poll() {
    setTimeout(() => { fetchUpdates().finally(poll); }, document.hidden ? 30000 : 3000);
  })();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) fetchUpdates(); });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const body = input.value.trim();
    if (!body) return;
    input.value = '';
    button.disabled = true;
    try {
      const res = await fetch(`/messages/${partner}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
        body: JSON.stringify({ message: body }),
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.message);
      addMessage(data.message);
      status.textContent = '';
    } catch (err) {
      input.value = body;
      status.textContent = err.message && !/fetch|JSON/i.test(err.message) ? err.message : 'Message not sent. Check your connection and try again.';
    } finally {
      button.disabled = false;
      input.focus();
    }
  });
})();
