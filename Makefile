# Makefile — dev-environment orchestration only.
#
# Scoped strictly to managing the Docker Compose stack. Tests, lint, build, and
# format use the native tools (composer test, npm run lint, npm run build, etc.).
# Make does NOT wrap those. See AGENTS.md and the standards repo for rationale.
#
# Adapter targets called by agents and cloud runners:
#   make setup    make start    make stop    make reset    make shell    make logs

DC := docker compose -f .devcontainer/docker-compose.yml

.PHONY: help setup start stop restart reset shell logs

help:  ## Show this help
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ {printf "  %-12s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup:  ## First-time setup (build images, start stack, install deps, copy env)
	$(DC) build
	$(DC) up -d
	$(DC) exec -T app composer install --prefer-dist --no-interaction || true
	$(DC) exec -T app npm ci || true
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
	$(DC) exec app bash

logs:  ## Tail container logs
	$(DC) logs -f
