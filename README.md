# Helpie

A PHP (Composer) web application with a public portal and admin dashboard. Includes WebSocket chat using Ratchet, Tailwind CSS for styles, and a MySQL database.

## Prerequisites
- PHP 8+
- Composer
- MySQL/MariaDB
- Node.js 16+
- WAMP/XAMPP or equivalent local web server

## Local Setup
1. Copy `.env.example` to `.env` and fill DB credentials (if present). Otherwise set DB in `includes/config.php`.
2. Install PHP deps:
   ```bash
   composer install
   ```
3. Install Node deps (for assets if used):
   ```bash
   npm install
   ```
4. Build CSS (optional if using Tailwind):
   ```bash
   npm run build
   ```
5. Configure your virtual host to point to `public/`.
6. Import SQL from `database/` if needed.

## WebSocket Chat
- Start server from `websocket/`:
  ```bash
  php start-server.php
  ```
  or use the provided `.bat` files on Windows.

## Scripts
- `npm run build`: builds Tailwind from `src/css/input.css` to `public/assets/css/main.css`.

## Deployment
- Ensure `public/` is the web root.
- Never commit `public/uploads/`, logs, or secrets.

## License
Proprietary (or update as needed).
