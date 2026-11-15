'use strict';
const http = require('http');
const config = require('./src/config');
const { app, sessionParser } = require('./src/app');
const chat = require('./src/chat');

const server = http.createServer(app);
chat.attach(server, sessionParser);

server.listen(config.port, () => {
  console.log(`Helpie is running at http://localhost:${config.port}`);
});

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    server.close(() => process.exit(0));
    setTimeout(() => process.exit(0), 2000).unref();
  });
}
