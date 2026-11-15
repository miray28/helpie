'use strict';
const express = require('express');
const { db, setSetting } = require('../db');
const { flash, requireAdmin } = require('../middleware');
const { removeImage } = require('../storage');
const { str, toId, isEmail, paginate, likePattern, safeRedirect } = require('../helpers');

const router = express.Router();
router.use(requireAdmin);

const DAY = 24 * 3600 * 1000;
const count = async (sql, params) => (await db.get(sql, params)).n;
const endSessions = (userId) => db.run('DELETE FROM sessions WHERE user_id = ?', [userId]);
const back = (req, fallback) => safeRedirect(req.body.return_to, fallback);

router.use(async (req, res, next) => {
  res.locals.adminCounts = {
    reports: await count("SELECT COUNT(*) AS n FROM reports WHERE status = 'pending'"),
  };
  next();
});

/* ---------- Dashboard ---------- */

router.get('/', async (req, res) => {
  const now = Date.now();
  // Posts per day for the last 14 days, oldest first.
  const since = new Date(now - 13 * DAY);
  since.setUTCHours(0, 0, 0, 0);

  const [stats, recentPosts, contactMessages, rows] = await Promise.all([
    db.get(`
      SELECT (SELECT COUNT(*) FROM users) AS users,
             (SELECT COUNT(*) FROM posts) AS posts,
             (SELECT COUNT(*) FROM posts WHERE type = 'donation') AS donations,
             (SELECT COUNT(*) FROM posts WHERE type = 'request') AS requests,
             (SELECT COUNT(DISTINCT user_id) FROM posts WHERE created_at >= @month) AS "activeUsers",
             (SELECT COUNT(*) FROM users WHERE created_at >= @week) AS "recentSignups"`,
      { month: now - 30 * DAY, week: now - 7 * DAY }),
    db.all(`
      SELECT p.id, p.title, p.type, p.status, p.created_at, u.username, u.id AS user_id
      FROM posts p JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT 8`),
    db.all('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5'),
    db.all(`
      SELECT ((created_at - @since::bigint) / @day::bigint)::int AS bucket, COUNT(*) AS n
      FROM posts WHERE created_at >= @since::bigint GROUP BY bucket`, { since: since.getTime(), day: DAY }),
  ]);
  stats.pendingReports = res.locals.adminCounts.reports;
  const chart = Array.from({ length: 14 }, (_, i) => ({
    label: new Date(since.getTime() + i * DAY).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' }),
    value: (rows.find((r) => r.bucket === i) || { n: 0 }).n,
  }));

  res.render('admin/dashboard', { title: 'Admin dashboard', stats, recentPosts, contactMessages, chart });
});

/* ---------- Users ---------- */

router.get('/users', async (req, res) => {
  const where = [];
  const params = {};
  const search = str(req.query.search, 100);
  if (search) {
    where.push("(u.username ILIKE @search ESCAPE '\\' OR u.email ILIKE @search ESCAPE '\\')");
    params.search = likePattern(search);
  }
  if (['admin', 'donor', 'recipient'].includes(req.query.role)) { where.push('u.role = @role'); params.role = req.query.role; }
  if (req.query.state === 'blocked') where.push('u.blocked = 1');
  if (req.query.state === 'unverified') where.push('u.verified = 0');
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const paging = paginate(await count(`SELECT COUNT(*) AS n FROM users u ${clause}`, params), req.query.page, 20);
  const users = await db.all(`
    SELECT u.*, (SELECT COUNT(*) FROM posts WHERE user_id = u.id) AS post_count
    FROM users u ${clause} ORDER BY u.created_at DESC LIMIT @limit OFFSET @offset`,
    { ...params, limit: paging.perPage, offset: paging.offset });
  res.render('admin/users', { title: 'Users', users, paging });
});

router.post('/users/:id', async (req, res, next) => {
  const target = await db.get('SELECT * FROM users WHERE id = ?', [toId(req.params.id)]);
  if (!target) return next();
  const redirect = () => res.redirect(back(req, '/admin/users'));
  const isSelf = target.id === req.user.id;

  switch (req.body.action) {
    case 'role': {
      if (!['admin', 'donor', 'recipient'].includes(req.body.role)) { flash(req, 'error', 'Unknown role.'); break; }
      if (isSelf && req.body.role !== 'admin') { flash(req, 'error', 'You cannot remove your own administrator role.'); break; }
      await db.run('UPDATE users SET role = ? WHERE id = ?', [req.body.role, target.id]);
      flash(req, 'success', `${target.username} is now ${req.body.role === 'admin' ? 'an admin' : `a ${req.body.role}`}.`);
      break;
    }
    case 'verify':
    case 'unverify':
      await db.run('UPDATE users SET verified = ? WHERE id = ?', [req.body.action === 'verify' ? 1 : 0, target.id]);
      flash(req, 'success', `${target.username} ${req.body.action === 'verify' ? 'verified' : 'is no longer verified'}.`);
      break;
    case 'block':
      if (isSelf || target.role === 'admin') { flash(req, 'error', 'Administrators cannot be blocked. Change their role first.'); break; }
      await db.run('UPDATE users SET blocked = 1 WHERE id = ?', [target.id]);
      await endSessions(target.id); // sign the blocked member out immediately
      flash(req, 'success', `${target.username} has been blocked.`);
      break;
    case 'unblock':
      await db.run('UPDATE users SET blocked = 0 WHERE id = ?', [target.id]);
      flash(req, 'success', `${target.username} has been unblocked.`);
      break;
    default:
      flash(req, 'error', 'Unknown action.');
  }
  redirect();
});

/* ---------- Posts ---------- */

router.get('/posts', async (req, res) => {
  const where = [];
  const params = {};
  if (['donation', 'request'].includes(req.query.type)) { where.push('p.type = @type'); params.type = req.query.type; }
  if (['available', 'pending', 'claimed'].includes(req.query.status)) { where.push('p.status = @status'); params.status = req.query.status; }
  const search = str(req.query.search, 100);
  if (search) {
    where.push("(p.title ILIKE @search ESCAPE '\\' OR p.content ILIKE @search ESCAPE '\\' OR u.username ILIKE @search ESCAPE '\\')");
    params.search = likePattern(search);
  }
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const paging = paginate(await count(`SELECT COUNT(*) AS n FROM posts p JOIN users u ON u.id = p.user_id ${clause}`, params), req.query.page, 20);
  const posts = await db.all(`
    SELECT p.*, u.username, c.name AS category_name,
      (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count
    FROM posts p JOIN users u ON u.id = p.user_id LEFT JOIN categories c ON c.id = p.category_id
    ${clause} ORDER BY p.created_at DESC LIMIT @limit OFFSET @offset`,
    { ...params, limit: paging.perPage, offset: paging.offset });
  res.render('admin/posts', { title: 'Posts', posts, paging });
});

async function deletePost(id) {
  const post = id ? await db.get('SELECT id, image FROM posts WHERE id = ?', [id]) : null;
  if (!post) return false;
  await db.run('DELETE FROM posts WHERE id = ?', [post.id]);
  await removeImage(post.image);
  return true;
}

router.post('/posts/:id/delete', async (req, res) => {
  flash(req, 'success', (await deletePost(toId(req.params.id))) ? 'Post deleted.' : 'That post was already gone.');
  res.redirect(back(req, '/admin/posts'));
});

/* ---------- Reports ---------- */

router.get('/reports', async (req, res) => {
  const where = [];
  const params = {};
  const status = ['pending', 'resolved', 'dismissed', 'all'].includes(req.query.status) ? req.query.status : 'pending';
  if (status !== 'all') { where.push('r.status = @status'); params.status = status; }
  if (['post', 'comment', 'user'].includes(req.query.type)) { where.push('r.content_type = @type'); params.type = req.query.type; }
  const clause = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const paging = paginate(await count(`SELECT COUNT(*) AS n FROM reports r ${clause}`, params), req.query.page, 20);
  const reports = await db.all(`
    SELECT r.*, reporter.username AS reporter_name, reported.username AS reported_name, reported.blocked AS reported_blocked,
      p.title AS post_title, cm.content AS comment_content, cm.post_id AS comment_post_id
    FROM reports r
    LEFT JOIN users reporter ON reporter.id = r.reporter_id
    LEFT JOIN users reported ON reported.id = r.reported_user_id
    LEFT JOIN posts p ON r.content_type = 'post' AND p.id = r.content_id
    LEFT JOIN comments cm ON r.content_type = 'comment' AND cm.id = r.content_id
    ${clause} ORDER BY r.created_at DESC LIMIT @limit OFFSET @offset`,
    { ...params, limit: paging.perPage, offset: paging.offset });
  res.render('admin/reports', { title: 'Reports', reports, paging, status });
});

router.post('/reports/:id', async (req, res, next) => {
  const report = await db.get('SELECT * FROM reports WHERE id = ?', [toId(req.params.id)]);
  if (!report) return next();
  const close = (status, action) => db.run(
    'UPDATE reports SET status = ?, action_taken = ?, resolved_by = ?, resolved_at = ? WHERE id = ?',
    [status, action, req.user.id, Date.now(), report.id]);

  switch (req.body.action) {
    case 'dismiss':
      await close('dismissed', 'none');
      flash(req, 'success', 'Report dismissed.');
      break;
    case 'resolve':
      await close('resolved', 'reviewed');
      flash(req, 'success', 'Report marked as resolved.');
      break;
    case 'delete_content':
      if (report.content_type === 'post') await deletePost(report.content_id);
      else if (report.content_type === 'comment') await db.run('DELETE FROM comments WHERE id = ?', [report.content_id]);
      else { flash(req, 'error', 'Use “Block member” for reports about a person.'); break; }
      await close('resolved', 'content_deleted');
      flash(req, 'success', 'Content removed and report resolved.');
      break;
    case 'block_user': {
      const target = await db.get('SELECT * FROM users WHERE id = ?', [report.reported_user_id]);
      if (!target || target.role === 'admin') { flash(req, 'error', 'That member cannot be blocked.'); break; }
      await db.run('UPDATE users SET blocked = 1 WHERE id = ?', [target.id]);
      await endSessions(target.id);
      await close('resolved', 'user_blocked');
      flash(req, 'success', `${target.username} has been blocked and the report resolved.`);
      break;
    }
    default:
      flash(req, 'error', 'Unknown action.');
  }
  res.redirect(back(req, '/admin/reports'));
});

/* ---------- Categories ---------- */

router.get('/categories', async (req, res) => {
  const categories = await db.all(`
    SELECT c.*, (SELECT COUNT(*) FROM posts WHERE category_id = c.id) AS post_count
    FROM categories c ORDER BY c.name`);
  res.render('admin/categories', { title: 'Categories', categories });
});

router.post('/categories', async (req, res) => {
  const name = str(req.body.name, 60);
  const description = str(req.body.description, 200) || null;
  const id = toId(req.body.category_id);
  try {
    if (req.body.action === 'delete' && id) {
      if ((await count('SELECT COUNT(*) AS n FROM posts WHERE category_id = ?', [id])) > 0) {
        flash(req, 'error', 'That category still has posts. Move or delete them first.');
      } else {
        await db.run('DELETE FROM categories WHERE id = ?', [id]);
        flash(req, 'success', 'Category deleted.');
      }
    } else if (!name) {
      flash(req, 'error', 'Category name is required.');
    } else if (req.body.action === 'edit' && id) {
      await db.run('UPDATE categories SET name = ?, description = ? WHERE id = ?', [name, description, id]);
      flash(req, 'success', 'Category updated.');
    } else {
      await db.run('INSERT INTO categories (name, description) VALUES (?, ?)', [name, description]);
      flash(req, 'success', 'Category added.');
    }
  } catch (err) {
    if (err.code !== '23505') throw err; // unique_violation
    flash(req, 'error', 'A category with that name already exists.');
  }
  res.redirect('/admin/categories');
});

/* ---------- Settings ---------- */

router.get('/settings', (req, res) => res.render('admin/settings', { title: 'Site settings' }));

router.post('/settings', async (req, res) => {
  const siteName = str(req.body.site_name, 60);
  const contactEmail = str(req.body.contact_email, 254);
  const perPage = parseInt(req.body.posts_per_page, 10);
  if (!siteName) flash(req, 'error', 'Site name is required.');
  else if (contactEmail && !isEmail(contactEmail)) flash(req, 'error', 'Contact email is not a valid address.');
  else if (!(perPage >= 4 && perPage <= 60)) flash(req, 'error', 'Posts per page must be between 4 and 60.');
  else {
    await db.tx(async (tx) => {
      await setSetting('site_name', siteName, tx);
      await setSetting('site_description', str(req.body.site_description, 300), tx);
      await setSetting('contact_email', contactEmail, tx);
      await setSetting('posts_per_page', perPage, tx);
      await setSetting('enable_registration', req.body.enable_registration ? '1' : '0', tx);
      await setSetting('maintenance_mode', req.body.maintenance_mode ? '1' : '0', tx);
    });
    flash(req, 'success', 'Settings saved.');
  }
  res.redirect('/admin/settings');
});

module.exports = router;
