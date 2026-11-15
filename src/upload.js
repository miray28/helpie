'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const multer = require('multer');
const config = require('./config');

for (const dir of ['posts', 'profiles']) {
  fs.mkdirSync(path.join(config.uploadDir, dir), { recursive: true });
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

// Saves an uploaded image and returns its path relative to the upload dir,
// e.g. "posts/3f2a….jpg". Throws a user-facing error for non-images.
function saveImage(file, folder) {
  const ext = sniffImage(file.buffer);
  if (!ext) {
    const err = new Error('Images must be JPEG, PNG, GIF or WebP files.');
    err.userFacing = true;
    throw err;
  }
  const rel = `${folder}/${crypto.randomBytes(16).toString('hex')}.${ext}`;
  fs.writeFileSync(path.join(config.uploadDir, rel), file.buffer);
  return rel;
}

function removeImage(rel) {
  if (!rel || !/^(posts|profiles)\/[a-f0-9]{32}\.(jpg|png|gif|webp)$/.test(rel)) return;
  fs.rm(path.join(config.uploadDir, rel), { force: true }, () => {});
}

module.exports = { upload, saveImage, removeImage };
