#!/usr/bin/env bash
# Installs a plugin zip into the running Kimai container (called by .github/workflows/deploy.yml).
# Steps: database dump, keep the current plugin, unpack the new one, reload as www-data, migrations,
# checks. A failed check restores the previous plugin and exits non-zero.
#
# Usage: deploy-prod.sh <HolidayBundle.zip> [commit]
# Env: KIMAI_CONTAINER (kimai), KIMAI_DB_CONTAINER (kimai-db), KIMAI_URL (http://localhost:8001,
#      seen from inside the container), DEPLOY_HOME (~/deploy/kimai-holiday), KEEP_BACKUPS (10)
set -euo pipefail

ZIP=${1:?Aufruf: deploy-prod.sh <zip> [commit]}
COMMIT=$(echo "${2:-unknown}" | cut -c1-7)
APP=${KIMAI_CONTAINER:-kimai}
DB=${KIMAI_DB_CONTAINER:-kimai-db}
URL=${KIMAI_URL:-http://localhost:8001}
HOME_DIR=${DEPLOY_HOME:-$HOME/deploy/kimai-holiday}
KEEP=${KEEP_BACKUPS:-10}
PLUGINS=/opt/kimai/var/plugins
STAMP=$(date -u +%Y%m%d-%H%M%S)

log() { echo "[Deploy] $*"; }
# Kimai writes deprecation notices to stderr on every call; keep only the rest
console() { docker exec -u www-data -w /opt/kimai "$APP" bin/console --no-interaction "$@" 2> >(grep -v 'deprecation\.' >&2); }

mkdir -p "$HOME_DIR/backups" "$HOME_DIR/previous"

# Never two deploys at once (mkdir is atomic; the workflow also serialises)
if ! mkdir "$HOME_DIR/deploy.lock" 2> /dev/null; then
  log "Ein anderer Deploy läuft ($HOME_DIR/deploy.lock), Abbruch"
  exit 1
fi
trap 'rmdir "$HOME_DIR/deploy.lock"' EXIT

unzip -p "$ZIP" HolidayBundle/composer.json > /dev/null
VERSION=$(unzip -p "$ZIP" HolidayBundle/composer.json | sed -n 's/.*"version": *"\([^"]*\)".*/\1/p' | head -1)
log "Plugin $VERSION, Commit $COMMIT"

log "Datenbank-Backup"
DUMP="$HOME_DIR/backups/kimai-db-$STAMP-$COMMIT.sql.gz"
docker exec "$DB" sh -c 'mariadb-dump -u"${MARIADB_USER:-$MYSQL_USER}" -p"${MARIADB_PASSWORD:-$MYSQL_PASSWORD}" --single-transaction "${MARIADB_DATABASE:-$MYSQL_DATABASE}"' | gzip > "$DUMP"
if [ "$(gzip -dc "$DUMP" | grep -c '^CREATE TABLE')" -eq 0 ]; then
  log "Backup leer, Abbruch"
  exit 1
fi
ls -1t "$HOME_DIR"/backups/kimai-db-*.sql.gz | tail -n +$((KEEP + 1)) | xargs -r rm --

log "Aktuelles Plugin sichern"
rm -rf "$HOME_DIR/previous/HolidayBundle"
if docker exec "$APP" test -d "$PLUGINS/HolidayBundle"; then
  docker cp "$APP:$PLUGINS/HolidayBundle" "$HOME_DIR/previous/HolidayBundle"
fi

install_dir() {
  # $1: "zip" or "previous"
  docker exec "$APP" rm -rf "$PLUGINS/HolidayBundle"
  if [ "$1" = zip ]; then
    docker cp "$ZIP" "$APP:/tmp/HolidayBundle-deploy.zip"
    docker exec "$APP" sh -c "unzip -q /tmp/HolidayBundle-deploy.zip -d $PLUGINS && rm /tmp/HolidayBundle-deploy.zip"
  else
    docker cp "$HOME_DIR/previous/HolidayBundle" "$APP:$PLUGINS/HolidayBundle"
  fi
  docker exec "$APP" chown -R www-data:www-data "$PLUGINS/HolidayBundle"
  console kimai:reload > /dev/null
  console kimai:bundle:holiday:install > /dev/null
}

check() {
  # Login answers, plugin pages redirect to the login, no server errors since the reload
  local since=$1 path code
  code=$(docker exec "$APP" curl -s -o /dev/null -w '%{http_code}' "$URL/de/login")
  [ "$code" = 200 ] || { log "Prüfung: /de/login -> $code"; return 1; }
  for path in /de/holiday/absence /de/holiday/absence-calendar /de/holiday/availability; do
    code=$(docker exec "$APP" curl -s -o /dev/null -w '%{http_code}' "$URL$path")
    [ "$code" = 302 ] || { log "Prüfung: $path -> $code"; return 1; }
  done
  if docker logs --since "$since" "$APP" 2>&1 | grep -v deprecation | grep -qE '" 500 |CRITICAL|PHP Fatal'; then
    log "Prüfung: Fehler im Log"
    return 1
  fi
}

SINCE=$(date -u +%Y-%m-%dT%H:%M:%SZ)
log "Plugin einspielen"
if install_dir zip && check "$SINCE"; then
  log "Fertig: Plugin $VERSION läuft"
  exit 0
fi

log "Fehlgeschlagen, stelle das vorherige Plugin wieder her"
if [ -d "$HOME_DIR/previous/HolidayBundle" ]; then
  install_dir previous
fi
log "Zurückgerollt. Datenbank-Backup: $DUMP"
exit 1
