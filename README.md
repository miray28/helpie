# Helpie

A community platform for donating and requesting medical supplies. Donors list
what they can give, recipients post what they need, and the two arrange the
hand-over through real-time private messaging.

Helpie is a Node.js application: Express, server-rendered EJS views and
Postgres. It deploys to **Vercel** or **Netlify** as a serverless function
(uploaded images go to the host's blob store), and runs locally with no setup.

## Run it locally

Requires Node.js 20 or newer. Locally the app uses an embedded Postgres stored
in `./data`, so there is no database to install.

```bash
npm install
npm run seed      # optional: demo members and posts (password "helpie123")
npm start         # http://localhost:3000
```

Use `npm run dev` to restart automatically when files change, and `npm test`
to run the end-to-end tests.

## Deploy to Vercel

1. At vercel.com choose **Add New → Project** and import this repository.
   Leave the build settings as they are and add one environment variable:
   `SESSION_SECRET`, a long random string (e.g. the output of `openssl rand -hex 32`).
2. In the project's **Storage** tab, create a **Neon** Postgres database and a
   **Blob** store, and connect both to the project. They add the
   `DATABASE_URL` and `BLOB_READ_WRITE_TOKEN` variables for you.
3. Redeploy (Deployments → ⋯ → Redeploy) so the app picks up those variables.

The app creates its own tables the first time it starts. `/healthz` reports
whether it can reach the database.

## Deploy to Netlify

Connect the repository to a Netlify project, set `SESSION_SECRET`, and deploy.
Netlify provisions its own Postgres (Netlify Database), applies the SQL in
`netlify/database/migrations/` and stores uploads in Netlify Blobs. Note that
production deploys and the database both consume Netlify credits.

Any other Postgres works too: set `DATABASE_URL` to its connection string.

### Creating an administrator

Locally:

```bash
npm run create-admin -- <username> <email> [password]
```

Against the deployed database, run the same command with `DATABASE_URL` set to
your database's connection string (Vercel: Storage → your database → `.env.local` tab).
If the account already exists it is promoted to admin; otherwise it is created
(a password is generated and printed when you don't supply one).

## Features

- **Feed** of donations and requests with search, type/category/status filters and pagination
- **Posts** with photo upload, condition, quantity, expiry date and location; edit, delete and status tracking
- **Donation requests**: ask for an item, approve or decline, withdraw
- **Comments** and **saved posts**
- **Private chat** between members, with unread counts
- **Profiles** with avatar and cover photo, member directory, privacy setting
- **Reporting** of posts, comments and members
- **Admin panel**: dashboard, users (roles, verify, block), posts, reports, categories, site settings (registration on/off, maintenance mode)

## Configuration

| Variable         | Purpose                                                                 |
| ---------------- | ----------------------------------------------------------------------- |
| `SESSION_SECRET` | Secret for signing session cookies. **Required in production**          |
| `DATABASE_URL`   | Postgres connection string (`POSTGRES_URL` is also accepted)            |
| `BLOB_READ_WRITE_TOKEN` | Vercel Blob token for image uploads (set by Vercel)              |
| `NETLIFY_DB_URL` | Set automatically by Netlify Database                                   |
| `PORT`           | Local port (default `3000`)                                             |
| `DATA_DIR`       | Local folder for the embedded database and uploads (default `./data`)   |

## Project layout

```
server.js                    Local development server
api/index.js + vercel.json   Vercel entry point and routing
netlify.toml                 Netlify build, function and routing config
src/netlify-function.mjs     Runs the Express app as a Netlify Function
scripts/build-netlify.js     Bundles it into netlify/functions/app.mjs at deploy time
netlify/database/migrations  Postgres schema (applied on deploy / first start)
src/app.js                   Express app, security headers, sessions, errors
src/db.js                    Database access (hosted Postgres or embedded)
src/storage.js               Image validation and storage (Blobs or disk)
src/middleware.js            Auth guards, CSRF, flash messages, rate limiting
src/chat.js                  Private messaging
src/routes/                  public, auth, posts, users, messages, admin
views/                       EJS templates
public/                      Stylesheet, client scripts, images (served by the CDN)
scripts/                     create-admin and seed
test/                        End-to-end tests
```

## Security notes

- Passwords are hashed with bcrypt; sessions are regenerated on sign-in.
- Every form is CSRF-protected and all output is HTML-escaped.
- Uploads are checked by file signature (not filename), renamed randomly and served with a locked-down content policy.
- Sign-in, sign-up and the contact form are rate limited (counters live in the database, so limits hold across function instances).

## License

Proprietary (or update as needed).
