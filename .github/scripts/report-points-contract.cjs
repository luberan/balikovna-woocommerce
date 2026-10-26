const { execFileSync } = require('node:child_process');

const marker = '<!-- balikovna:pickup-directory-contract -->';

function reportContract(api, { repository, result, runUrl }) {
  if (!['success', 'failure'].includes(result) || !repository || !runUrl) {
    throw new Error('A repository, run URL and successful or failed directory result are required.');
  }
  const endpoint = `repos/${repository}/issues`;
  const issues = api('GET', `${endpoint}?state=all&creator=github-actions%5Bbot%5D&per_page=100`).flat()
    .filter(issue => !issue.pull_request && issue.user?.login === 'github-actions[bot]' && issue.body?.includes(marker));
  const issue = issues.find(candidate => candidate.state === 'open') || issues[0];

  if (result === 'failure') {
    const body = [
      marker,
      'The scheduled pickup directory contract check failed.',
      '',
      `[Latest failed run](${runUrl})`,
      '',
      'Check the workflow logs for API, format, size, parser or runner/setup failures.',
      'This issue is updated instead of creating duplicates, reopened on recurrence, and closed after a successful check.',
    ].join('\n');
    if (issue) {
      api('PATCH', `${endpoint}/${issue.number}`, { state: 'open', body });
      console.log(`Updated directory incident #${issue.number}.`);
    } else {
      const created = api('POST', endpoint, { title: 'Pickup directory contract check failed', body });
      console.log(`Created directory incident #${created.number}.`);
    }
  } else if (issue?.state === 'open') {
    api('POST', `${endpoint}/${issue.number}/comments`, { body: `Directory check recovered: [successful run](${runUrl}).` });
    api('PATCH', `${endpoint}/${issue.number}`, { state: 'closed', state_reason: 'completed' });
    console.log(`Resolved directory incident #${issue.number}.`);
  } else {
    console.log('Directory check passed; there is no open incident.');
  }
}

if (require.main === module) {
  reportContract((method, endpoint, body) => {
    const args = ['api', endpoint, '--method', method];
    if (method === 'GET') {
      args.push('--paginate', '--slurp');
    } else {
      args.push('--input', '-');
    }
    return JSON.parse(execFileSync('gh', args, {
      encoding: 'utf8',
      input: body === undefined ? undefined : JSON.stringify(body),
    }));
  }, {
    repository: process.env.GITHUB_REPOSITORY,
    result: process.env.DIRECTORY_RESULT,
    runUrl: process.env.RUN_URL,
  });
}

module.exports = { marker, reportContract };
