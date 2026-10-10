#!/usr/bin/env bash
# CI: installs Kimai from source into <dir> with an empty database. Needs PHP 8.3 with intl, gd, zip, xsl, pdo_mysql.
# Usage: dev/ci-kimai.sh <dir>        env: KIMAI_VERSION (default 2.67.0), DATABASE_URL (see dev/ci.sh)
set -euo pipefail

DIR="$1"
KIMAI_VERSION="${KIMAI_VERSION:-2.67.0}"
: "${DATABASE_URL:?DATABASE_URL is required}"

git clone --quiet --depth 1 --branch "$KIMAI_VERSION" https://github.com/kimai/kimai.git "$DIR"
cd "$DIR"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

cat > .env.local <<ENV
APP_ENV=prod
APP_SECRET=ci-secret-not-for-production
DATABASE_URL=$DATABASE_URL
MAILER_URL=null://null
MAILER_FROM=kimai@example.test
ENV

php -d memory_limit=-1 bin/console kimai:install --no-interaction
