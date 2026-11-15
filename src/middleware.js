'use strict';
const crypto = require('crypto');
const { db, getSettings } = require('./db');
const helpers = require('./helpers');

function flash(req, type, message) {
  req.session.flash = req.session.flash || [];
  req.session.flash.push({ type, message });
}

// Loads the signed-in user and everything the templates need on every page.
async function locals(req, res, next) {
  const [settings, user] = await Promise.all([
    getSettings(),
    req.session.userId
      ? db.get(`
          SELECT u.*,
            (SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
              WHERE (c.user1_id = u.id OR c.user2_id = u.id) AND m.sender_id != u.id AND m.read_at IS NULL) AS unread_messages,
            (SELECT COUNT(*) FROM donation_requests dr JOIN posts p ON p.id = dr.post_id
              WHERE p.user_id = u.id AND dr.status = 'pending') AS pending_requests
          FROM users u WHERE u.id = ?`, [req.session.userId])
      : null,
  ]);

  req.user = user || null;
  if (req.session.userId && (!user || user.blocked)) {
    delete req.session.userId;
    if (user) flash(req, 'error', 'Your account has been suspended.');
    req.user = null;
  }

  if (!req.session.csrfToken) req.session.csrfToken = crypto.randomBytes(32).toString('hex');

  res.locals.settings = settings;
  res.locals.currentUser = req.user;
  res.locals.csrfToken = req.session.csrfToken;
  res.locals.path = req.path;
  res.locals.query = req.query;
  res.locals.h = helpers;
  res.locals.title = '';
  res.locals.flash = req.session.flash || [];
  delete req.session.flash;
  res.locals.badges = req.user
    ? { messages: req.user.unread_messages, requests: req.user.pending_requests }
    : { messages: 0, requests: 0 };
  next();
}

function tokensMatch(a, b) {
  if (typeof a !== 'string' || typeof b !== 'string' || a.length !== b.length) return false;
  return crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));
}

// Routes that accept multipart uploads. multer parses those bodies inside the
// route, so the CSRF check is deferred to csrfAfterUpload there. Multipart
// requests to any other path are rejected outright.
const UPLOAD_PATHS = /^\/(posts|posts\/\d+\/edit|profile\/edit)\/?$/;

function csrfError() {
  const err = new Error('Your session expired or the form was invalid. Please go back and try again.');
  err.status = 403;
  return err;
}

const submittedToken = (req) => (req.body && req.body._csrf) || req.get('x-csrf-token');

// CSRF check for every state-changing request.
function csrf(req, res, next) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) return next();
  if (req.is('multipart/form-data')) {
    return UPLOAD_PATHS.test(req.path) ? next() : next(csrfError());
  }
  return tokensMatch(submittedToken(req), req.session.csrfToken) ? next() : next(csrfError());
}

// Runs after multer on upload routes.
function csrfAfterUpload(req, res, next) {
  return tokensMatch(submittedToken(req), req.session.csrfToken) ? next() : next(csrfError());
}

function wantsJson(req) {
  return req.xhr || (req.get('accept') || '').includes('application/json');
}

function requireLogin(req, res, next) {
  if (req.user) return next();
  if (wantsJson(req)) return res.status(401).json({ success: false, message: 'Please sign in.' });
  if (req.method === 'GET') req.session.returnTo = req.originalUrl;
  flash(req, 'error', 'Please sign in to continue.');
  res.redirect('/login');
}

function requireAdmin(req, res, next) {
  if (req.user && req.user.role === 'admin') return next();
  if (!req.user) return requireLogin(req, res, next);
  const err = new Error('You need administrator access to view this page.');
  err.status = 403;
  next(err);
}

function maintenance(req, res, next) {
  if (res.locals.settings.maintenance_mode !== '1') return next();
  if (req.user && req.user.role === 'admin') return next();
  if (['/login', '/logout', '/healthz'].includes(req.path)) return next();
  res.status(503).render('error', {
    title: 'Back soon',
    status: 503,
    message: 'Helpie is down for maintenance. Please check back shortly.',
  });
}

// The visitor's address. On Netlify the real client IP arrives in a header
// set by the platform (it cannot be spoofed by the visitor).
function clientIp(req) {
  return req.get('x-nf-client-connection-ip') || req.ip || 'unknown';
}

// Fixed-window rate limiter stored in the database, so the limit holds across
// serverless function instances.
function rateLimit({ name, windowMs, max, message }) {
  return async (req, res, next) => {
    const now = Date.now();
    const row = await db.get(`
      INSERT INTO rate_limits (key, count, expires) VALUES (@key, 1, @expires)
      ON CONFLICT (key) DO UPDATE SET
        count = CASE WHEN rate_limits.expires <= @now THEN 1 ELSE rate_limits.count + 1 END,
        expires = CASE WHEN rate_limits.expires <= @now THEN @expires ELSE rate_limits.expires END
      RETURNING count`, { key: `${name}:${clientIp(req)}`, expires: now + windowMs, now });
    if (row.count <= max) return next();
    if (wantsJson(req)) return res.status(429).json({ success: false, message });
    res.status(429).render('error', { title: 'Slow down', status: 429, message });
  };
}

module.exports = {
  flash, locals, csrf, csrfAfterUpload, requireLogin, requireAdmin, maintenance, rateLimit, wantsJson,
};
