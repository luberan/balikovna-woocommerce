const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');

const site = process.env.BALIKOVNA_TEST_SITE;
if (!site || !fs.existsSync(path.join(site, '.balikovna-integration-site'))) throw new Error('Missing isolated test site.');
const url = new URL(process.env.BALIKOVNA_TEST_BASE_URL || 'http://127.0.0.1:8873');
if (url.hostname !== '127.0.0.1') throw new Error('Refusing a nonlocal server binding.');
const logPath = path.join(site, 'php-server.log');
const serverLog = fs.openSync(logPath, 'a');
const php = spawn(process.env.BALIKOVNA_PHP_BINARY || 'php', [
  ...JSON.parse(process.env.BALIKOVNA_PHP_ARGS || '[]'),
  '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0',
  '-S', `${url.hostname}:${url.port || 8873}`, '-t', site,
], { stdio: ['ignore', serverLog, serverLog] });
let stopping = false;
for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => { stopping = true; php.kill(); process.exit(0); });
}
php.on('error', error => { throw error; });
php.on('exit', (code, signal) => {
  if (stopping) return;
  console.error(`PHP test server exited unexpectedly: ${signal || `code ${code}`}`);
  console.error(fs.readFileSync(logPath, 'utf8').slice(-16384));
  process.exit(code || 1);
});