# Deployment

## Release

Tag `X.Y.Z` (bare semver, no `v` prefix). The `release.yml` workflow:

1. `composer install --no-dev` populates `vendor/`.
2. `npm ci && npm run build` populates `build/` with compiled assets.
3. `npm run package` rsyncs the working tree into `dist/<slug>/` honoring `.distignore` (and `.distinclude` if present), then zips it to `build/<slug>-<version>.zip`.
4. The zip is attached to the GitHub release.

The trigger uses `tags: ['[0-9]+.[0-9]+.[0-9]+*']`, which matches `1.0.0`, `1.4.2`, `2.0.0-rc.1`, etc.

## Install on a site

- Manual: download the zip from the GitHub release; upload via WP admin.
- Composer (recommended for site repos):
  ```json
  {
    "repositories": [
      { "type": "vcs", "url": "https://github.com/wpscholar/wp-agents" }
    ],
    "require": { "wpscholar/wp-agents": "^0.1" }
  }
  ```

## wordpress.org publishing

Not configured by default. Add an `svn-publish.yml` workflow when ready, gated by manual approval.
