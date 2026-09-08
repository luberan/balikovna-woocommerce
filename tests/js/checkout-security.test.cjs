const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

for (const script of ['checkout.js', 'checkout-block.js']) {
  test(`${script}: ignored messages never leak payloads into debug logs`, () => {
    const listeners = {};
    const logs = [];
    const config = { debug: true, selected: [], services: {}, i18n: {} };
    const sandbox = {
      window: { addEventListener: (event, callback) => { listeners[event] = callback; } },
      document: {},
      console: { log: (...args) => { logs.push(args); } },
      jQuery: () => {},
      wp: { element: {}, plugins: { registerPlugin: () => {} }, data: {} },
      wc: { blocksCheckout: {} },
      BalikovnaWC: config,
      BalikovnaWCBlock: config,
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/js', script), 'utf8'), sandbox);
    listeners.message({ origin: 'https://untrusted.example', data: { phone: '+420700000001', message: 'pickerResult', point: { id: 'B10000', name: 'private' } } });
    assert.deepEqual(logs, []);
  });
}