'use strict';
// Local development server. On Netlify the app runs from netlify/functions/app.mjs.
const config = require('./src/config');
const { app } = require('./src/app');
const { db } = require('./src/db');

db.ready.then(() => {
  const server = app.listen(config.port, () => {
    console.log(`Helpie is running at http://localhost:${config.port}`);
    console.log(config.databaseUrl ? 'Database: Supabase Postgres' : `Database: embedded Postgres in ${config.dataDir}`);
  });
  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
      server.close(() => db.close().finally(() => process.exit(0)));
      setTimeout(() => process.exit(0), 3000).unref();
    });
  }
}).catch((err) => {
  console.error('Could not start Helpie:', err);
  process.exit(1);
});
