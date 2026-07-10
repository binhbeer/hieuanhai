#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

git pull
php artisan migrate
pnpm build
php artisan optimize
chown -R www-data:www-data storage/
systemctl restart php8.4-fpm.service
