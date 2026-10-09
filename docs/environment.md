# Environment

## Start

One command, from a terminal:

```bash
make setup
```

Or open the repo in VS Code/Cursor ("Reopen in Container") or Codespaces. Both
paths build the same image and run `.devcontainer/post-create.sh`, which installs
Composer and npm dependencies, installs WordPress with WP-CLI, activates the
plugin and prepares the integration test scaffolding. It is safe to re-run.

You get:

- WordPress 7.1 on PHP 8.4 at http://localhost:8484 (admin / password)
- MySQL 8
- Mailpit at http://localhost:8025
- An `app` container with PHP, Node, Composer and WP-CLI (`make shell`, then `wp ...`)

Change versions or ports with environment variables:

```bash
make setup PHP_VERSION=8.2 WP_VERSION=7.0 WP_PORT=9000 MAILPIT_PORT=9025
```

`PHP_VERSION` sets PHP for both the app and the web server. `WP_VERSION` is any
`wordpress` image minor tag. Core files live in the `wp-data` volume, so after
changing `WP_VERSION` on an existing site run `make reset`. A port change only
needs `make setup` again; it updates the site URL.

The plugin is bind-mounted at `wp-content/plugins/agent-studio`. The app
container keeps its own `node_modules` volume so Linux builds of native
packages never replace the host's.

## Install

```bash
composer install
npm install
npm run build
```

## Reset

```bash
make reset   # destroys the local database and site, then runs setup
```

## Offline

- `docker compose pull` while online to cache images.
- Tests and lint run offline.
- Playwright tests run offline against the local WordPress.
- AI/Promptfoo evals require network and degrade gracefully.

## Fallback runtimes

- `wp-env` is allowed for quick demos.
- WordPress Playground CLI for snapshot previews.
