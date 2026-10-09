# AGENTS.md

This is a WordPress plugin built from `wpscholar/template-wordpress-plugin`, implementing `wpscholar/standards`.

## Architecture

For non-trivial plugins, follow the **portable hexagonal architecture** (Domain → Application → Contracts → Infrastructure → WordPress Integration). Business logic in framework-agnostic PHP; WordPress APIs isolated behind adapters.

- Project-local pointer: [`docs/architecture.md`](./docs/architecture.md)
- Full architecture standard: https://github.com/wpscholar/standards/blob/main/docs/architecture/wordpress-plugin.md
- ADR: https://github.com/wpscholar/standards/blob/main/docs/decisions/0008-portable-php-architecture.md

If you're working on Domain/Application code, **do not** introduce `WP_Post`, `WP_User`, `wpdb`, `get_option()`, `add_action()`, or other WordPress functions. Use Contracts and inject Infrastructure adapters instead.

## Quick commands

- Start environment: `make setup` (or "Reopen in Container" / Codespaces). See `docs/environment.md`.
- Install: `composer install && npm install`
- Build: `npm run build`
- Tests: `composer test && npm test`
  - PHP unit (no WP bootstrap): `composer test:unit`
  - PHP integration (with WP): `composer test:integration`
- E2E: `npm run test:e2e`
- Lint: `composer lint && npm run lint && npm run format:check`
- Static analysis: `composer analyze`
- PHP compatibility check: `composer compat`
- Package zip: `npm run package`

See `docs/environment.md`, `docs/testing.md`, `docs/deployment.md`, `docs/architecture.md`.

## Project notes

- PHP 8.2+ floor. PSR-12 is the primary coding standard; WordPress security and i18n sniffs apply selectively.
- PSR-4 autoloaded source under `src/`. For non-trivial plugins, organize as `src/Domain/`, `src/Application/`, `src/Contracts/`, `src/Infrastructure/`, `src/WordPress/` per the architecture doc.
- Main plugin file at the repo root is a thin bootstrap.
- Built assets are generated; don't commit them to `main` (they go in the release zip).
- Use WP-CLI inside the devcontainer.
- See WordPress agent-skills: https://github.com/WordPress/agent-skills

## Agent Studio specifics

- Agent and skill post types use `map_meta_cap => false` on purpose. Never turn it on: it makes `manage_options` behave like a per-post capability.
- `tests/unit/` must not load WordPress. `tests/integration/` extends `WP_UnitTestCase` and fakes only the model (`ScriptedModelClient`).
- `ArchitectureTest` fails the build if Domain or Application use WordPress APIs.
- The roadmap lives in `README.md`. Ship what is marked v1; do not build roadmap items without a request.

## Permissions

**Allowed without approval.** Install dependencies. Run tests, lint, format, static analysis. Modify code and docs. Generate fixtures. Build assets. Run WP-CLI (`wp ...`) against the dev site. Inspect logs. Add new Domain/Application code following the architecture.

**Requires explicit approval.** Destructive DB ops on shared/staging data. Changes to `.github/workflows/`, packaging script, plugin auth/security code. Dependency major upgrades. Changes to release zip exclusion list. Introducing WordPress dependencies into Domain or Application layers.

**Never allowed.** Read or modify production secrets. Deploy to production / publish to wordpress.org. Rotate credentials. `cat .env`, `echo $SECRET`. Commit `.env`. Disable security checks. Bypass CI requirements.
