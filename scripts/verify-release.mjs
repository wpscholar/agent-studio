// Release checks run by .github/workflows/release.yml.
//
//   node scripts/verify-release.mjs version <tag>
//     The tag must match every place the plugin states its version. WordPress
//     compares the release tag with the installed header Version, so a mismatch
//     would offer the same update forever (or never).
//
//   node scripts/verify-release.mjs zip <tag> <path-to-zip>
//     The zip must install as a working plugin: one top-level folder named for
//     the slug, production vendor/ and built assets present, dev files absent.

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const SLUG = 'agent-studio';
const MAIN = `${SLUG}.php`;

const [mode, tag, zipPath] = process.argv.slice(2);
const errors = [];

function fail(message) {
	errors.push(message);
}

function versionsIn(phpSource) {
	return {
		header: phpSource.match(/^\s*\*\s*Version:\s*(\S+)/m)?.[1],
		constant: phpSource.match(
			/define\(\s*'AGENT_STUDIO_VERSION'\s*,\s*'([^']+)'\s*\)/,
		)?.[1],
	};
}

if (!tag || !/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/.test(tag)) {
	console.error(
		`Release tags are bare semver such as 1.2.3 or 2.0.0-rc.1; got "${tag ?? ''}".`,
	);
	process.exit(1);
}

if (mode === 'version') {
	const { header, constant } = versionsIn(readFileSync(MAIN, 'utf8'));
	const pkg = JSON.parse(readFileSync('package.json', 'utf8')).version;
	for (const [where, value] of [
		[`${MAIN} header "Version:"`, header],
		[`${MAIN} AGENT_STUDIO_VERSION`, constant],
		['package.json "version"', pkg],
	]) {
		if (value !== tag)
			fail(`${where} is ${value ?? 'missing'}, but the tag is ${tag}.`);
	}
	if (
		!/^\s*\*\s*Update URI:\s*https:\/\/github\.com\/\S+/m.test(
			readFileSync(MAIN, 'utf8'),
		)
	) {
		fail(
			`${MAIN} has no "Update URI" header pointing at GitHub, so sites will not see this release.`,
		);
	}
} else if (mode === 'zip') {
	if (!zipPath) {
		console.error('Usage: verify-release.mjs zip <tag> <zip>');
		process.exit(1);
	}
	const entries = execFileSync('unzip', ['-Z1', zipPath], { encoding: 'utf8' })
		.split('\n')
		.filter(Boolean);
	const has = (path) => entries.includes(`${SLUG}/${path}`);

	const roots = new Set(entries.map((e) => e.split('/')[0]));
	if (roots.size !== 1 || !roots.has(SLUG)) {
		fail(
			`The zip must hold a single "${SLUG}/" folder; found: ${[...roots].join(', ')}.`,
		);
	}
	for (const required of [
		MAIN,
		'uninstall.php',
		'vendor/autoload.php',
		'build/assets/admin.js',
	]) {
		if (!has(required)) fail(`The zip is missing ${SLUG}/${required}.`);
	}
	for (const forbidden of [
		'vendor/phpunit/',
		'vendor/phpstan/',
		'vendor/squizlabs/',
		'node_modules/',
		'tests/',
		'.github/',
		'composer.json',
		'package.json',
	]) {
		if (entries.some((e) => e.startsWith(`${SLUG}/${forbidden}`))) {
			fail(`The zip should not contain ${SLUG}/${forbidden}.`);
		}
	}
	if (entries.some((e) => /\.zip$/.test(e)))
		fail('The zip contains another zip.');

	if (has(MAIN)) {
		const shipped = execFileSync('unzip', ['-p', zipPath, `${SLUG}/${MAIN}`], {
			encoding: 'utf8',
		});
		const { header, constant } = versionsIn(shipped);
		if (header !== tag || constant !== tag) {
			fail(
				`The zipped ${MAIN} says version ${header} / ${constant}, but the tag is ${tag}.`,
			);
		}
	}
} else {
	console.error('Usage: verify-release.mjs version <tag> | zip <tag> <zip>');
	process.exit(1);
}

if (errors.length) {
	for (const e of errors) console.error(`::error::${e}`);
	process.exit(1);
}
console.log(`Release ${tag}: ${mode} checks passed.`);
