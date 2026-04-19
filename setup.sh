#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/backend"

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
  echo "==> Creating .env from .env.example"
  cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64:" .env; then
  echo "==> Generating APP_KEY"
  php artisan key:generate
fi

mkdir -p database
if [ ! -f database/database.sqlite ]; then
  echo "==> Creating SQLite database"
  touch database/database.sqlite
fi

echo "==> Regenerating composer autoload (paths)"
composer dump-autoload -o --no-interaction

echo "==> Clearing caches"
php artisan config:clear
php artisan cache:clear

echo "==> Running migrations + seeders"
php artisan migrate --seed --force

echo ""
echo "Setup complete. Run ./start.sh to launch the app."
