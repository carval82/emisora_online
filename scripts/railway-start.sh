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

if [ -z "${CACHE_STORE:-}" ]; then
  export CACHE_STORE=file
  echo "CACHE_STORE=file (recomendado para live; evita PostgreSQL en cada chunk/pack)"
fi

PHP_FPM_CONF="/assets/php-fpm.conf"
if [ -f "${PHP_FPM_CONF}" ] && ! grep -q "pm.max_children = 20" "${PHP_FPM_CONF}"; then
  {
    echo ""
    echo "[www]"
    echo "pm = dynamic"
    echo "pm.max_children = 20"
    echo "pm.start_servers = 4"
    echo "pm.min_spare_servers = 2"
    echo "pm.max_spare_servers = 8"
  } >> "${PHP_FPM_CONF}"
  echo "php-fpm: pm.max_children=20"
fi

echo "Iniciando nginx + php-fpm (concurrencia para live)..."
if [ -f /assets/start.sh ]; then
  exec /assets/start.sh
fi

echo "Fallback: php artisan serve (solo desarrollo)"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
