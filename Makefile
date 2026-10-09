# Makefile — dev-environment orchestration only.
#
# Scoped strictly to managing the Docker Compose stack. Tests, lint, build, and
# format use the native tools (composer test, npm run lint, npm run build, etc.).
# Make does NOT wrap those. See AGENTS.md and the standards repo for rationale.
#
# Adapter targets called by agents and cloud runners:
#   make setup    make start    make stop    make reset    make shell    make logs

DC := docker compose -f .devcontainer/docker-compose.yml

# Override per run, e.g. `make setup PHP_VERSION=8.2 WP_VERSION=7.0 WP_PORT=9000`.
# See .devcontainer/docker-compose.yml for the full list and defaults.
export PHP_VERSION WP_VERSION WP_PORT MAILPIT_PORT WP_URL XDEBUG_MODE

.PHONY: help setup start stop restart reset shell logs

help:  ## Show this help
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ {printf "  %-12s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup:  ## Build, start, install deps, install WordPress with WP-CLI, prepare tests
	$(DC) up -d --build
	$(DC) exec -T -w /workspace app bash .devcontainer/post-create.sh
	@[ -f .env ] || ( [ -f .env.example ] && cp .env.example .env && echo "Copied .env.example -> .env" )

start:  ## Start dev containers
	$(DC) up -d

stop:  ## Stop dev containers
	$(DC) down

restart: stop start  ## Restart dev containers

reset:  ## Destroy volumes and rebuild (DEV ONLY — destroys local DB)
	$(DC) down -v
	$(MAKE) setup

shell:  ## Drop into the app container
	$(DC) exec -w /workspace app bash

logs:  ## Tail container logs
	$(DC) logs -f
