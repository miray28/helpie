// Runs the whole Express app as a single Netlify Function. Static files in
// public/ are served by the CDN; every other URL comes here (see `config`).
//
// Netlify hands the function a web Request and expects a web Response, while
// Express speaks Node's req/res. serverless-http bridges the two through an
// API-Gateway-shaped event, so this file only translates Request -> event and
// result -> Response.
import serverless from 'serverless-http';
import appModule from '../../src/app.js';

const handler = serverless(appModule.app, { binary: ['image/*'] });

export default async (request) => {
  const url = new URL(request.url);
  const hasBody = !['GET', 'HEAD'].includes(request.method);
  const body = hasBody ? Buffer.from(await request.arrayBuffer()) : null;

  const query = {};
  for (const key of new Set(url.searchParams.keys())) query[key] = url.searchParams.getAll(key);

  const result = await handler({
    httpMethod: request.method,
    path: url.pathname,
    // Tell Express the original scheme so it will issue Secure cookies.
    headers: { 'x-forwarded-proto': url.protocol.replace(':', ''), ...Object.fromEntries(request.headers) },
    queryStringParameters: Object.fromEntries(url.searchParams),
    multiValueQueryStringParameters: query,
    body: body && body.length ? body.toString('base64') : null,
    isBase64Encoded: Boolean(body && body.length),
    requestContext: {},
  }, {});

  const headers = new Headers();
  for (const [name, value] of Object.entries(result.headers || {})) {
    for (const v of Array.isArray(value) ? value : [value]) headers.append(name, String(v));
  }
  for (const [name, values] of Object.entries(result.multiValueHeaders || {})) {
    headers.delete(name);
    for (const v of values) headers.append(name, String(v));
  }

  const noBody = [204, 304].includes(result.statusCode) || request.method === 'HEAD';
  const payload = noBody || result.body == null
    ? null
    : result.isBase64Encoded ? Buffer.from(result.body, 'base64') : result.body;
  return new Response(payload, { status: result.statusCode, headers });
};

export const config = {
  path: '/*',
  preferStatic: true,
};
