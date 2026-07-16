#!/bin/bash
set -e

echo "=== Emisora Online — Railway start ==="

if [ -n "${PORT:-}" ]; then
    sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
    sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/*.conf
    echo "Apache en puerto ${PORT}"
fi

if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force || echo "[warn] no se pudo generar APP_KEY"
fi

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-${DATABASE_URL:-}}"

if [ -z "${CACHE_STORE:-}" ]; then
    export CACHE_STORE=file
fi
if [ -z "${SESSION_DRIVER:-}" ]; then
    export SESSION_DRIVER=file
fi

echo "CACHE_STORE=${CACHE_STORE} SESSION_DRIVER=${SESSION_DRIVER}"

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan storage:link || true

if [ -n "${DB_URL}" ]; then
    echo "Ejecutando migrate + seed..."
    php artisan migrate --force --no-interaction || echo "[warn] migrate fallo, continuando"
    php artisan db:seed --force --no-interaction || echo "[warn] seed fallo, continuando"
fi

php artisan config:cache || echo "[warn] config:cache fallo"
php artisan route:cache || echo "[warn] route:cache fallo"
php artisan view:cache || echo "[warn] view:cache fallo"

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

for f in /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; do
    if [ -e "$f" ] && ! echo "$f" | grep -q "mpm_prefork"; then
        rm -f "$f"
    fi
done

if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
    a2enmod mpm_prefork >/dev/null 2>&1 || true
fi

echo "=== Emisora Online lista en puerto ${PORT:-80} ==="
exec apache2-foreground
