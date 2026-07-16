#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

git pull
php artisan migrate
pnpm build
mv storage/framework/views storage/framework/views.old
mkdir storage/framework/views
chown www-data:www-data storage/framework/views
rm -rf storage/framework/views.old
php artisan route:clear
php artisan optimize
chown -R www-data:www-data storage/
systemctl restart php8.4-fpm.service
systemctl restart supervisor.service
