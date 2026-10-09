#!/usr/bin/env bash
# Sets up the environment inside the app container: dependencies, a WordPress
# site installed with WP-CLI, the plugin activated, and the integration test
# scaffolding. Runs on container creation (VS Code, Codespaces, CI) and from
# `make setup`. Safe to re-run.
set -euo pipefail

cd /workspace

# node_modules is a named volume created for the image's vscode user. In CI the
# devcontainer CLI changes vscode's UID to match the runner, so take it over.
if [ ! -w node_modules ]; then
  sudo chown "$(id -u):$(id -g)" node_modules
fi

composer install --prefer-dist --no-interaction
npm ci

# The wordpress container copies core and writes wp-config.php on first start.
for _ in $(seq 1 60); do
  [ -f /var/www/html/wp-config.php ] && break
  sleep 2
done

site() { wp --path=/var/www/html "$@"; }

if ! site core is-installed 2> /dev/null; then
  site core install --url="$WP_URL" --title="Agent Studio" \
    --admin_user=admin --admin_password=password --admin_email=admin@example.com --skip-email
fi
# Follows WP_PORT / WP_URL changes on an existing site.
site option update home "$WP_URL" > /dev/null
site option update siteurl "$WP_URL" > /dev/null
site plugin activate agent-studio

bash bin/install-wp-tests.sh

echo "Ready: $WP_URL (admin / password). Mailpit: http://localhost:${MAILPIT_PORT:-8025}"
