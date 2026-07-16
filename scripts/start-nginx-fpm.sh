#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-8080}"

mkdir -p /var/log/nginx /var/cache/nginx /tmp 2>/dev/null || true

NGINX_CONF="/etc/nginx.conf"

if [ -f /assets/scripts/prestart.mjs ] && [ -f /assets/nginx.template.conf ]; then
  echo "nginx: usando template nixpacks (/assets)"
  node /assets/scripts/prestart.mjs /assets/nginx.template.conf "${NGINX_CONF}"
elif [ -f /assets/nginx.conf ]; then
  echo "nginx: usando /assets/nginx.conf"
  cp /assets/nginx.conf "${NGINX_CONF}"
else
  echo "nginx: usando scripts/nginx.conf.template"
  sed "s/PORT_PLACEHOLDER/${PORT}/g" /app/scripts/nginx.conf.template > "${NGINX_CONF}"
fi

FPM_CONF="/app/scripts/php-fpm.conf"
if [ -f /assets/php-fpm.conf ]; then
  FPM_CONF="/assets/php-fpm.conf"
fi

echo "php-fpm: ${FPM_CONF}"
php-fpm -y "${FPM_CONF}" -D

echo "nginx: puerto ${PORT}"
exec nginx -c "${NGINX_CONF}" -g 'daemon off;'
