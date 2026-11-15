'use strict';
const express = require('express');
const bcrypt = require('bcryptjs');
const { db } = require('../db');
const { flash, rateLimit } = require('../middleware');
const { str, isEmail, safeRedirect } = require('../helpers');
const { newTopic } = require('../chat');

const router = express.Router();

const DUMMY_HASH = bcrypt.hashSync('not-a-real-password', 10);

const loginLimit = rateLimit({
  name: 'login',
  windowMs: 15 * 60 * 1000,
  max: 10,
  message: 'Too many sign-in attempts. Please wait 15 minutes and try again.',
});

const registerLimit = rateLimit({
  name: 'register',
  windowMs: 60 * 60 * 1000,
  max: 30,
  message: 'Too many sign-up attempts from this network. Please try again later.',
});

function validatePassword(password) {
  if (typeof password !== 'string' || password.length < 8) return 'Password must be at least 8 characters.';
  if (password.length > 200) return 'Password is too long.';
  if (!/[A-Za-z]/.test(password) || !/\d/.test(password)) return 'Password must contain at least one letter and one number.';
  return null;
}

// Regenerate the session on sign-in so a pre-login session id can't be reused.
function signIn(req, userId) {
  const returnTo = req.session.returnTo;
  return new Promise((resolve, reject) => {
    req.session.regenerate((err) => {
      if (err) return reject(err);
      req.session.userId = userId;
      db.run('UPDATE users SET last_login = ? WHERE id = ?', [Date.now(), userId]).then(() => resolve(returnTo), reject);
    });
  });
}

router.get('/login', (req, res) => {
  if (req.user) return res.redirect(req.user.role === 'admin' ? '/admin' : '/feed');
  res.render('login', { title: 'Sign in', identifier: '' });
});

router.post('/login', loginLimit, async (req, res) => {
  const identifier = str(req.body.identifier, 254);
  const password = typeof req.body.password === 'string' ? req.body.password : '';
  const user = identifier
    ? await db.get('SELECT * FROM users WHERE lower(username) = lower(@id) OR lower(email) = lower(@id)', { id: identifier })
    : null;

  // Always run a hash comparison so response time doesn't reveal whether the account exists.
  const matches = await bcrypt.compare(password, user ? user.password_hash : DUMMY_HASH);

  if (!user || !matches) {
    res.locals.flash.push({ type: 'error', message: 'Incorrect username/email or password.' });
    return res.status(401).render('login', { title: 'Sign in', identifier });
  }
  if (user.blocked) {
    res.locals.flash.push({ type: 'error', message: 'This account has been suspended. Contact support if you think this is a mistake.' });
    return res.status(403).render('login', { title: 'Sign in', identifier });
  }
  const returnTo = await signIn(req, user.id);
  res.redirect(safeRedirect(returnTo, user.role === 'admin' ? '/admin' : '/feed'));
});

router.get('/register', (req, res) => {
  if (req.user) return res.redirect('/feed');
  res.render('register', { title: 'Join Helpie', form: { role: req.query.role === 'recipient' ? 'recipient' : 'donor' } });
});

router.post('/register', registerLimit, async (req, res) => {
  if (res.locals.settings.enable_registration !== '1') {
    flash(req, 'error', 'New registrations are currently closed.');
    return res.redirect('/register');
  }
  const form = {
    username: str(req.body.username, 60),
    email: str(req.body.email, 254).toLowerCase(),
    role: str(req.body.role, 20),
  };
  const password = typeof req.body.password === 'string' ? req.body.password : '';

  let error = null;
  if (!/^[A-Za-z0-9_.-]{3,30}$/.test(form.username)) error = 'Username must be 3–30 characters: letters, numbers, dots, dashes or underscores.';
  else if (!isEmail(form.email)) error = 'Please enter a valid email address.';
  else if (!['donor', 'recipient'].includes(form.role)) error = 'Please choose whether you are donating or looking for supplies.';
  else if (validatePassword(password)) error = validatePassword(password);
  else if (password !== req.body.confirm_password) error = 'The two passwords do not match.';
  else if (!req.body.terms) error = 'Please accept the terms of service to continue.';
  else if (await db.get('SELECT 1 FROM users WHERE lower(username) = lower(?)', [form.username])) error = 'That username is already taken.';
  else if (await db.get('SELECT 1 FROM users WHERE lower(email) = lower(?)', [form.email])) error = 'An account with that email already exists.';

  if (error) {
    res.locals.flash.push({ type: 'error', message: error });
    return res.status(400).render('register', { title: 'Join Helpie', form });
  }

  const row = await db.get(
    'INSERT INTO users (username, email, password_hash, role, realtime_topic, created_at) VALUES (?, ?, ?, ?, ?, ?) RETURNING id',
    [form.username, form.email, await bcrypt.hash(password, 11), form.role, newTopic(), Date.now()]);
  await signIn(req, row.id);
  flash(req, 'success', `Welcome to Helpie, ${form.username}!`);
  res.redirect('/feed');
});

router.post('/logout', (req, res, next) => {
  req.session.destroy((err) => {
    if (err) return next(err);
    res.clearCookie('helpie.sid');
    res.redirect('/login');
  });
});

module.exports = router;
module.exports.validatePassword = validatePassword;
