#!/usr/bin/env bash
# CI: installs the release zip into an installed Kimai and tests it end to end.
#
#   dev/ci.sh <kimai-dir> <HolidayBundle-x.y.z.zip>
#
#   zip ──► var/plugins/HolidayBundle ──► kimai:reload ──► kimai:bundle:holiday:install (migrations)
#       ──► lint:container / twig / xliff ──► php -S: login, absences, calendar, public holidays,
#       working times; API and ICS without credentials are refused, not 500
#
# env: DATABASE_URL, e.g. mysql://kimai:kimai@127.0.0.1:3306/kimai?charset=utf8mb4&serverVersion=10.11.0-MariaDB
set -euo pipefail
trap 'echo "FAIL: unexpected error in line $LINENO" >&2' ERR

KIMAI="$(cd "$1" && pwd)"
ZIP="$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"
PLUGIN="$KIMAI/var/plugins/HolidayBundle"
PORT="${CI_PORT:-8765}"
BASE="http://127.0.0.1:$PORT"
ADMIN_PASS="ci-admin-pass"
LOG="$(mktemp)"
: "${DATABASE_URL:?DATABASE_URL is required}"

# unlimited memory: warming up the Twig cache needs more than the usual 128 MB CLI limit
console() { php -d memory_limit=-1 "$KIMAI/bin/console" --no-interaction "$@"; }
fail() { echo "FAIL: $*" >&2; exit 1; }
step() { echo; echo "── $*"; }

step "Install zip"
test ! -e "$PLUGIN" || fail "$PLUGIN exists already"
mkdir -p "$KIMAI/var/plugins"
php -r '$z = new ZipArchive(); $z->open($argv[1]) === true || exit(1); $z->extractTo($argv[2]) || exit(1);' "$ZIP" "$KIMAI/var/plugins"
test -f "$PLUGIN/HolidayBundle.php" || fail "zip does not contain HolidayBundle/HolidayBundle.php"
test ! -e "$PLUGIN/dev" || fail "dev/ must not be part of the zip"
test ! -e "$PLUGIN/demo" || fail "demo/ must not be part of the zip"
console kimai:reload
console kimai:plugin | grep -q HolidayBundle || fail "plugin not loaded"

step "Migrations"
console kimai:bundle:holiday:install

step "Lint"
console lint:container
console lint:twig "$PLUGIN/Resources/views"
console lint:xliff "$PLUGIN/Resources/translations"
test "$(console debug:router | grep -c 'holiday_')" -ge 20 || fail "routes missing"

step "HTTP"
console kimai:user:create admin admin@example.test ROLE_SUPER_ADMIN "$ADMIN_PASS"
# EGPCS: the built-in server otherwise hides environment variables (DATABASE_URL) from Symfony
php -d memory_limit=-1 -d variables_order=EGPCS -S "127.0.0.1:$PORT" -t "$KIMAI/public" "$KIMAI/public/index.php" > "$LOG" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 30); do curl -sf -o /dev/null "$BASE/en/login" && break; sleep 1; done

JAR="$(mktemp)"
token="$(curl -sf -c "$JAR" -b "$JAR" "$BASE/en/login" | grep -oE 'name="_csrf_token" value="[^"]+"' | sed -E 's/.*value="([^"]+)"/\1/')"
test -n "$token" || fail "no login token"
curl -sf -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "_username=admin" --data-urlencode "_password=$ADMIN_PASS" --data-urlencode "_csrf_token=$token" "$BASE/en/login_check"

# check <url> <status> [text]: HTTP status after redirects, and the text in the body
check() {
    local url="$1" status="$2" expect="${3:-}" body code final
    body="$(mktemp)"
    read -r code final < <(curl -sL -o "$body" -w '%{http_code} %{url_effective}\n' -c "$JAR" -b "$JAR" -H 'Accept: text/html,application/json' "$BASE$url")
    test "$code" = "$status" || { tail -20 "$LOG" >&2; fail "$url: HTTP $code, expected $status"; }
    if [[ -n "$expect" ]] && ! grep -q "$expect" "$body"; then
        echo "landed on $final, title: $(grep -o '<title>[^<]*' "$body" | head -1)" >&2
        fail "$url: missing \"$expect\""
    fi
    echo "ok $url"
}
check "/en/holiday/absence" 200 "Vacation left"
check "/en/holiday/absence-calendar" 200 "data-holiday-autosubmit"
check "/en/holiday/public-holidays" 200 "Groups"
check "/en/holiday/working-times" 200
check "/en/holiday/absence/ics" 200

# Without the session: the API wants a token, an unknown ICS token finds nothing.
JAR="$(mktemp)"
check "/api/holiday/absences/types" 401
check "/holiday/ics/$(printf '%048d' 0).ics" 404

if grep -E 'CRITICAL|PHP (Fatal|Warning)' "$LOG"; then
    fail "errors in the server log"
fi

echo
echo "All checks passed."
