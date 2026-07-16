#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

PORT="${PORT:-8080}"
export PORT

echo "=== Emisora Online — Railway start ==="
echo "PORT=${PORT}"

if [ -z "${APP_KEY:-}" ]; then
  APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  export APP_KEY
  echo "WARN: APP_KEY no configurada — usando clave temporal."
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-${DATABASE_URL:-}}"

if [ -z "${CACHE_STORE:-}" ]; then
  export CACHE_STORE=file
fi
if [ -z "${SESSION_DRIVER:-}" ]; then
  export SESSION_DRIVER=file
fi

php artisan config:clear >/dev/null 2>&1 || true
php artisan storage:link >/dev/null 2>&1 || true

if [ -n "${DB_URL}" ]; then
  echo "Ejecutando migrate + seed..."
  php artisan migrate --force --no-interaction
  php artisan db:seed --force --no-interaction || true
fi

php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true

echo "CACHE_STORE=${CACHE_STORE:-file} SESSION_DRIVER=${SESSION_DRIVER:-file}"

chmod +x /app/scripts/start-nginx-fpm.sh 2>/dev/null || true

echo "Iniciando nginx + php-fpm..."
if [ -f /assets/start.sh ]; then
  exec /assets/start.sh
fi

exec bash /app/scripts/start-nginx-fpm.sh
