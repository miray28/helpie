'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const multer = require('multer');
const config = require('./config');

// Uploaded images. Serverless hosts have no persistent disk, so on Vercel they
// go to Vercel Blob and on Netlify to Netlify Blobs; locally they are plain
// files under DATA_DIR/uploads. Either way the app serves them from /uploads.

const KEY_PATTERN = /^(posts|profiles)\/[a-f0-9]{32}\.(jpg|png|gif|webp)$/;
const CONTENT_TYPES = { jpg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp' };
const localDir = path.join(config.dataDir, 'uploads');

const backends = {
  disk: {
    async put(key, buffer) {
      await fs.promises.mkdir(path.dirname(path.join(localDir, key)), { recursive: true });
      await fs.promises.writeFile(path.join(localDir, key), buffer);
    },
    async get(key) {
      try {
        return await fs.promises.readFile(path.join(localDir, key));
      } catch (err) {
        if (err.code === 'ENOENT') return null;
        throw err;
      }
    },
    remove: (key) => fs.promises.rm(path.join(localDir, key), { force: true }),
  },

  'netlify-blobs': {
    store: () => require('@netlify/blobs').getStore('uploads'),
    put(key, buffer) {
      return this.store().set(key, buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength));
    },
    async get(key) {
      const data = await this.store().get(key, { type: 'arrayBuffer' });
      return data ? Buffer.from(data) : null;
    },
    remove(key) { return this.store().delete(key); },
  },

  'vercel-blob': {
    async put(key, buffer, contentType) {
      await require('@vercel/blob').put(key, buffer, { access: 'public', addRandomSuffix: false, contentType });
    },
    async get(key) {
      const { head, BlobNotFoundError } = require('@vercel/blob');
      let info;
      try {
        info = await head(key);
      } catch (err) {
        if (err instanceof BlobNotFoundError) return null;
        throw err;
      }
      const res = await fetch(info.url);
      return res.ok ? Buffer.from(await res.arrayBuffer()) : null;
    },
    remove: (key) => require('@vercel/blob').del(key),
  },
};
const backend = backends[config.storage];

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: config.maxUploadBytes, files: 2 },
});

// Detect the real image type from the file's leading bytes; never trust the
// client-supplied filename or MIME type.
function sniffImage(buf) {
  if (buf.length < 12) return null;
  if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'jpg';
  if (buf.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'png';
  if (buf.subarray(0, 4).toString('latin1') === 'GIF8') return 'gif';
  if (buf.subarray(0, 4).toString('latin1') === 'RIFF' && buf.subarray(8, 12).toString('latin1') === 'WEBP') return 'webp';
  return null;
}

// Stores an uploaded image and returns its key, e.g. "posts/3f2a….jpg".
// Throws a user-facing error for anything that is not an image.
async function saveImage(file, folder) {
  const ext = sniffImage(file.buffer);
  if (!ext) {
    const err = new Error('Images must be JPEG, PNG, GIF or WebP files.');
    err.userFacing = true;
    throw err;
  }
  const key = `${folder}/${crypto.randomBytes(16).toString('hex')}.${ext}`;
  await backend.put(key, file.buffer, CONTENT_TYPES[ext]);
  return key;
}

async function readImage(key) {
  if (!KEY_PATTERN.test(key)) return null;
  const body = await backend.get(key);
  return body ? { body, contentType: CONTENT_TYPES[key.split('.').pop()] } : null;
}

// Best effort: a failed delete only leaves an orphaned file behind.
async function removeImage(key) {
  if (!key || !KEY_PATTERN.test(key)) return;
  try {
    await backend.remove(key);
  } catch (err) {
    console.error('Could not delete image', key, err.message);
  }
}

module.exports = { upload, saveImage, readImage, removeImage };
