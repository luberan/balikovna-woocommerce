const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const { autoMergeDecision } = require('../../.github/scripts/dependabot-automerge.cjs');
const { marker, reportContract } = require('../../.github/scripts/report-points-contract.cjs');

const metadata = {
  ecosystem: 'github_actions',
  updateType: 'version-update:semver-patch',
  dependencyNames: 'actions/checkout',
  dependencyType: 'direct:production',
  maintainerChanges: 'false',
};

test('Only allowlisted minor/patch GitHub Actions and development Playwright updates auto-merge', () => {
  for (const updateType of ['version-update:semver-minor', 'version-update:semver-patch']) {
    assert.equal(autoMergeDecision({
      ...metadata, updateType,
      dependencyNames: 'actions/checkout, dependabot/fetch-metadata,googleapis/release-please-action,shivammathur/setup-php',
    }).allowed, true);
    assert.equal(autoMergeDecision({
      ...metadata, updateType, ecosystem: 'npm_and_yarn', dependencyType: 'direct:development',
      dependencyNames: '@playwright/test,playwright,playwright-core',
    }).allowed, true);
  }
});

test('Major, Composer, unknown, mixed, production and maintainer-modified updates stay manual', () => {
  for (const change of [
    { updateType: 'version-update:semver-major' },
    { updateType: '' },
    { ecosystem: 'composer' },
    { ecosystem: 'github-actions' },
    { ecosystem: 'npm' },
    { ecosystem: '__proto__' },
    { dependencyNames: '' },
    { dependencyNames: 'actions/checkout,unknown/action' },
    { dependencyNames: 'actions/checkout@v7' },
    { maintainerChanges: 'true' },
    { maintainerChanges: '' },
    { ecosystem: 'npm_and_yarn', dependencyNames: '@playwright/test', dependencyType: 'direct:production' },
    { ecosystem: 'npm_and_yarn', dependencyNames: '@playwright/test', dependencyType: 'indirect' },
    { ecosystem: 'npm_and_yarn', dependencyNames: 'unknown-package', dependencyType: 'direct:development' },
  ]) {
    const decision = autoMergeDecision({ ...metadata, ...change });
    assert.equal(decision.allowed, false, JSON.stringify(change));
    assert.ok(decision.reason);
  }
});

function runPolicy(overrides = {}, execute = () => {}) {
  const calls = [];
  const main = { exports: {} };
  const env = {
    ECOSYSTEM: 'github_actions', UPDATE_TYPE: metadata.updateType,
    DEPENDENCY_NAMES: 'actions/checkout', DEPENDENCY_TYPE: 'direct:production',
    MAINTAINER_CHANGES: 'false', PR_NUMBER: '123', PR_HEAD_SHA: 'a'.repeat(40),
    GITHUB_REPOSITORY: 'owner/repo', ...overrides,
  };
  const requireMock = name => {
    assert.equal(name, 'node:child_process');
    return { execFileSync: (...args) => { calls.push(args); return execute(...args); } };
  };
  requireMock.main = main;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../.github/scripts/dependabot-automerge.cjs'), 'utf8'), {
    require: requireMock, module: main, process: { env }, console: { log: () => {} },
  });
  return calls;
}

test('Auto-merge uses native CI-gated squash merging and pins the verified PR head', () => {
  const [[binary, args, options]] = runPolicy();
  assert.equal(binary, 'gh');
  assert.equal(Array.from(args).join(' '),
    `pr merge 123 --repo owner/repo --auto --squash --match-head-commit ${'a'.repeat(40)}`);
  assert.equal(options.stdio, 'inherit');
  assert.equal(runPolicy({ UPDATE_TYPE: 'version-update:semver-major' }).length, 0);
  assert.throws(() => runPolicy({}, () => { throw new Error('merge rejected'); }), /merge rejected/);
});

function incident(state = 'open', overrides = {}) {
  return { number: 12, state, body: marker, user: { login: 'github-actions[bot]' }, ...overrides };
}

function report(pages, result = 'failure', request = () => ({ number: 99 })) {
  const calls = [];
  reportContract((method, endpoint, body) => {
    calls.push({ method, endpoint, body });
    return method === 'GET' ? pages : request(method, endpoint, body);
  }, { repository: 'owner/repo', result, runUrl: 'https://github.com/owner/repo/actions/runs/123/attempts/2' });
  return calls;
}

test('The first failure creates one identifiable issue linked to the failed attempt', () => {
  const calls = report([[]]);
  assert.match(calls[0].endpoint, /state=all&creator=github-actions%5Bbot%5D&per_page=100/);
  assert.equal(calls.length, 2);
  assert.equal(calls[1].method, 'POST');
  assert.equal(calls[1].endpoint, 'repos/owner/repo/issues');
  assert.match(calls[1].body.body, /<!-- balikovna:pickup-directory-contract -->/);
  assert.match(calls[1].body.body, /runs\/123\/attempts\/2/);
});

test('Repeated failures update the existing issue across paginated results', () => {
  const calls = report([[{ number: 1, body: 'Unrelated' }], [incident()]]);
  assert.equal(calls.length, 2);
  assert.equal(calls[1].method, 'PATCH');
  assert.equal(calls[1].endpoint, 'repos/owner/repo/issues/12');
  assert.equal(calls[1].body.state, 'open');
});

test('A recurring failure reopens the same closed issue rather than creating another', () => {
  const calls = report([[incident('closed')]]);
  assert.equal(calls[1].method, 'PATCH');
  assert.equal(calls[1].body.state, 'open');
});

test('Recovery comments and closes the open incident, but healthy checks do not create issues', () => {
  const calls = report([[incident()]], 'success');
  assert.equal(calls.length, 3);
  assert.equal(calls[1].endpoint, 'repos/owner/repo/issues/12/comments');
  assert.match(calls[1].body.body, /successful run/);
  assert.deepEqual(calls[2].body, { state: 'closed', state_reason: 'completed' });
  assert.equal(report([[]], 'success').length, 1);
  assert.equal(report([[incident('closed')]], 'success').length, 1);
});

test('An open incident is preferred over closed history, and unrelated user issues and PRs are untouched', () => {
  const calls = report([[
    incident('closed', { number: 20 }),
    incident('open', { number: 21, pull_request: {} }),
    incident('open', { number: 22, user: { login: 'someone' } }),
    incident(),
  ]]);
  assert.equal(calls[1].endpoint, 'repos/owner/repo/issues/12');
});

test('Cancelled or missing results and GitHub write failures are surfaced explicitly', () => {
  for (const result of ['cancelled', 'skipped', undefined]) {
    assert.throws(() => reportContract(() => assert.fail('No API call expected'), {
      repository: 'owner/repo', runUrl: 'https://github.com/owner/repo/actions/runs/123', result,
    }), /required/);
  }
  assert.throws(() => report([[]], 'failure', () => { throw new Error('GitHub unavailable'); }), /GitHub unavailable/);
});
