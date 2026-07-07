#!/bin/sh
set -e

mkdir -p /var/cache/nginx/imgproxy /run/nginx /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp
chown -R app:app /var/www/html/storage /var/www/html/bootstrap/cache /run /var/lib/nginx /var/log/nginx /var/cache/nginx /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp 2>/dev/null || true

exec env - PATH="$PATH" runsvdir -P /etc/service
