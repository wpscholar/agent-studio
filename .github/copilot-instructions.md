# Copilot instructions

This is a WordPress plugin built from [`wpscholar/template-wordpress-plugin`](https://github.com/wpscholar/template-wordpress-plugin), implementing [`wpscholar/standards`](https://github.com/wpscholar/standards).

The canonical agent instruction file is [`AGENTS.md`](../AGENTS.md). Read it first — it has full project context, command list, and permission tiers.

## Critical guardrails (summary; see AGENTS.md for full)

**Never:**
- Read or modify production secrets.
- Deploy to production (`wp deploy`, SVN publish, etc.).
- `cat .env`, `echo $SECRET`, or any command that prints secret values.
- Commit `.env`, `.env.local`, `.env.testing`.
- Bypass CI requirements or disable security checks.

**Requires approval first:**
- Changes to `.github/workflows/`, packaging script, or auth/security code.
- Destructive DB operations on shared data.
- Dependency major upgrades.
- Modifying `.cursor/environment.json` or this file (they're part of the agent contract).

**Allowed without approval:**
- Install deps, run tests/lint/format/static analysis, modify code and docs, run WP-CLI against the dev site, build assets.

## Common commands

- Tests: `composer test && npm test`
- Lint: `composer lint && npm run lint && npm run format:check`
- Static analysis: `composer analyze`
- Build: `npm run build`
- Package release zip: `npm run package` (rsync + zip honoring `.distignore`)
- Local dev stack: `make setup`, `make start`, `make stop`, `make reset`, `make shell`, `make logs`

Full command list and project-specific notes are in [`AGENTS.md`](../AGENTS.md) and [`docs/`](../docs/).
