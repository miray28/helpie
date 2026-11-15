'use strict';
const express = require('express');
const bcrypt = require('bcryptjs');
const { db } = require('../db');
const { flash, requireLogin, csrfAfterUpload } = require('../middleware');
const { upload, saveImage, removeImage } = require('../upload');
const { str, toId, isEmail, paginate, likePattern } = require('../helpers');
const { validatePassword } = require('./auth');

const router = express.Router();

const MEMBER_SORTS = {
  newest: 'u.created_at DESC',
  oldest: 'u.created_at ASC',
  username: 'u.username COLLATE NOCASE ASC',
  donations: 'total_donations DESC, u.created_at DESC',
  requests: 'total_requests DESC, u.created_at DESC',
};

router.get('/members', requireLogin, (req, res) => {
  const where = ['u.blocked = 0', 'u.profile_visible = 1'];
  const params = {};
  const search = str(req.query.search, 100);
  if (search) {
    where.push("(u.username LIKE @search ESCAPE '\\' OR u.address LIKE @search ESCAPE '\\')");
    params.search = likePattern(search);
  }
  if (['admin', 'donor', 'recipient'].includes(req.query.role)) { where.push('u.role = @role'); params.role = req.query.role; }
  const clause = `WHERE ${where.join(' AND ')}`;
  const total = db.prepare(`SELECT COUNT(*) AS n FROM users u ${clause}`).get(params).n;
  const paging = paginate(total, req.query.page, 12);
  const orderBy = MEMBER_SORTS[req.query.sort] || MEMBER_SORTS.newest;
  const members = db.prepare(`
    SELECT u.id, u.username, u.role, u.about, u.address, u.profile_image, u.verified, u.created_at,
      (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 'donation') AS total_donations,
      (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 'request') AS total_requests
    FROM users u ${clause} ORDER BY ${orderBy} LIMIT @limit OFFSET @offset`)
    .all({ ...params, limit: paging.perPage, offset: paging.offset });
  res.render('members', { title: 'Members', members, paging });
});

function showProfile(req, res, next) {
  const id = req.params.id ? toId(req.params.id) : req.user.id;
  const isAdmin = req.user.role === 'admin';
  const profile = id ? db.prepare(`
    SELECT u.*,
      (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 'donation') AS total_donations,
      (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND type = 'request') AS total_requests
    FROM users u WHERE u.id = ?`).get(id) : null;
  const isOwn = profile && profile.id === req.user.id;
  if (!profile || (!isOwn && !isAdmin && (profile.blocked || !profile.profile_visible))) {
    const err = new Error('That profile is not available.');
    err.status = 404;
    return next(err);
  }
  const posts = db.prepare(`
    SELECT p.*, c.name AS category_name,
      (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count
    FROM posts p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 12`).all(profile.id);
  res.render('profile', { title: profile.username, profile, posts, isOwn });
}

router.get('/profile', requireLogin, showProfile);
router.get('/users/:id', requireLogin, showProfile);

router.get('/profile/edit', requireLogin, (req, res) => {
  res.render('profile-edit', { title: 'Edit profile', form: req.user });
});

const profileUpload = upload.fields([{ name: 'profile_image', maxCount: 1 }, { name: 'cover_image', maxCount: 1 }]);

router.post('/profile/edit', requireLogin, profileUpload, csrfAfterUpload, (req, res) => {
  const form = {
    username: str(req.body.username, 60),
    about: str(req.body.about, 1000),
    phone: str(req.body.phone, 30),
    address: str(req.body.address, 200),
  };
  let error = null;
  if (!/^[A-Za-z0-9_.-]{3,30}$/.test(form.username)) error = 'Username must be 3–30 characters: letters, numbers, dots, dashes or underscores.';
  else if (db.prepare('SELECT 1 FROM users WHERE username = ? AND id != ?').get(form.username, req.user.id)) error = 'That username is already taken.';
  else if (form.phone && !/^[0-9+() .-]{5,30}$/.test(form.phone)) error = 'Please enter a valid phone number.';
  if (error) {
    res.locals.flash.push({ type: 'error', message: error });
    return res.status(400).render('profile-edit', { title: 'Edit profile', form: { ...req.user, ...form } });
  }

  const files = req.files || {};
  let profileImage = req.user.profile_image;
  let coverImage = req.user.cover_image;
  if (files.profile_image) {
    profileImage = saveImage(files.profile_image[0], 'profiles');
    removeImage(req.user.profile_image);
  }
  if (files.cover_image) {
    coverImage = saveImage(files.cover_image[0], 'profiles');
    removeImage(req.user.cover_image);
  }
  db.prepare('UPDATE users SET username = ?, about = ?, phone = ?, address = ?, profile_image = ?, cover_image = ? WHERE id = ?')
    .run(form.username, form.about || null, form.phone || null, form.address || null, profileImage, coverImage, req.user.id);
  flash(req, 'success', 'Profile updated.');
  res.redirect('/profile');
});

router.get('/settings', requireLogin, (req, res) => res.render('settings', { title: 'Account settings' }));

router.post('/settings', requireLogin, (req, res, next) => {
  const user = req.user;
  const fail = (message) => { flash(req, 'error', message); res.redirect('/settings'); };
  const passwordOk = (value) => typeof value === 'string' && bcrypt.compareSync(value, user.password_hash);

  switch (req.body.action) {
    case 'update_email': {
      const email = str(req.body.email, 254).toLowerCase();
      if (!isEmail(email)) return fail('Please enter a valid email address.');
      if (!passwordOk(req.body.current_password)) return fail('Your current password is incorrect.');
      if (db.prepare('SELECT 1 FROM users WHERE email = ? AND id != ?').get(email, user.id)) return fail('That email is already in use.');
      db.prepare('UPDATE users SET email = ? WHERE id = ?').run(email, user.id);
      flash(req, 'success', 'Email updated.');
      break;
    }
    case 'change_password': {
      if (!passwordOk(req.body.current_password)) return fail('Your current password is incorrect.');
      const problem = validatePassword(req.body.new_password);
      if (problem) return fail(problem);
      if (req.body.new_password !== req.body.confirm_password) return fail('The two new passwords do not match.');
      db.prepare('UPDATE users SET password_hash = ? WHERE id = ?').run(bcrypt.hashSync(req.body.new_password, 12), user.id);
      // Sign out every other session for this account.
      db.prepare('DELETE FROM sessions WHERE sid != ? AND json_extract(data, \'$.userId\') = ?').run(req.sessionID, user.id);
      flash(req, 'success', 'Password changed.');
      break;
    }
    case 'update_privacy':
      db.prepare('UPDATE users SET profile_visible = ? WHERE id = ?').run(req.body.profile_visible ? 1 : 0, user.id);
      flash(req, 'success', 'Privacy settings updated.');
      break;
    case 'delete_account': {
      if (!passwordOk(req.body.current_password)) return fail('Your password is incorrect.');
      if (user.role === 'admin' && db.prepare("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'").get().n <= 1) {
        return fail('You are the only administrator. Promote someone else before deleting this account.');
      }
      const images = db.prepare('SELECT image FROM posts WHERE user_id = ? AND image IS NOT NULL').all(user.id).map((r) => r.image);
      db.prepare('DELETE FROM users WHERE id = ?').run(user.id);
      [...images, user.profile_image, user.cover_image].forEach(removeImage);
      return req.session.destroy((err) => {
        if (err) return next(err);
        res.clearCookie('helpie.sid');
        res.redirect('/');
      });
    }
    default:
      return fail('Unknown action.');
  }
  res.redirect('/settings');
});

module.exports = router;
