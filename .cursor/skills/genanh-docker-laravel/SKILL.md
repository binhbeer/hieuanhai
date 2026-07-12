---
name: genanh-docker-laravel
description: Run and validate work in the GenAnh Laravel Docker stack. Use when changing PHP, Blade, Livewire, Vite assets, migrations, tests, Composer scripts, Docker services, or when the user mentions docker, artisan, composer, npm, vite, mysql, cache, queue, or tests in this project.
---

# GenAnh Docker Laravel

## Context
- Workspace root: `/home/chinhanh`.
- Laravel app root: `/home/chinhanh/app`.
- Container app root: `/var/www/html`.
- Runtime service: `app` (`chinhanh-app`).
- Project domain: `https://chinhanh.local`; prefer this over `localhost` for browser/manual checks and generated app URLs.

## Commands
Run from `/home/chinhanh`:

```bash
docker compose exec app sh -lc 'cd /var/www/html && php artisan <command>'
docker compose exec app sh -lc 'cd /var/www/html && composer <command>'
docker compose exec app sh -lc 'cd /var/www/html && npm <command>'
```

Use these checks by change type:

```bash
# PHP formatting
composer lint:check

# Static analysis
composer types:check

# Test suite
composer test

# Assets
npm run build

# Migrations
php artisan migrate --pretend
php artisan migrate
```

## Rules
- Prefer container commands over host commands.
- Before starting services, check whether they already run.
- Never run destructive Docker/database commands without explicit user approval.
- Use smallest check that covers the diff; full `composer test` only for broad PHP changes.
- If container is down, start with `docker compose up -d` and wait for MySQL health.
- After `php artisan optimize:clear` (or any artisan that rewrites `storage/` / `bootstrap/cache` as root), always `chown -R app:app storage bootstrap/cache` in the same container session. Example:

```bash
docker compose exec app sh -lc 'cd /var/www/html && php artisan optimize:clear && chown -R app:app storage bootstrap/cache'
```

  Skip `chown` only if command already ran as `app` (e.g. `docker compose exec -u app`).
