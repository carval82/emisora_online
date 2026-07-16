#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY no está configurada en Railway."
  exit 1
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-${DATABASE_URL:-}}"

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
