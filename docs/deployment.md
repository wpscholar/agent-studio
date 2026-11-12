# Deployment

## Release

Releases start from a GitHub release, not from pushing a tag.

1. Bump the version in all three places: the `Version:` header and `AGENT_STUDIO_VERSION` in `agent-studio.php`, and `version` in `package.json`. `PluginFileTest` fails if they disagree.
2. Merge to `main`.
3. On GitHub, draft a new release. Create its tag there with the same version (`1.2.3`; a leading `v` is allowed), write or generate the notes, and **Publish**. Tick **Set as a pre-release** for test builds; sites never receive pre-releases.

Publishing (the `release: published` event) runs `release.yml`. Drafts do not. The workflow:

1. Checks out the release's tag, and fails unless that version matches all three version strings (`scripts/verify-release.mjs version`). Sites compare the release version with the installed header, so a mismatch would offer the same update forever, or never offer it.
2. `composer install --no-dev --optimize-autoloader` populates `vendor/`.
3. `npm ci && npm run build` populates `build/` with compiled assets.
4. `npm run package` rsyncs the working tree into `dist/<slug>/` honoring `.distignore` (and `.distinclude` if present), then zips it to `build/<slug>-<version>.zip`.
5. Checks the zip (`scripts/verify-release.mjs zip`): it has a single `agent-studio/` folder, `vendor/autoload.php` and the built admin script, the right version, and no dev files.
6. Uploads the zip to the release as an asset.

If any step fails, the workflow turns the release back into a draft, so sites are never pointed at a release without a zip. Fix the problem (move the tag if the code must change), then publish the draft again to rerun it.

## Updates on installed sites

The `Update URI: https://github.com/wpscholar/agent-studio` header tells WordPress to ask the plugin, not WordPress.org, about updates. `src/WordPress/Updates/PluginUpdates.php` answers it:

- It reads the latest full release from the GitHub API (drafts and pre-releases are never offered) and checks at most every 6 hours, or once an hour after a failed check or when the latest release has no zip yet (the workflow attaches it a few minutes after publishing). **Check again** on Dashboard → Updates checks right away.
- It offers only the attached `agent-studio-<version>.zip`. A release without that zip offers nothing, because GitHub's source archives lack `vendor/` and the built assets.
- The usual notices, **View details** (with the release notes), one-click updates and automatic updates all work.
- Before installing, it checks that the unpacked zip contains `agent-studio.php`. It then moves the update into the folder the plugin is installed in now, so the plugin stays active even if that folder has another name.
- Database tables and content migrate on the next page load after the update (`Schema::upgradeIfNeeded()` and `PostTypeRegistrar::migrate()`), not only on activation.

### While the repository is private

GitHub's API does not show releases of a private repository without a token. Add a fine-grained token with read-only **Contents** access to this repository in `wp-config.php`:

```php
define('AGENT_STUDIO_GITHUB_TOKEN', 'github_pat_...');
```

Or provide it through the `agent_studio_github_token` filter. The token is sent only to `api.github.com`. GitHub then redirects downloads to a signed address, and the token is not sent there. Once the repository is public, no token is needed.

## Install on a site

- Manual: download the zip from the GitHub release; upload via WP admin.
- Composer (recommended for site repos):
  ```json
  {
    "repositories": [
      { "type": "vcs", "url": "https://github.com/wpscholar/agent-studio" }
    ],
    "require": { "wpscholar/agent-studio": "^0.1" }
  }
  ```

## wordpress.org publishing

Not configured by default. Add an `svn-publish.yml` workflow when ready, gated by manual approval.
