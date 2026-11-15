'use strict';
const crypto = require('crypto');
const { db, getSettings } = require('./db');
const helpers = require('./helpers');

const userById = db.prepare('SELECT * FROM users WHERE id = ?');
const unreadCount = db.prepare(`
  SELECT COUNT(*) AS n FROM messages m
  JOIN conversations c ON c.id = m.conversation_id
  WHERE (c.user1_id = @me OR c.user2_id = @me) AND m.sender_id != @me AND m.read_at IS NULL`);
const pendingRequestCount = db.prepare(`
  SELECT COUNT(*) AS n FROM donation_requests dr
  JOIN posts p ON p.id = dr.post_id
  WHERE p.user_id = ? AND dr.status = 'pending'`);

function flash(req, type, message) {
  req.session.flash = req.session.flash || [];
  req.session.flash.push({ type, message });
}

// Loads the signed-in user and everything the templates need on every page.
function locals(req, res, next) {
  let user = null;
  if (req.session.userId) {
    user = userById.get(req.session.userId) || null;
    if (!user || user.blocked) {
      delete req.session.userId;
      if (user) flash(req, 'error', 'Your account has been suspended.');
      user = null;
    }
  }
  req.user = user;

  if (!req.session.csrfToken) req.session.csrfToken = crypto.randomBytes(32).toString('hex');

  const settings = getSettings();
  res.locals.settings = settings;
  res.locals.currentUser = user;
  res.locals.csrfToken = req.session.csrfToken;
  res.locals.path = req.path;
  res.locals.query = req.query;
  res.locals.h = helpers;
  res.locals.title = '';
  res.locals.flash = req.session.flash || [];
  delete req.session.flash;
  res.locals.badges = user
    ? { messages: unreadCount.get({ me: user.id }).n, requests: pendingRequestCount.get(user.id).n }
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

// CSRF check for every state-changing request.
function csrf(req, res, next) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) return next();
  if (req.is('multipart/form-data')) {
    return UPLOAD_PATHS.test(req.path) ? next() : next(csrfError());
  }
  const token = (req.body && req.body._csrf) || req.get('x-csrf-token');
  return tokensMatch(token, req.session.csrfToken) ? next() : next(csrfError());
}

// Runs after multer on upload routes.
function csrfAfterUpload(req, res, next) {
  const token = (req.body && req.body._csrf) || req.get('x-csrf-token');
  return tokensMatch(token, req.session.csrfToken) ? next() : next(csrfError());
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
  if (['/login', '/logout'].includes(req.path)) return next();
  res.status(503).render('error', {
    title: 'Back soon',
    status: 503,
    message: 'Helpie is down for maintenance. Please check back shortly.',
  });
}

// Small in-memory limiter for sign-in attempts and other abuse-prone forms.
function rateLimit({ windowMs, max, message }) {
  const hits = new Map();
  setInterval(() => {
    const now = Date.now();
    for (const [key, entry] of hits) if (entry.reset <= now) hits.delete(key);
  }, windowMs).unref();

  return (req, res, next) => {
    const key = req.ip;
    const now = Date.now();
    let entry = hits.get(key);
    if (!entry || entry.reset <= now) {
      entry = { count: 0, reset: now + windowMs };
      hits.set(key, entry);
    }
    entry.count += 1;
    if (entry.count > max) {
      if (wantsJson(req)) return res.status(429).json({ success: false, message });
      flash(req, 'error', message);
      return res.redirect(req.get('referer') ? helpers.safeRedirect(new URL(req.get('referer'), 'http://x').pathname, '/') : '/');
    }
    next();
  };
}

module.exports = {
  flash, locals, csrf, csrfAfterUpload, requireLogin, requireAdmin,
  maintenance, rateLimit, wantsJson,
};
