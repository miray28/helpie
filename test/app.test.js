'use strict';
// End-to-end smoke tests: boots the real app against a throwaway database and
// drives it over HTTP and WebSocket.
const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');

const dataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'helpie-test-'));
process.env.DATA_DIR = dataDir;
if (!process.env.HELPIE_TEST_KEEP_DB) delete process.env.DATABASE_URL;

const { app } = require('../src/app');
const { db } = require('../src/db');

let server;
let base;

before(async () => {
  await db.ready;
  server = http.createServer(app);
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  base = `http://127.0.0.1:${server.address().port}`;
});

after(async () => {
  server.closeAllConnections();
  await new Promise((resolve) => server.close(resolve));
  await db.close();
  fs.rmSync(dataDir, { recursive: true, force: true });
});

// A tiny cookie-aware client.
function client() {
  let cookie = '';
  let csrf = '';
  async function request(method, url, { form, json, multipart, headers = {} } = {}) {
    const init = { method, redirect: 'manual', headers: { cookie, ...headers } };
    if (form) {
      init.body = new URLSearchParams({ _csrf: csrf, ...form });
      init.headers['content-type'] = 'application/x-www-form-urlencoded';
    } else if (json) {
      init.body = JSON.stringify(json);
      init.headers['content-type'] = 'application/json';
      init.headers['x-csrf-token'] = csrf;
      init.headers.accept = 'application/json';
    } else if (multipart) {
      const data = new FormData();
      data.set('_csrf', csrf);
      for (const [k, v] of Object.entries(multipart)) data.set(k, v);
      init.body = data;
    }
    const res = await fetch(base + url, init);
    const setCookie = res.headers.getSetCookie();
    if (setCookie.length) cookie = setCookie.map((c) => c.split(';')[0]).join('; ');
    const text = await res.text();
    const match = text.match(/name="csrf-token" content="([a-f0-9]+)"/);
    if (match) csrf = match[1];
    return { status: res.status, location: res.headers.get('location'), text, headers: res.headers };
  }
  return {
    get: (url, opts) => request('GET', url, opts),
    post: (url, opts) => request('POST', url, opts),
    get cookie() { return cookie; },
    set csrf(value) { csrf = value; },
  };
}

async function register(c, username, role = 'donor') {
  await c.get('/register');
  const res = await c.post('/register', {
    form: { username, email: `${username}@example.com`, password: 'passw0rd!', confirm_password: 'passw0rd!', role, terms: '1' },
  });
  assert.equal(res.status, 302, res.text);
  assert.equal(res.location, '/feed');
  await c.get('/feed');
  return (await db.get('SELECT id FROM users WHERE username = ?', [username])).id;
}

const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

test('public pages render and private pages require sign-in', async () => {
  const c = client();
  for (const url of ['/', '/about', '/contact', '/terms', '/privacy', '/login', '/register']) {
    assert.equal((await c.get(url)).status, 200, url);
  }
  const res = await c.get('/feed');
  assert.equal(res.status, 302);
  assert.equal(res.location, '/login');
  assert.equal((await c.get('/nope')).status, 404);
  assert.equal((await c.get('/admin')).status, 302);
});

test('forms without a valid CSRF token are rejected', async () => {
  const c = client();
  await c.get('/login');
  c.csrf = 'wrong';
  const res = await c.post('/login', { form: { identifier: 'x', password: 'y' } });
  assert.equal(res.status, 403);
});

test('registration validates input and login works', async () => {
  const c = client();
  await c.get('/register');
  const weak = await c.post('/register', {
    form: { username: 'weak', email: 'weak@example.com', password: 'short', confirm_password: 'short', role: 'donor', terms: '1' },
  });
  assert.equal(weak.status, 400);

  await register(c, 'alice');
  await c.post('/logout', { form: {} });
  assert.equal((await c.get('/feed')).status, 302);

  await c.get('/login');
  const bad = await c.post('/login', { form: { identifier: 'alice', password: 'wrong-password' } });
  assert.equal(bad.status, 401);
  const good = await c.post('/login', { form: { identifier: 'ALICE@example.com', password: 'passw0rd!' } });
  assert.equal(good.status, 302);
  assert.equal(good.location, '/feed');
});

test('donation flow: post, upload, comment, save, request, approve', async () => {
  const donor = client();
  const recipient = client();
  const donorId = await register(donor, 'donor1');
  const recipientId = await register(recipient, 'recip1', 'recipient');
  const categoryId = (await db.get('SELECT id FROM categories LIMIT 1')).id;

  // Non-image uploads are refused.
  await donor.get('/posts/new');
  const fake = await donor.post('/posts', {
    multipart: { type: 'donation', title: 'Bad upload', content: 'This is not really an image.', category_id: String(categoryId), image: new Blob(['<?php echo 1; ?>'], { type: 'image/png' }) },
  });
  assert.equal(fake.status, 400);
  assert.match(fake.text, /Images must be JPEG/);

  const created = await donor.post('/posts', {
    multipart: {
      type: 'donation', title: 'Spare wheelchair', content: 'A wheelchair in good condition.', category_id: String(categoryId),
      condition_state: 'good', quantity: '1', location: 'Leeds', image: new Blob([PNG], { type: 'image/png' }),
    },
  });
  assert.equal(created.status, 302, created.text);
  const postUrl = created.location;
  const postId = Number(postUrl.split('/').pop());
  const stored = await db.get('SELECT * FROM posts WHERE id = ?', [postId]);
  assert.match(stored.image, /^posts\/[a-f0-9]{32}\.png$/);
  const image = await donor.get(`/uploads/${stored.image}`);
  assert.equal(image.status, 200);
  assert.equal(image.headers.get('content-type'), 'image/png');
  assert.match(image.headers.get('cache-control'), /immutable/);
  assert.equal((await donor.get('/uploads/posts/..%2f..%2fpackage.json')).status, 404);

  // Feed shows it; search is case-insensitive and literal (no wildcard injection).
  assert.match((await recipient.get('/feed?search=WHEELchair')).text, /Spare wheelchair/);
  assert.doesNotMatch((await recipient.get('/feed?search=%25zzz')).text, /Spare wheelchair/);

  // Markup in user content is escaped.
  await recipient.get(postUrl);
  await recipient.post(`${postUrl}/comments`, { form: { content: '<script>alert(1)</script>' } });
  const page = await recipient.get(postUrl);
  assert.doesNotMatch(page.text, /<script>alert\(1\)<\/script>/);
  assert.match(page.text, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);

  // Save toggles.
  const saved = await recipient.post(`${postUrl}/save`, { json: {} });
  assert.deepEqual(JSON.parse(saved.text), { success: true, is_saved: true, save_count: 1 });
  assert.match((await recipient.get('/saved')).text, /Spare wheelchair/);

  // Only the owner can edit or delete.
  assert.equal((await recipient.get(`${postUrl}/edit`)).status, 403);
  assert.equal((await recipient.post(`${postUrl}/delete`, { form: {} })).status, 403);

  // Owners cannot request their own donation; recipients can, once.
  await donor.get(postUrl);
  await donor.post(`${postUrl}/request`, { form: { notes: 'mine' } });
  await recipient.post(`${postUrl}/request`, { form: { notes: 'I need this' } });
  await recipient.post(`${postUrl}/request`, { form: { notes: 'again' } });
  const requests = await db.all('SELECT * FROM donation_requests WHERE post_id = ?', [postId]);
  assert.equal(requests.length, 1);
  assert.equal(requests[0].requester_id, recipientId);
  const requestStatus = async () => (await db.get('SELECT status FROM donation_requests WHERE id = ?', [requests[0].id])).status;

  // The requester cannot approve their own request; the donor can.
  await recipient.post(`/requests/${requests[0].id}/status`, { form: { status: 'approved' } });
  assert.equal(await requestStatus(), 'pending');
  assert.match((await donor.get('/requests')).text, /I need this/);
  assert.match((await donor.get('/feed')).text, /Requests <span class="count">1<\/span>/);
  await donor.post(`/requests/${requests[0].id}/status`, { form: { status: 'approved' } });
  assert.equal(await requestStatus(), 'approved');
  assert.equal((await db.get('SELECT status FROM posts WHERE id = ?', [postId])).status, 'pending');

  for (const url of ['/activity', '/my-posts', '/members', '/members?sort=donations&search=don', '/profile', `/users/${donorId}`, '/settings', '/profile/edit', '/messages']) {
    assert.equal((await recipient.get(url)).status, 200, url);
  }

  // Profile edit with an avatar upload.
  await recipient.get('/profile/edit');
  const edited = await recipient.post('/profile/edit', {
    multipart: { username: 'recip1', about: 'Hello there', profile_image: new Blob([PNG], { type: 'image/png' }) },
  });
  assert.equal(edited.status, 302, edited.text);
  assert.match((await db.get('SELECT profile_image FROM users WHERE id = ?', [recipientId])).profile_image, /^profiles\//);

  // Deleting the post removes its image.
  await donor.get(postUrl);
  assert.equal((await donor.post(`${postUrl}/delete`, { form: {} })).status, 302);
  assert.equal((await donor.get(`/uploads/${stored.image}`)).status, 404);
});

test('chat: send, fetch updates, unread badge and read state', async () => {
  const a = client();
  const b = client();
  const aId = await register(a, 'chat_a');
  const bId = await register(b, 'chat_b');

  await a.get(`/messages/${bId}`);
  const sent = JSON.parse((await a.post(`/messages/${bId}`, { json: { message: 'hello there' } })).text);
  assert.equal(sent.success, true);
  assert.equal(sent.message.body, 'hello there');
  assert.equal((await a.post(`/messages/${aId}`, { json: { message: 'to myself' } })).status, 400);
  assert.equal((await a.post(`/messages/${bId}`, { json: { message: '   ' } })).status, 400);

  // The recipient sees an unread badge, then the message, then no badge.
  assert.match((await b.get('/feed')).text, /Messages <span class="count">1<\/span>/);
  const updates = JSON.parse((await b.get(`/messages/${aId}/updates?after=0`, { headers: { accept: 'application/json' } })).text);
  assert.equal(updates.messages.length, 1);
  assert.equal(updates.messages[0].sender_id, aId);
  assert.doesNotMatch((await b.get('/feed')).text, /Messages <span class="count">/);
  assert.match((await b.get(`/messages/${aId}`)).text, /hello there/);

  // Only newer messages come back for a given cursor.
  await b.post(`/messages/${aId}`, { json: { message: 'hi back' } });
  const newer = JSON.parse((await a.get(`/messages/${bId}/updates?after=${sent.message.id}`)).text);
  assert.deepEqual(newer.messages.map((m) => m.body), ['hi back']);

  // A third member cannot read the conversation.
  const c = client();
  await register(c, 'chat_c');
  const snoop = JSON.parse((await c.get(`/messages/${aId}/updates?after=0`)).text);
  assert.deepEqual(snoop.messages, []);
});

test('admin area: access control, reports, blocking and settings', async () => {
  const admin = client();
  const user = client();
  const adminId = await register(admin, 'boss');
  const userId = await register(user, 'troll');
  assert.equal((await admin.get('/admin')).status, 403);
  await db.run("UPDATE users SET role = 'admin' WHERE id = ?", [adminId]);

  for (const url of ['/admin', '/admin/users', '/admin/users?search=tro&role=donor', '/admin/posts', '/admin/posts?search=x', '/admin/reports', '/admin/categories', '/admin/settings']) {
    assert.equal((await admin.get(url)).status, 200, url);
    assert.equal((await user.get(url)).status, 403, url);
  }

  // The user posts, the admin (as a member) reports it, then removes it.
  const categoryId = (await db.get('SELECT id FROM categories LIMIT 1')).id;
  await user.get('/posts/new');
  const created = await user.post('/posts', { multipart: { type: 'request', title: 'Buy cheap pills', content: 'Totally legitimate offer, click here.', category_id: String(categoryId) } });
  const postId = Number(created.location.split('/').pop());
  assert.match((await admin.get('/admin')).text, /Buy cheap pills/);
  await admin.get(`/report?type=post&id=${postId}`);
  await admin.post('/report', { form: { content_type: 'post', content_id: String(postId), type: 'spam', reason: 'Obvious spam' } });
  const report = await db.get('SELECT * FROM reports WHERE content_id = ?', [postId]);
  assert.equal(report.reported_user_id, userId);
  assert.match((await admin.get('/admin/reports')).text, /Obvious spam/);
  await admin.post(`/admin/reports/${report.id}`, { form: { action: 'delete_content' } });
  assert.equal((await db.get('SELECT COUNT(*) AS n FROM posts WHERE id = ?', [postId])).n, 0);
  assert.equal((await db.get('SELECT status FROM reports WHERE id = ?', [report.id])).status, 'resolved');

  // Duplicate category names are refused, whatever the letter case.
  await admin.get('/admin/categories');
  await admin.post('/admin/categories', { form: { action: 'add', name: 'WOUND CARE' } });
  assert.equal((await db.get("SELECT COUNT(*) AS n FROM categories WHERE lower(name) = 'wound care'")).n, 1);

  // Blocking signs the member out and prevents signing back in.
  await admin.post(`/admin/users/${userId}`, { form: { action: 'block' } });
  assert.equal((await user.get('/feed')).status, 302);
  await user.get('/login');
  assert.equal((await user.post('/login', { form: { identifier: 'troll', password: 'passw0rd!' } })).status, 403);

  // Admins cannot demote themselves.
  await admin.post(`/admin/users/${adminId}`, { form: { action: 'role', role: 'donor' } });
  assert.equal((await db.get('SELECT role FROM users WHERE id = ?', [adminId])).role, 'admin');

  // Maintenance mode locks out everyone except admins.
  await admin.get('/admin/settings');
  await admin.post('/admin/settings', { form: { site_name: 'Helpie', posts_per_page: '12', maintenance_mode: '1' } });
  assert.equal((await client().get('/')).status, 503);
  assert.equal((await admin.get('/feed')).status, 200);
  await admin.post('/admin/settings', { form: { site_name: 'Helpie', posts_per_page: '12', enable_registration: '1' } });
  assert.equal((await client().get('/')).status, 200);
});

test('sign-in attempts are rate limited', async () => {
  const c = client();
  await c.get('/login');
  let last;
  for (let i = 0; i < 12; i += 1) last = await c.post('/login', { form: { identifier: 'nobody', password: 'wrong-password' } });
  assert.equal(last.status, 429);
});

test('health check reports database connectivity', async () => {
  const res = await client().get('/healthz');
  assert.equal(res.status, 200);
  assert.deepEqual(JSON.parse(res.text), { ok: true });
});
