#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-8080}"

mkdir -p /var/log/nginx /var/cache/nginx /tmp /var/log 2>/dev/null || true

NGINX_CONF="/tmp/nginx.conf"

if command -v node >/dev/null 2>&1 && [ -f /assets/scripts/prestart.mjs ] && [ -f /assets/nginx.template.conf ]; then
  echo "nginx: nixpacks prestart (/assets/nginx.template.conf)"
  node /assets/scripts/prestart.mjs /assets/nginx.template.conf "${NGINX_CONF}"
elif [ -f /assets/nginx.conf ]; then
  echo "nginx: /assets/nginx.conf"
  cp /assets/nginx.conf "${NGINX_CONF}"
else
  echo "nginx: scripts/nginx.conf.template"
  sed "s/PORT_PLACEHOLDER/${PORT}/g" /app/scripts/nginx.conf.template > "${NGINX_CONF}"
  if [ ! -f /etc/nginx/mime.types ]; then
    NGINX_PREFIX="$(nginx -V 2>&1 | sed -n 's/.*--prefix=\([^ ]*\).*/\1/p' | head -1 || true)"
    if [ -n "${NGINX_PREFIX}" ] && [ -f "${NGINX_PREFIX}/conf/mime.types" ]; then
      sed -i "s|include /etc/nginx/mime.types|include ${NGINX_PREFIX}/conf/mime.types|g" "${NGINX_CONF}" || true
    fi
  fi
fi

FPM_CONF="/app/scripts/php-fpm.conf"
if [ -f /assets/php-fpm.conf ]; then
  FPM_CONF="/assets/php-fpm.conf"
fi

echo "php-fpm: ${FPM_CONF}"
if ! php-fpm -y "${FPM_CONF}" -D 2>/var/log/php-fpm-start.log; then
  echo "WARN: php-fpm falló, usando config mínima"
  cat > /tmp/php-fpm-min.conf << 'EOF'
[global]
daemonize = yes
[www]
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
clear_env = no
EOF
  php-fpm -y /tmp/php-fpm-min.conf -D
fi

echo "nginx -t..."
nginx -t -c "${NGINX_CONF}"

echo "nginx en puerto ${PORT} (php-fpm 127.0.0.1:9000)"
exec nginx -c "${NGINX_CONF}" -g 'daemon off;'
