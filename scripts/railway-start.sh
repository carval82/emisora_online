#!/usr/bin/env bash
set -uo pipefail

cd "$(dirname "$0")/.."

PORT="${PORT:-8080}"
echo "=== Emisora Online — Railway start ==="
echo "PORT=${PORT}"

if [ -z "${APP_KEY:-}" ]; then
  APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  export APP_KEY
  echo "WARN: APP_KEY no configurada — usando clave temporal. Configura APP_KEY en Variables."
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-${DATABASE_URL:-}}"

php artisan config:clear >/dev/null 2>&1 || true
php artisan storage:link >/dev/null 2>&1 || true

echo "Iniciando servidor (healthcheck)..."
php artisan serve --host=0.0.0.0 --port="${PORT}" &
SERVER_PID=$!

setup_database() {
  if [ -z "${DB_URL}" ]; then
    echo "WARN: Sin DATABASE_URL — agrega PostgreSQL al proyecto y vincúlalo."
    return 0
  fi
  echo "Ejecutando migrate + seed..."
  php artisan migrate --force --no-interaction || echo "WARN: migrate falló"
  php artisan db:seed --force --no-interaction || true
  php artisan config:cache >/dev/null 2>&1 || true
  php artisan route:cache >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  echo "Base de datos lista."
}

setup_database &
wait "${SERVER_PID}"
