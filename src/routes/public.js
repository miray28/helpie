'use strict';
const express = require('express');
const { db } = require('../db');
const { flash, rateLimit } = require('../middleware');
const { str, isEmail } = require('../helpers');

const router = express.Router();

router.get('/', (req, res) => {
  if (req.user) return res.redirect('/feed');
  const stats = db.prepare(`
    SELECT (SELECT COUNT(*) FROM posts WHERE type = 'donation') AS donations,
           (SELECT COUNT(*) FROM posts WHERE type = 'request') AS requests,
           (SELECT COUNT(*) FROM users WHERE blocked = 0) AS members`).get();
  res.render('landing', { title: '', stats });
});

router.get('/about', (req, res) => res.render('about', { title: 'About us' }));
router.get('/terms', (req, res) => res.render('terms', { title: 'Terms of service' }));
router.get('/privacy', (req, res) => res.render('privacy', { title: 'Privacy policy' }));

router.get('/contact', (req, res) => res.render('contact', { title: 'Contact us', form: {} }));

const contactLimit = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 5,
  message: 'You have sent several messages already. Please try again later.',
});

router.post('/contact', contactLimit, (req, res) => {
  const form = {
    name: str(req.body.name, 100),
    email: str(req.body.email, 254),
    subject: str(req.body.subject, 150),
    message: str(req.body.message, 5000),
  };
  let error = null;
  if (!form.name || !form.email || !form.subject || !form.message) error = 'Please fill in every field.';
  else if (!isEmail(form.email)) error = 'Please enter a valid email address.';
  if (error) {
    res.locals.flash.push({ type: 'error', message: error });
    return res.status(400).render('contact', { title: 'Contact us', form });
  }
  db.prepare('INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?)')
    .run(form.name, form.email, form.subject, form.message, Date.now());
  flash(req, 'success', 'Thanks for your message. We will get back to you soon.');
  res.redirect('/contact');
});

module.exports = router;
