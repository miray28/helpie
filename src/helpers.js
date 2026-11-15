'use strict';

const UNITS = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
];

function timeAgo(ms) {
  if (!ms) return '';
  const seconds = Math.max(0, Math.floor((Date.now() - ms) / 1000));
  for (const [name, size] of UNITS) {
    const n = Math.floor(seconds / size);
    if (n >= 1) return `${n} ${name}${n > 1 ? 's' : ''} ago`;
  }
  return 'just now';
}

function formatDate(ms) {
  if (!ms) return '';
  return new Date(ms).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function humanize(value) {
  if (!value) return '';
  const s = String(value).replace(/_/g, ' ');
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function initials(name) {
  return String(name || '?').trim().slice(0, 2).toUpperCase();
}

function postImage(post) {
  return post && post.image ? `/uploads/${post.image}` : '/images/default-post-image.jpg';
}

function avatar(user) {
  const img = user && (user.profile_image || user.author_image);
  return img ? `/uploads/${img}` : '/images/default-avatar.jpg';
}

function cover(user) {
  return user && user.cover_image ? `/uploads/${user.cover_image}` : '/images/default-cover.jpg';
}

// Builds "?a=1&b=2" from the current query with overrides, dropping empty values.
function queryString(query, overrides = {}) {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries({ ...query, ...overrides })) {
    if (v !== undefined && v !== null && v !== '') params.set(k, String(v));
  }
  const s = params.toString();
  return s ? `?${s}` : '';
}

function paginate(total, page, perPage) {
  const pages = Math.max(1, Math.ceil(total / perPage));
  const current = Math.min(Math.max(1, parseInt(page, 10) || 1), pages);
  return { total, pages, page: current, perPage, offset: (current - 1) * perPage };
}

const str = (v, max = 5000) => (typeof v === 'string' ? v.trim().slice(0, max) : '');
const toId = (v) => {
  const n = Number(v);
  return Number.isInteger(n) && n > 0 ? n : null;
};
const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) && v.length <= 254;

// Escapes LIKE wildcards so user searches are matched literally (use with ESCAPE '\').
const likePattern = (v) => `%${String(v).replace(/[\\%_]/g, '\\$&')}%`;

// Only allow same-site relative redirects.
function safeRedirect(target, fallback = '/feed') {
  if (typeof target === 'string' && /^\/(?![/\\])/.test(target)) return target;
  return fallback;
}

const ICONS = {
  bookmark: '<path d="M19 21l-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
  message: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.5-4.5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
  flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  check: '<path d="M20 6L9 17l-5-5"/>',
  tag: '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.2"/>',
};

function icon(name) {
  return `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ''}</svg>`;
}

module.exports = {
  icon,
  timeAgo, formatDate, humanize, initials, postImage, avatar, cover,
  queryString, paginate, str, toId, isEmail, likePattern, safeRedirect,
};
