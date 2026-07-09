#!/bin/sh
set -e

shutdown() {
  echo "shutting down container"

  for _srv in /etc/service/*; do
    [ -d "${_srv}" ] && sv force-stop "$(basename "${_srv}")" || true
  done

  kill -HUP "${PID}" 2>/dev/null || true
  wait "${PID}" 2>/dev/null || true
  exit
}

mkdir -p /var/cache/nginx/imgproxy /run/nginx /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp
chown -R app:app /var/www/html/storage /var/www/html/bootstrap/cache /run /var/lib/nginx /var/log/nginx /var/cache/nginx /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp 2>/dev/null || true

exec env - PATH="$PATH" runsvdir -P /etc/service &
PID=$!

su app -c "cd /var/www/html && php artisan schedule:work" &
su app -c "cd /var/www/html && php artisan horizon" &
su app -c "cd /var/www/html && php artisan reverb:start" &

trap shutdown SIGTERM SIGHUP SIGQUIT SIGINT
wait "${PID}"

shutdown
