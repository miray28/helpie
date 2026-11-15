'use strict';
const express = require('express');
const { db } = require('../db');
const { flash, requireLogin, csrfAfterUpload, wantsJson } = require('../middleware');
const { upload, saveImage, removeImage } = require('../storage');
const { str, toId, paginate, likePattern } = require('../helpers');

const router = express.Router();

const CONDITIONS = ['new', 'like_new', 'good', 'fair'];
const STATUSES = ['available', 'pending', 'claimed'];
const REPORT_TYPES = ['spam', 'inappropriate', 'harassment', 'other'];

const POST_SELECT = `
  SELECT p.*, u.username, u.profile_image AS author_image, c.name AS category_name,
         (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.id) AS save_count,
         (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
         EXISTS(SELECT 1 FROM saved_posts WHERE post_id = p.id AND user_id = @me) AS is_saved
  FROM posts p
  JOIN users u ON u.id = p.user_id
  LEFT JOIN categories c ON c.id = p.category_id`;

const allCategories = () => db.all('SELECT * FROM categories ORDER BY name');

// Shared filter handling for the feed and "my posts".
function buildFilters(query, where, params) {
  if (['donation', 'request'].includes(query.type)) { where.push('p.type = @type'); params.type = query.type; }
  if (STATUSES.includes(query.status)) { where.push('p.status = @status'); params.status = query.status; }
  const category = toId(query.category);
  if (category) { where.push('p.category_id = @category'); params.category = category; }
  const search = str(query.search, 100);
  if (search) {
    where.push("(p.title ILIKE @search ESCAPE '\\' OR p.content ILIKE @search ESCAPE '\\' OR p.location ILIKE @search ESCAPE '\\')");
    params.search = likePattern(search);
  }
}

async function listPosts(req, baseWhere, baseParams) {
  const where = [...baseWhere];
  const filters = { ...baseParams };
  buildFilters(req.query, where, filters);
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const { n: total } = await db.get(`SELECT COUNT(*) AS n FROM posts p JOIN users u ON u.id = p.user_id ${clause}`, filters);
  const perPage = Math.min(Math.max(parseInt(req.res.locals.settings.posts_per_page, 10) || 12, 1), 60);
  const paging = paginate(total, req.query.page, perPage);
  const posts = await db.all(`${POST_SELECT} ${clause} ORDER BY p.created_at DESC LIMIT @limit OFFSET @offset`,
    { ...filters, me: req.user.id, limit: paging.perPage, offset: paging.offset });
  return { posts, paging };
}

function readPostForm(body) {
  const type = body.type === 'request' ? 'request' : 'donation';
  const quantity = parseInt(body.quantity, 10);
  const expiry = str(body.expiry_date, 10);
  return {
    type,
    title: str(body.title, 120),
    content: str(body.content, 5000),
    category_id: toId(body.category_id),
    condition_state: type === 'donation' && CONDITIONS.includes(body.condition_state) ? body.condition_state : null,
    quantity: Number.isInteger(quantity) && quantity > 0 && quantity < 100000 ? quantity : null,
    expiry_date: /^\d{4}-\d{2}-\d{2}$/.test(expiry) ? expiry : null,
    location: str(body.location, 120) || null,
  };
}

async function validatePostForm(form) {
  if (form.title.length < 3) return 'Please give your post a title (at least 3 characters).';
  if (form.content.length < 10) return 'Please describe the item in a little more detail (at least 10 characters).';
  if (!form.category_id || !(await db.get('SELECT 1 FROM categories WHERE id = ?', [form.category_id]))) return 'Please choose a category.';
  return null;
}

async function loadPost(req, res, next) {
  const id = toId(req.params.id);
  const post = id ? await db.get(`${POST_SELECT} WHERE p.id = @id`, { me: req.user.id, id }) : null;
  if (!post) {
    if (wantsJson(req)) return res.status(404).json({ success: false, message: 'Post not found.' });
    const err = new Error('That post no longer exists.');
    err.status = 404;
    return next(err);
  }
  req.post = post;
  next();
}

const canManage = (user, post) => user.id === post.user_id || user.role === 'admin';

function requireOwner(req, res, next) {
  if (canManage(req.user, req.post)) return next();
  const err = new Error('You can only change your own posts.');
  err.status = 403;
  next(err);
}

/* ---------- Lists ---------- */

router.get('/feed', requireLogin, async (req, res) => {
  const [{ posts, paging }, categories] = await Promise.all([listPosts(req, ['u.blocked = 0'], {}), allCategories()]);
  res.render('feed', { title: 'Community feed', heading: 'Community feed', posts, paging, categories, mode: 'feed' });
});

router.get('/my-posts', requireLogin, async (req, res) => {
  const [{ posts, paging }, categories] = await Promise.all([
    listPosts(req, ['p.user_id = @owner'], { owner: req.user.id }), allCategories()]);
  res.render('feed', { title: 'My posts', heading: 'My posts', posts, paging, categories, mode: 'mine' });
});

router.get('/saved', requireLogin, async (req, res) => {
  const { n: total } = await db.get('SELECT COUNT(*) AS n FROM saved_posts WHERE user_id = ?', [req.user.id]);
  const paging = paginate(total, req.query.page, 12);
  const posts = await db.all(`${POST_SELECT}
    JOIN saved_posts sp ON sp.post_id = p.id AND sp.user_id = @me
    ORDER BY sp.saved_at DESC LIMIT @limit OFFSET @offset`,
    { me: req.user.id, limit: paging.perPage, offset: paging.offset });
  res.render('feed', { title: 'Saved posts', heading: 'Saved posts', posts, paging, categories: [], mode: 'saved' });
});

/* ---------- Create / edit ---------- */

// Re-renders the form with a message instead of a bare error page.
async function formError(res, message, view) {
  res.locals.flash.push({ type: 'error', message });
  res.status(400).render('post-form', { ...view, categories: await allCategories() });
}

router.get('/posts/new', requireLogin, async (req, res) => {
  const type = req.query.type === 'request' || (req.query.type !== 'donation' && req.user.role === 'recipient') ? 'request' : 'donation';
  res.render('post-form', { title: 'New post', post: null, form: { type }, categories: await allCategories() });
});

router.post('/posts', requireLogin, upload.single('image'), csrfAfterUpload, async (req, res) => {
  const form = readPostForm(req.body);
  const view = { title: 'New post', post: null, form };
  const error = await validatePostForm(form);
  if (error) return formError(res, error, view);
  let image = null;
  try {
    if (req.file) image = await saveImage(req.file, 'posts');
  } catch (err) {
    if (!err.userFacing) throw err;
    return formError(res, err.message, view);
  }
  const now = Date.now();
  const row = await db.get(`
    INSERT INTO posts (user_id, type, title, content, category_id, condition_state, quantity, expiry_date, location, image, created_at, updated_at)
    VALUES (@user_id, @type, @title, @content, @category_id, @condition_state, @quantity, @expiry_date, @location, @image, @now, @now)
    RETURNING id`, { ...form, user_id: req.user.id, image, now });
  flash(req, 'success', 'Your post is live.');
  res.redirect(`/posts/${row.id}`);
});

router.get('/posts/:id/edit', requireLogin, loadPost, requireOwner, async (req, res) => {
  res.render('post-form', { title: 'Edit post', post: req.post, form: req.post, categories: await allCategories() });
});

router.post('/posts/:id/edit', requireLogin, upload.single('image'), csrfAfterUpload, loadPost, requireOwner, async (req, res) => {
  const form = readPostForm(req.body);
  const view = { title: 'Edit post', post: req.post, form };
  const error = await validatePostForm(form);
  if (error) return formError(res, error, view);
  let image = req.post.image;
  try {
    if (req.file) image = await saveImage(req.file, 'posts');
    else if (req.body.remove_image) image = null;
  } catch (err) {
    if (!err.userFacing) throw err;
    return formError(res, err.message, view);
  }
  await db.run(`
    UPDATE posts SET type = @type, title = @title, content = @content, category_id = @category_id,
      condition_state = @condition_state, quantity = @quantity, expiry_date = @expiry_date,
      location = @location, image = @image, updated_at = @now
    WHERE id = @id`, { ...form, image, now: Date.now(), id: req.post.id });
  if (image !== req.post.image) await removeImage(req.post.image);
  flash(req, 'success', 'Post updated.');
  res.redirect(`/posts/${req.post.id}`);
});

/* ---------- Detail ---------- */

router.get('/posts/:id', requireLogin, loadPost, async (req, res) => {
  const post = req.post;
  const isOwner = post.user_id === req.user.id;
  const [comments, myRequest, requests] = await Promise.all([
    db.all(`
      SELECT c.*, u.username, u.profile_image FROM comments c
      JOIN users u ON u.id = c.user_id
      WHERE c.post_id = ? ORDER BY c.created_at ASC, c.id ASC`, [post.id]),
    isOwner ? null : db.get('SELECT * FROM donation_requests WHERE post_id = ? AND requester_id = ?', [post.id, req.user.id]),
    isOwner ? db.all(`
      SELECT dr.*, u.username, u.profile_image FROM donation_requests dr
      JOIN users u ON u.id = dr.requester_id
      WHERE dr.post_id = ? ORDER BY dr.created_at DESC`, [post.id]) : [],
  ]);
  res.render('post', { title: post.title, post, comments, isOwner, myRequest, requests, canManage: canManage(req.user, post) });
});

/* ---------- Actions ---------- */

router.post('/posts/:id/delete', requireLogin, loadPost, requireOwner, async (req, res) => {
  await db.run('DELETE FROM posts WHERE id = ?', [req.post.id]);
  await db.run("DELETE FROM reports WHERE content_type = 'post' AND content_id = ? AND status = 'pending'", [req.post.id]);
  await removeImage(req.post.image);
  if (wantsJson(req)) return res.json({ success: true });
  flash(req, 'success', 'Post deleted.');
  res.redirect(req.user.id === req.post.user_id ? '/my-posts' : '/feed');
});

router.post('/posts/:id/status', requireLogin, loadPost, requireOwner, async (req, res) => {
  if (!STATUSES.includes(req.body.status)) {
    flash(req, 'error', 'Unknown status.');
  } else {
    await db.run('UPDATE posts SET status = ?, updated_at = ? WHERE id = ?', [req.body.status, Date.now(), req.post.id]);
    flash(req, 'success', 'Status updated.');
  }
  res.redirect(`/posts/${req.post.id}`);
});

router.post('/posts/:id/save', requireLogin, loadPost, async (req, res) => {
  const removed = (await db.run('DELETE FROM saved_posts WHERE post_id = ? AND user_id = ?', [req.post.id, req.user.id])).rowCount;
  if (!removed) {
    await db.run('INSERT INTO saved_posts (post_id, user_id, saved_at) VALUES (?, ?, ?) ON CONFLICT DO NOTHING',
      [req.post.id, req.user.id, Date.now()]);
  }
  const { n: saveCount } = await db.get('SELECT COUNT(*) AS n FROM saved_posts WHERE post_id = ?', [req.post.id]);
  if (wantsJson(req)) return res.json({ success: true, is_saved: !removed, save_count: saveCount });
  res.redirect(`/posts/${req.post.id}`);
});

router.post('/posts/:id/comments', requireLogin, loadPost, async (req, res) => {
  const content = str(req.body.content, 1000);
  if (!content) flash(req, 'error', 'Comments cannot be empty.');
  else await db.run('INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, ?)', [req.post.id, req.user.id, content, Date.now()]);
  res.redirect(`/posts/${req.post.id}#comments`);
});

router.post('/comments/:id/delete', requireLogin, async (req, res, next) => {
  const comment = await db.get(
    'SELECT c.*, p.user_id AS post_owner FROM comments c JOIN posts p ON p.id = c.post_id WHERE c.id = ?', [toId(req.params.id)]);
  if (!comment) return next();
  if (![comment.user_id, comment.post_owner].includes(req.user.id) && req.user.role !== 'admin') {
    const err = new Error('You cannot delete that comment.');
    err.status = 403;
    return next(err);
  }
  await db.run('DELETE FROM comments WHERE id = ?', [comment.id]);
  res.redirect(`/posts/${comment.post_id}#comments`);
});

/* ---------- Donation requests ---------- */

router.post('/posts/:id/request', requireLogin, loadPost, async (req, res) => {
  const post = req.post;
  if (post.type !== 'donation') flash(req, 'error', 'Only donations can be requested.');
  else if (post.user_id === req.user.id) flash(req, 'error', 'You cannot request your own donation.');
  else if (post.status === 'claimed') flash(req, 'error', 'This donation has already been claimed.');
  else {
    const info = await db.run(
      'INSERT INTO donation_requests (post_id, requester_id, notes, created_at) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING',
      [post.id, req.user.id, str(req.body.notes, 1000), Date.now()]);
    flash(req, info.rowCount ? 'success' : 'error',
      info.rowCount ? 'Request sent. The donor will be in touch.' : 'You have already requested this donation.');
  }
  res.redirect(`/posts/${post.id}`);
});

router.get('/requests', requireLogin, async (req, res) => {
  const requests = await db.all(`
    SELECT dr.*, p.title AS post_title, p.status AS post_status, u.username, u.profile_image
    FROM donation_requests dr
    JOIN posts p ON p.id = dr.post_id
    JOIN users u ON u.id = dr.requester_id
    WHERE p.user_id = ?
    ORDER BY (dr.status = 'pending') DESC, dr.created_at DESC`, [req.user.id]);
  res.render('requests', { title: 'Donation requests', requests });
});

router.post('/requests/:id/status', requireLogin, async (req, res, next) => {
  const request = await db.get(
    'SELECT dr.*, p.user_id AS owner_id FROM donation_requests dr JOIN posts p ON p.id = dr.post_id WHERE dr.id = ?', [toId(req.params.id)]);
  if (!request || request.owner_id !== req.user.id) return next();
  if (!['approved', 'rejected'].includes(req.body.status) || request.status !== 'pending') {
    flash(req, 'error', 'That request can no longer be changed.');
  } else {
    await db.tx(async (tx) => {
      await tx.run('UPDATE donation_requests SET status = ? WHERE id = ?', [req.body.status, request.id]);
      if (req.body.status === 'approved') {
        // Reserve the item so nobody else expects it while the hand-over is arranged.
        await tx.run("UPDATE posts SET status = 'pending', updated_at = ? WHERE id = ? AND status = 'available'", [Date.now(), request.post_id]);
      }
    });
    flash(req, 'success', req.body.status === 'approved'
      ? 'Request approved. Send a message to arrange the hand-over.'
      : 'Request declined.');
  }
  res.redirect('/requests');
});

router.post('/requests/:id/cancel', requireLogin, async (req, res) => {
  await db.run("DELETE FROM donation_requests WHERE id = ? AND requester_id = ? AND status = 'pending'", [toId(req.params.id), req.user.id]);
  flash(req, 'success', 'Request withdrawn.');
  res.redirect('/activity');
});

router.get('/activity', requireLogin, async (req, res) => {
  const [myRequests, myPosts] = await Promise.all([
    db.all(`
      SELECT dr.*, p.title AS post_title, p.image, u.username AS poster_name, u.id AS poster_id
      FROM donation_requests dr
      JOIN posts p ON p.id = dr.post_id
      JOIN users u ON u.id = p.user_id
      WHERE dr.requester_id = ? ORDER BY dr.created_at DESC`, [req.user.id]),
    db.all(`
      SELECT p.*, (SELECT COUNT(*) FROM donation_requests WHERE post_id = p.id) AS request_count,
             (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count
      FROM posts p WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 50`, [req.user.id]),
  ]);
  res.render('activity', { title: 'My activity', myRequests, myPosts });
});

/* ---------- Reports ---------- */

function reportTarget(type, id, me) {
  if (!id) return null;
  if (type === 'post') {
    return db.get('SELECT p.id, p.title AS label, p.user_id, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.user_id != ?', [id, me]);
  }
  if (type === 'comment') {
    return db.get('SELECT c.id, c.content AS label, c.user_id, u.username FROM comments c JOIN users u ON u.id = c.user_id WHERE c.id = ? AND c.user_id != ?', [id, me]);
  }
  if (type === 'user') {
    return db.get('SELECT id, username AS label, id AS user_id, username FROM users WHERE id = ? AND id != ?', [id, me]);
  }
  return null;
}

router.get('/report', requireLogin, async (req, res, next) => {
  const type = str(req.query.type, 10);
  const target = await reportTarget(type, toId(req.query.id), req.user.id);
  if (!target) return next();
  res.render('report', { title: 'Report', type, target, reportTypes: REPORT_TYPES });
});

router.post('/report', requireLogin, async (req, res, next) => {
  const type = str(req.body.content_type, 10);
  const target = await reportTarget(type, toId(req.body.content_id), req.user.id);
  if (!target) return next();
  if (!REPORT_TYPES.includes(req.body.type)) {
    flash(req, 'error', 'Please choose a reason for the report.');
    return res.redirect(`/report?type=${type}&id=${target.id}`);
  }
  await db.run(`
    INSERT INTO reports (content_type, content_id, reporter_id, reported_user_id, type, reason, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [type, target.id, req.user.id, target.user_id, req.body.type, str(req.body.reason, 1000) || null, Date.now()]);
  flash(req, 'success', 'Thanks — our moderators will review your report.');
  res.redirect('/feed');
});

module.exports = router;
