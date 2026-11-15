# Helpie

A community platform for donating and requesting medical supplies. Donors list
what they can give, recipients post what they need, and the two arrange the
hand-over through real-time private messaging.

Helpie is a Node.js application: Express, server-rendered EJS views and
Postgres. It is set up to run on **Netlify** (the app runs as a Netlify
Function, the database is Netlify Database, uploaded images go to Netlify
Blobs), and runs locally with no setup at all.

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

## Deploy to Netlify

Everything Netlify needs is in the repository (`netlify.toml`,
`netlify/functions/app.mjs`, `netlify/database/migrations/`).

1. Connect this repository to a Netlify project (or run `netlify deploy --prod`).
2. Set one environment variable on the project: `SESSION_SECRET`, a long random
   string (for example the output of `openssl rand -hex 32`).
3. Deploy. Netlify provisions the Postgres database, applies the migrations and
   publishes the site. `/healthz` reports whether the app can reach the database.

To use a different Postgres (Supabase, Neon, your own), set `DATABASE_URL` to
its connection string and run the SQL in `netlify/database/migrations/` on it.

### Creating an administrator

Locally:

```bash
npm run create-admin -- <username> <email> [password]
```

Against the deployed database, run the same command with `DATABASE_URL` set to
the connection string shown in Netlify under **Data & Storage → Database**.
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
| `NETLIFY_DB_URL` | Set automatically by Netlify Database                                   |
| `DATABASE_URL`   | Optional: any other Postgres connection string (takes priority)         |
| `PORT`           | Local port (default `3000`)                                             |
| `DATA_DIR`       | Local folder for the embedded database and uploads (default `./data`)   |

## Project layout

```
server.js                    Local development server
netlify.toml                 Netlify build, function and routing config
netlify/functions/app.mjs    Runs the Express app as a Netlify Function
netlify/database/migrations  Postgres schema (applied by Netlify on deploy)
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
