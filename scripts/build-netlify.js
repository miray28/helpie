#!/usr/bin/env node
'use strict';
// Bundles the app and all of its dependencies into one self-contained file,
// netlify/functions/app.mjs, so the deployed function does not depend on how
// the hosting platform resolves node_modules.
const path = require('path');
const esbuild = require('esbuild');

const root = path.join(__dirname, '..');

esbuild.build({
  entryPoints: [path.join(root, 'src', 'netlify-function.mjs')],
  outfile: path.join(root, 'netlify', 'functions', 'app.mjs'),
  bundle: true,
  platform: 'node',
  target: 'node20',
  format: 'esm',
  // Optional native/edge drivers that are never used here.
  external: ['pg-native', 'pg-cloudflare'],
  // The dependencies are CommonJS: give them require/__dirname inside the ES module.
  banner: {
    js: [
      "import { createRequire as __createRequire } from 'node:module';",
      "import { fileURLToPath as __fileURLToPath } from 'node:url';",
      "import { dirname as __pathDirname } from 'node:path';",
      'const require = __createRequire(import.meta.url);',
      'const __filename = __fileURLToPath(import.meta.url);',
      'const __dirname = __pathDirname(__filename);',
    ].join('\n'),
  },
  logLevel: 'info',
}).catch(() => process.exit(1));
