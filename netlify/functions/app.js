'use strict';
// Runs the whole Express app as a single Netlify Function. Static files in
// public/ are served by the CDN; every other URL is routed here (netlify.toml).
const serverless = require('serverless-http');
const { connectLambda } = require('@netlify/blobs');
const { app } = require('../../src/app');

const handler = serverless(app, {
  binary: ['image/*'],
});

exports.handler = async (event, context) => {
  // Gives @netlify/blobs its credentials for this request (absent in local tests).
  if (event.blobs) connectLambda(event);
  return handler(event, context);
};
