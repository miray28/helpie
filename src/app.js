'use strict';
const path = require('path');
const express = require('express');
const session = require('express-session');
const helmet = require('helmet');
const multer = require('multer');
const config = require('./config');
const { db } = require('./db');
const PostgresStore = require('./session-store');
const storage = require('./storage');
const mw = require('./middleware');

const app = express();
app.disable('x-powered-by');
app.set('trust proxy', config.isProd ? 1 : false);
app.engine('ejs', require('ejs').__express); // explicit so the bundler includes it
app.set('view engine', 'ejs');
app.set('views', path.join(config.root, 'views'));

const supabaseHost = config.supabaseUrl ? new URL(config.supabaseUrl).host : null;
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      imgSrc: ["'self'", 'data:', 'blob:'],
      connectSrc: ["'self'", ...(supabaseHost ? [`wss://${supabaseHost}`] : [])],
      objectSrc: ["'none'"],
      frameAncestors: ["'none'"],
      upgradeInsecureRequests: null,
    },
  },
}));

// On Netlify the CDN serves public/ directly; this only matters locally.
app.use(express.static(path.join(config.root, 'public'), { maxAge: config.isProd ? '7d' : 0 }));

// Health check: confirms the function can reach the database.
app.get('/healthz', async (req, res) => {
  try {
    await db.get('SELECT 1 AS ok');
    res.json({ ok: true });
  } catch (err) {
    console.error('Health check failed:', err);
    res.status(503).json({ ok: false, error: err.code || err.message });
  }
});

// Uploaded images. Names are random and never reused, so they can be cached
// forever by browsers and by the CDN.
app.get('/uploads/:folder/:file', async (req, res, next) => {
  const image = await storage.readImage(`${req.params.folder}/${req.params.file}`);
  if (!image) return next();
  res.set({
    'Content-Type': image.contentType,
    'Cache-Control': 'public, max-age=31536000, immutable',
    'Netlify-CDN-Cache-Control': 'public, max-age=31536000, immutable',
    'Content-Security-Policy': "default-src 'none'; sandbox",
    'X-Content-Type-Options': 'nosniff',
  });
  res.send(image.body);
});

app.use(session({
  name: 'helpie.sid',
  secret: config.sessionSecret,
  store: new PostgresStore(),
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: config.isProd,
    maxAge: 30 * 24 * 3600 * 1000,
  },
}));
app.use(express.urlencoded({ extended: false, limit: '100kb' }));
app.use(express.json({ limit: '100kb' }));
app.use(mw.locals);
app.use(mw.csrf);
app.use(mw.maintenance);

app.use(require('./routes/public'));
app.use(require('./routes/auth'));
app.use(require('./routes/posts'));
app.use(require('./routes/users'));
app.use(require('./routes/messages'));
app.use('/admin', require('./routes/admin'));

app.use((req, res) => {
  res.status(404).render('error', {
    title: 'Page not found',
    status: 404,
    message: "We couldn't find the page you were looking for.",
  });
});

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  let status = err.status || 500;
  let message = err.message;
  if (err instanceof multer.MulterError) {
    status = 400;
    message = err.code === 'LIMIT_FILE_SIZE' ? 'Images must be 4 MB or smaller.' : 'That upload could not be processed.';
  } else if (err.userFacing) {
    status = 400;
  } else if (status >= 500) {
    console.error(err);
    message = 'Something went wrong on our side. Please try again.';
  }
  if (mw.wantsJson(req)) return res.status(status).json({ success: false, message });
  if (!res.locals.settings) {
    // The failure happened before page data was loaded (e.g. database down).
    return res.status(status).type('text').send(message);
  }
  res.status(status).render('error', { title: 'Something went wrong', status, message });
});

module.exports = { app };
