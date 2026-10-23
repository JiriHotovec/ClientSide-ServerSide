#!/usr/bin/env bash
# Build a ready-to-upload zip (vendor, built assets, migrated SQLite DB, production .env).
# Usage: scripts/build-package.sh [--with-data] https://example.com [output.zip]
#   --with-data  ship a copy of the local database/database.sqlite (users, devices, measurements) instead of a fresh one.
# Optional env: ADMIN_EMAIL (default admin@example.com), ADMIN_PASSWORD (default: random, printed at the end); ignored with --with-data.
set -euo pipefail

WITH_DATA=0
ARGS=()
for a in "$@"; do
  if [ "$a" = "--with-data" ]; then WITH_DATA=1; else ARGS+=("$a"); fi
done
APP_URL="${ARGS[0]:?Usage: $0 [--with-data] <app-url> [output.zip]}"
SRC="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${ARGS[1]:-$SRC/meteoview-package.zip}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(openssl rand -base64 12 | tr -d '/+=')}"
CRON_SECRET="$(openssl rand -hex 24)"

echo "==> Copying source"
rsync -a \
  --exclude='.git' --exclude='.github' --exclude='.agents' --exclude='.claude' --exclude='.codex' \
  --exclude='.env' --exclude='.mcp.json' --exclude='boost.json' --exclude='AGENTS.md' --exclude='CLAUDE.md' \
  --exclude='.DS_Store' --exclude='/vendor' --exclude='/node_modules' --exclude='/tests' --exclude='/docs' \
  --exclude='/public/build' --exclude='/public/hot' --exclude='/scripts' --exclude='*.zip' \
  --exclude='/database/database.sqlite' --exclude='/storage/logs/*' --exclude='/storage/framework/*/*' \
  "$SRC/" "$STAGE/"
mkdir -p "$STAGE"/storage/framework/{cache/data,sessions,views} "$STAGE"/storage/logs
cd "$STAGE"

echo "==> Frontend build"
npm install --no-audit --no-fund --silent
npm run build --silent
rm -rf node_modules

echo "==> Composer (no-dev, optimized)"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "==> Production .env"
sed -e 's|^APP_ENV=.*|APP_ENV=production|' \
    -e 's|^APP_DEBUG=.*|APP_DEBUG=false|' \
    -e "s|^APP_URL=.*|APP_URL=$APP_URL|" \
    -e 's|^LOG_LEVEL=.*|LOG_LEVEL=warning|' \
    -e "s|^CRON_SECRET=.*|CRON_SECRET=$CRON_SECRET|" \
    .env.example > .env
php artisan key:generate --force --no-interaction >/dev/null

echo "==> SQLite database"
if [ "$WITH_DATA" = 1 ]; then
  [ -s "$SRC/database/database.sqlite" ] || { echo "Local database/database.sqlite missing or empty" >&2; exit 1; }
  # .backup gives a consistent copy even if the local DB is in use
  sqlite3 "$SRC/database/database.sqlite" ".backup '$STAGE/database/database.sqlite'"
  php artisan migrate --force --no-interaction >/dev/null
  php artisan tinker --execute 'DB::table("sessions")->delete(); DB::table("cache")->delete(); DB::table("cache_locks")->delete();' >/dev/null
else
  touch database/database.sqlite
  php artisan migrate --force --no-interaction >/dev/null
  ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASSWORD="$ADMIN_PASSWORD" php artisan tinker --execute '
App\Support\SystemTypes::sync();
$u = new App\Models\User(["name" => "Admin", "email" => getenv("ADMIN_EMAIL"), "password" => getenv("ADMIN_PASSWORD")]);
$u->role = "admin"; $u->email_verified_at = now(); $u->save();
' >/dev/null
fi

# Hosts whose web root is the project root: send everything to public/.
cat > .htaccess <<'HT'
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
HT

echo "==> Zip"
rm -f "$OUT"
zip -qr "$OUT" . -x '.env.example'
echo
echo "Package: $OUT ($(du -h "$OUT" | cut -f1))"
if [ "$WITH_DATA" = 1 ]; then echo "Admin:   existing users from local DB"; else echo "Admin:   $ADMIN_EMAIL / $ADMIN_PASSWORD"; fi
echo "Cron:    curl -H 'X-Cron-Token: $CRON_SECRET' $APP_URL/api/cron/fetch   (run every minute)"
echo "Upload, unzip, point web root to public/ (or keep root .htaccess). Needs PHP >= 8.3, pdo_sqlite, writable storage/ bootstrap/cache/ database/."
