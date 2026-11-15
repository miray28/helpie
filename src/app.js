'use strict';
const path = require('path');
const express = require('express');
const session = require('express-session');
const helmet = require('helmet');
const multer = require('multer');
const config = require('./config');
const SqliteStore = require('./session-store');
const mw = require('./middleware');

const app = express();
app.disable('x-powered-by');
if (config.trustProxy) app.set('trust proxy', 1);
app.set('view engine', 'ejs');
app.set('views', path.join(config.root, 'views'));

app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      imgSrc: ["'self'", 'data:', 'blob:'],
      connectSrc: ["'self'", 'ws:', 'wss:'],
      objectSrc: ["'none'"],
      frameAncestors: ["'none'"],
      upgradeInsecureRequests: null,
    },
  },
}));

app.use(express.static(path.join(config.root, 'public'), { maxAge: config.isProd ? '7d' : 0 }));
app.use('/uploads', express.static(config.uploadDir, {
  maxAge: '30d',
  index: false,
  setHeaders: (res) => res.setHeader('Content-Security-Policy', "default-src 'none'; sandbox"),
}));

const sessionParser = session({
  name: 'helpie.sid',
  secret: config.sessionSecret,
  store: new SqliteStore(),
  resave: false,
  saveUninitialized: false,
  rolling: true,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: config.isProd && config.trustProxy,
    maxAge: 14 * 24 * 3600 * 1000,
  },
});
app.use(sessionParser);
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
    message = err.code === 'LIMIT_FILE_SIZE' ? 'Images must be 5 MB or smaller.' : 'That upload could not be processed.';
  } else if (err.userFacing) {
    status = 400;
  } else if (status >= 500) {
    console.error(err);
    message = 'Something went wrong on our side. Please try again.';
  }
  if (mw.wantsJson(req)) return res.status(status).json({ success: false, message });
  res.status(status).render('error', { title: 'Something went wrong', status, message });
});

module.exports = { app, sessionParser };
