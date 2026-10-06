#!/usr/bin/env bash
set -euo pipefail

cd /workspace

[ -f composer.json ] && composer install --prefer-dist --no-interaction
[ -f package.json ] && npm ci

# Wait for WordPress to be ready
for i in $(seq 1 30); do
  if curl -fsS --max-time 2 http://wordpress/ > /dev/null 2>&1; then break; fi
  sleep 2
done

echo "Devcontainer ready. Plugin mounted at wp-content/plugins/wp-agents."
