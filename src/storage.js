'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const multer = require('multer');
const config = require('./config');

// Uploaded images. On Netlify they are kept in Netlify Blobs (functions have
// no persistent disk); locally they are plain files under DATA_DIR/uploads.

const KEY_PATTERN = /^(posts|profiles)\/[a-f0-9]{32}\.(jpg|png|gif|webp)$/;
const CONTENT_TYPES = { jpg: 'image/jpeg', png: 'image/png', gif: 'image/gif', webp: 'image/webp' };
const localDir = path.join(config.dataDir, 'uploads');

function blobStore() {
  const { getStore } = require('@netlify/blobs');
  return getStore('uploads');
}

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
  if (config.onNetlify) {
    const data = file.buffer.buffer.slice(file.buffer.byteOffset, file.buffer.byteOffset + file.buffer.byteLength);
    await blobStore().set(key, data);
  } else {
    await fs.promises.mkdir(path.join(localDir, folder), { recursive: true });
    await fs.promises.writeFile(path.join(localDir, key), file.buffer);
  }
  return key;
}

async function readImage(key) {
  if (!KEY_PATTERN.test(key)) return null;
  const contentType = CONTENT_TYPES[key.split('.').pop()];
  if (config.onNetlify) {
    const data = await blobStore().get(key, { type: 'arrayBuffer' });
    return data ? { body: Buffer.from(data), contentType } : null;
  }
  try {
    return { body: await fs.promises.readFile(path.join(localDir, key)), contentType };
  } catch (err) {
    if (err.code === 'ENOENT') return null;
    throw err;
  }
}

// Best effort: a failed delete only leaves an orphaned file behind.
async function removeImage(key) {
  if (!key || !KEY_PATTERN.test(key)) return;
  try {
    if (config.onNetlify) await blobStore().delete(key);
    else await fs.promises.rm(path.join(localDir, key), { force: true });
  } catch (err) {
    console.error('Could not delete image', key, err.message);
  }
}

module.exports = { upload, saveImage, readImage, removeImage };
