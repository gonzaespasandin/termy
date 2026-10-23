#!/usr/bin/env bash
# Termy deploy script. Run on the server as the ubuntu user, from any directory.
# Idempotent: safe to run repeatedly. Does not run seeders or build the frontend.
set -euo pipefail

APP_DIR=/var/www/termy
BACKEND_DIR="$APP_DIR/backend"

step() { printf '\n==> %s\n' "$*"; }

if [ ! -f "$BACKEND_DIR/.env" ]; then
    echo "ERROR: $BACKEND_DIR/.env is missing. Copy deploy/backend.env.production.example first." >&2
    exit 1
fi

step "Pulling latest code"
cd "$APP_DIR"
git pull --ff-only

cd "$BACKEND_DIR"

step "Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

step "Running migrations"
php artisan migrate --force

step "Linking public storage"
php artisan storage:link || echo "storage link already exists, skipping"

step "Caching config, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

step "Fixing permissions for www-data"
sudo chown -R "$(id -un)":www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} +

step "Reloading services"
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx

step "Deploy finished"
