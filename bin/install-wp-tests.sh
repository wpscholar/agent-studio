#!/usr/bin/env bash
# Installs what the integration suite needs: a copy of WordPress core, the
# WordPress PHPUnit test library for the same version, and an empty test
# database. Safe to re-run; it skips work that is already done.
#
# Usage: bin/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version]
#
# Defaults suit the devcontainer: wordpress_test root root db, and the version
# of the dev site at /var/www/html (or the latest release when there is none).
# wp-version may be "latest", X.Y or X.Y.Z.
#
# Needs PHP with mysqli, git and curl. Uses WP-CLI for core when it is present.

set -euo pipefail

DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-root}
DB_HOST=${4-db}
WP_VERSION=${5-}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=${TMPDIR%/}
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}
WP_CORE_DIR=${WP_CORE_DIR%/}
DEVELOP_REPO=https://github.com/WordPress/wordpress-develop.git

if [ -z "$WP_VERSION" ]; then
	# tail: WP-CLI can print PHP deprecation notices to stdout before the version.
	WP_VERSION=$(wp core version --path=/var/www/html 2>/dev/null | tail -n 1)
	WP_VERSION=${WP_VERSION:-latest}
fi
if [ "$WP_VERSION" = latest ]; then
	WP_VERSION=$(curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | grep -o '"version":"[^"]*' | head -1 | cut -d'"' -f4)
fi

# wordpress-develop tags X.Y.0 releases as "X.Y.0"; WordPress itself reports "X.Y".
WP_TESTS_TAG=
for tag in "$WP_VERSION" "$WP_VERSION.0"; do
	if git ls-remote --exit-code --tags "$DEVELOP_REPO" "refs/tags/$tag" > /dev/null; then
		WP_TESTS_TAG=$tag
		break
	fi
done
if [ -z "$WP_TESTS_TAG" ]; then
	echo "No wordpress-develop tag for WordPress $WP_VERSION." >&2
	exit 1
fi

echo "WordPress $WP_VERSION: core in $WP_CORE_DIR, test library in $WP_TESTS_DIR"

install_core() {
	if [ "$(cat "$WP_CORE_DIR/.version" 2>/dev/null)" = "$WP_VERSION" ]; then
		return
	fi
	rm -rf "$WP_CORE_DIR"
	mkdir -p "$WP_CORE_DIR"
	if command -v wp > /dev/null; then
		wp core download --version="$WP_VERSION" --path="$WP_CORE_DIR" --skip-content --quiet
	else
		curl -fsSL "https://wordpress.org/wordpress-$WP_VERSION.tar.gz" | tar -xz --strip-components=1 -C "$WP_CORE_DIR"
	fi
	echo "$WP_VERSION" > "$WP_CORE_DIR/.version"
}

install_test_library() {
	if [ "$(cat "$WP_TESTS_DIR/.version" 2>/dev/null)" = "$WP_VERSION" ]; then
		return
	fi
	local checkout
	checkout=$(mktemp -d)
	# Fetch only tests/phpunit/{includes,data} and the root files of the tag.
	git -c advice.detachedHead=false clone --quiet --depth 1 --filter=blob:none --sparse --branch "$WP_TESTS_TAG" "$DEVELOP_REPO" "$checkout"
	git -C "$checkout" sparse-checkout set tests/phpunit/includes tests/phpunit/data
	rm -rf "$WP_TESTS_DIR"
	mkdir -p "$WP_TESTS_DIR"
	mv "$checkout/tests/phpunit/includes" "$checkout/tests/phpunit/data" "$WP_TESTS_DIR/"
	sed \
		-e "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" \
		-e "s:__DIR__ . '/src/':'$WP_CORE_DIR/':" \
		-e "s/youremptytestdbnamehere/$DB_NAME/" \
		-e "s/yourusernamehere/$DB_USER/" \
		-e "s/yourpasswordhere/$DB_PASS/" \
		-e "s|localhost|$DB_HOST|" \
		"$checkout/wp-tests-config-sample.php" > "$WP_TESTS_DIR/wp-tests-config.php"
	rm -rf "$checkout"
	echo "$WP_VERSION" > "$WP_TESTS_DIR/.version"
}

create_database() {
	DB_NAME=$DB_NAME DB_USER=$DB_USER DB_PASS=$DB_PASS DB_HOST=$DB_HOST php -r '
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		[$host, $port] = array_pad(explode(":", getenv("DB_HOST"), 2), 2, null);
		$db = new mysqli($host, getenv("DB_USER"), getenv("DB_PASS"), "", $port !== null ? (int) $port : 3306);
		$db->query("CREATE DATABASE IF NOT EXISTS `" . $db->real_escape_string(getenv("DB_NAME")) . "`");
	'
}

install_core
install_test_library
create_database
