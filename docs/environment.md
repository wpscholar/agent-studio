# Environment

## Start

Open in Codespaces (preferred) or VS Code/Cursor with "Reopen in Container".

The container runs:

- WordPress 6.8 at http://localhost
- MySQL 8
- Mailpit at http://localhost:8025

The plugin is bind-mounted at `wp-content/plugins/wp-agents`.

## Install

```bash
composer install
npm install
npm run build
```

## Reset

```bash
docker compose down -v
docker compose up -d
```

## Offline

- `docker compose pull` while online to cache images.
- Tests and lint run offline.
- Playwright tests run offline against the local WordPress.
- AI/Promptfoo evals require network and degrade gracefully.

## Fallback runtimes

- `wp-env` is allowed for quick demos.
- WordPress Playground CLI for snapshot previews.
