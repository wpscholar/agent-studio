ARG PHP_VERSION=8.4
# Keep in step with .nvmrc. Bookworm's older glibc runs on bookworm and trixie bases.
ARG NODE_VERSION=24

FROM node:${NODE_VERSION}-bookworm-slim AS node

FROM mcr.microsoft.com/devcontainers/php:${PHP_VERSION}

# Node is installed here rather than as a devcontainer feature, so plain
# `docker compose` (make setup) and the devcontainer CLI build the same image.
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# mysqli for WordPress and the integration tests, zip for WP-CLI downloads,
# a MySQL client for `wp db`, and WP-CLI. The MariaDB client rejects MySQL 8's self-signed certificate;
# this is a local dev database, so client TLS is off.
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli zip \
    && printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/devcontainer.cnf \
    && curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# The container keeps its own node_modules (a named volume) so Linux builds of
# native packages never replace the host's. A new volume copies this ownership.
RUN mkdir -p /workspace/node_modules && chown vscode:vscode /workspace/node_modules

USER vscode

# The bind-mounted repo is owned by the host user; let git use it.
RUN git config --global --add safe.directory /workspace
