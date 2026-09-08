const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');

const site = process.env.BALIKOVNA_TEST_SITE;
if (!site || !fs.existsSync(path.join(site, '.balikovna-integration-site'))) throw new Error('Missing isolated test site.');
const url = new URL(process.env.BALIKOVNA_TEST_BASE_URL || 'http://127.0.0.1:8873');
if (url.hostname !== '127.0.0.1') throw new Error('Refusing a nonlocal server binding.');
const serverLog = fs.openSync(path.join(site, 'php-server.log'), 'a');
const php = spawn(process.env.BALIKOVNA_PHP_BINARY || 'php', [
  ...JSON.parse(process.env.BALIKOVNA_PHP_ARGS || '[]'),
  '-S', `${url.hostname}:${url.port || 8873}`, '-t', site,
], { stdio: ['ignore', serverLog, serverLog] });
for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => { php.kill(); process.exit(0); });
}
php.on('error', error => { throw error; });
php.on('exit', code => process.exit(code || 0));