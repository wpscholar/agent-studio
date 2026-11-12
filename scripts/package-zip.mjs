// Build a release zip using .distignore + rsync.
//
// Prereqs (run by .github/workflows/release.yml and ci.yml build job
// BEFORE this script):
//   composer install --no-dev --prefer-dist --no-interaction
//   npm ci
//   npm run build
//
// rsync against the working tree so just-built assets in build/ are included.

import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';

const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
const slug = pkg.name;
const version = pkg.version;

const buildDir = resolve('build');
const distRoot = resolve('dist');
const stageDir = join(distRoot, slug);
const zipPath = join(buildDir, `${slug}-${version}.zip`);

mkdirSync(buildDir, { recursive: true });
mkdirSync(distRoot, { recursive: true });
if (existsSync(stageDir)) rmSync(stageDir, { recursive: true, force: true });
if (existsSync(zipPath)) rmSync(zipPath);

// rsync uses the first rule that matches a path, so .distinclude must come
// before .distignore for its re-includes to win.
const args = ['-rLptc', '--delete'];
if (existsSync('.distinclude')) args.push('--include-from=.distinclude');
args.push('--exclude-from=.distignore');
args.push('./', `${stageDir}/`);

execSync(`rsync ${args.join(' ')}`, { stdio: 'inherit' });
execSync(`zip -rq "${zipPath}" "${slug}"`, { cwd: distRoot, stdio: 'inherit' });
rmSync(stageDir, { recursive: true, force: true });

console.log(`Built ${zipPath}`);
