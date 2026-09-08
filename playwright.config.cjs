const { defineConfig } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const site = process.env.BALIKOVNA_TEST_SITE;
if (!site || !fs.existsSync(path.join(site, '.balikovna-integration-site'))) {
  throw new Error('Prepare an isolated BALIKOVNA_TEST_SITE before running E2E tests.');
}
const baseURL = process.env.BALIKOVNA_TEST_BASE_URL || 'http://127.0.0.1:8873';
if (new URL(baseURL).hostname !== '127.0.0.1') throw new Error('E2E must run on localhost.');

module.exports = defineConfig({
  testDir: './tests/e2e',
  testMatch: '*.spec.cjs',
  workers: 1,
  retries: 0,
  timeout: 120000,
  expect: { timeout: 20000 },
  reporter: 'list',
  use: {
    baseURL,
    browserName: 'chromium',
    viewport: { width: 1365, height: 900 },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  webServer: {
    command: 'node tests/e2e/serve.cjs',
    url: `${baseURL}/fixture-data.json`,
    reuseExistingServer: false,
    timeout: 30000,
  },
});