#!/bin/sh
set -eu

[ "${APP_ENV:-}" = e2e ] || { echo 'E2E startup requires APP_ENV=e2e' >&2; exit 1; }
mkdir -p storage/app/public
cp /e2e/product.svg storage/app/public/e2e-product.svg
php artisan migrate --force
php artisan db:seed --class=E2ESeeder --force
php artisan storage:link
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
