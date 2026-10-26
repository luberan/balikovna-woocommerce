const { execFileSync } = require('node:child_process');

const allowedDependencies = {
  github_actions: new Set([
    'actions/checkout',
    'dependabot/fetch-metadata',
    'googleapis/release-please-action',
    'shivammathur/setup-php',
  ]),
  npm_and_yarn: new Set(['@playwright/test', 'playwright', 'playwright-core']),
};

function autoMergeDecision({ ecosystem, updateType, dependencyNames, dependencyType, maintainerChanges }) {
  if (!['version-update:semver-minor', 'version-update:semver-patch'].includes(updateType)) {
    return { allowed: false, reason: 'Only minor and patch updates can auto-merge.' };
  }
  if (maintainerChanges !== 'false') {
    return { allowed: false, reason: 'Maintainer changes or missing verification require manual review.' };
  }
  const allowlist = Object.hasOwn(allowedDependencies, ecosystem) ? allowedDependencies[ecosystem] : undefined;
  if (!allowlist || (ecosystem === 'npm_and_yarn' && dependencyType !== 'direct:development')) {
    return { allowed: false, reason: 'This ecosystem or dependency type requires manual review.' };
  }
  const names = dependencyNames.split(',').map(name => name.trim());
  if (names.some(name => !allowlist.has(name))) {
    return { allowed: false, reason: 'Every updated dependency must be explicitly allowlisted.' };
  }
  return { allowed: true, reason: 'Allowlisted minor/patch update; required CI checks still apply.' };
}

if (require.main === module) {
  const pr = [process.env.PR_NUMBER, '--repo', process.env.GITHUB_REPOSITORY];
  if (process.argv.includes('--disable')) {
    const state = JSON.parse(execFileSync('gh', ['pr', 'view', ...pr, '--json', 'autoMergeRequest'], { encoding: 'utf8' }));
    if (state.autoMergeRequest) {
      execFileSync('gh', ['pr', 'merge', ...pr, '--disable-auto'], { stdio: 'inherit' });
    }
    console.log('Previous auto-merge is disabled; the current changes must be verified again.');
  } else {
    const decision = autoMergeDecision({
      ecosystem: process.env.ECOSYSTEM,
      updateType: process.env.UPDATE_TYPE,
      dependencyNames: process.env.DEPENDENCY_NAMES || '',
      dependencyType: process.env.DEPENDENCY_TYPE,
      maintainerChanges: process.env.MAINTAINER_CHANGES,
    });
    console.log(decision.reason);
    if (decision.allowed) {
      execFileSync('gh', [
        'pr', 'merge', ...pr, '--auto', '--squash',
        '--match-head-commit', process.env.PR_HEAD_SHA,
      ], { stdio: 'inherit' });
    }
  }
}

module.exports = { autoMergeDecision };
