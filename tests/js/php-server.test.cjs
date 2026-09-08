const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../e2e/serve.cjs'), 'utf8');

function startServer() {
  const child = new EventEmitter();
  child.kill = () => child.emit('exit', null, 'SIGTERM');
  const exits = [];
  const errors = [];
  const signals = new Map();
  const starts = [];
  const modules = {
    'node:fs': {
      existsSync: () => true,
      openSync: () => 1,
      readFileSync: () => 'PHP failure details',
    },
    'node:path': path,
    'node:child_process': { spawn: (...args) => { starts.push(args); return child; } },
  };
  vm.runInNewContext(source, {
    require: name => modules[name],
    URL,
    console: { error: message => errors.push(message) },
    process: {
      env: { BALIKOVNA_TEST_SITE: '/isolated' },
      on: (signal, handler) => signals.set(signal, handler),
      exit: code => exits.push(code),
    },
  }, { filename: 'serve.cjs' });
  return { child, exits, errors, signals, starts };
}

test('PHP checkout server disables JIT without disabling OPcache', () => {
  const server = startServer();
  const [binary, args] = server.starts[0];
  assert.equal(binary, 'php');
  for (const setting of ['opcache.jit=disable', 'opcache.jit_buffer_size=0']) {
    assert.ok(args.includes(setting));
    assert.equal(args[args.indexOf(setting) - 1], '-d');
  }
  assert.ok(!args.includes('opcache.enable=0'));
});

test('PHP server termination by signal fails the launcher and exposes its log', () => {
  const server = startServer();
  server.child.emit('exit', null, 'SIGSEGV');
  assert.deepEqual(server.exits, [1]);
  assert.match(server.errors.join('\n'), /SIGSEGV/);
  assert.match(server.errors.join('\n'), /PHP failure details/);
});

test('PHP server exit codes are preserved, and unexpected clean exits fail', () => {
  for (const code of [0, 7]) {
    const server = startServer();
    server.child.emit('exit', code, null);
    assert.deepEqual(server.exits, [code || 1]);
    assert.match(server.errors.join('\n'), new RegExp(`code ${code}`));
  }
});

test('Requested PHP server shutdown remains successful', () => {
  const server = startServer();
  server.signals.get('SIGTERM')();
  assert.deepEqual(server.exits, [0]);
  assert.deepEqual(server.errors, []);
});