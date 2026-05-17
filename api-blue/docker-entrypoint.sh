#!/bin/bash
set -e

# Merge Docker-specific env overrides into .env
if [ -f /var/www/.env.docker ]; then
    while IFS='=' read -r key value; do
        [[ -z "$key" || "$key" =~ ^# ]] && continue
        if grep -q "^${key}=" /var/www/.env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" /var/www/.env
        else
            echo "${key}=${value}" >> /var/www/.env
        fi
    done < /var/www/.env.docker
    echo "✅ Docker env overrides applied"
fi

echo "⏳ Waiting for MySQL..."
while ! mysqladmin ping -h"mysql" -u root --skip-ssl --silent 2>/dev/null; do
    sleep 2
done
echo "✅ MySQL ready"

# First-time setup
if [ ! -f /var/www/storage/.initialized ]; then
    echo "🔧 Running first-time setup..."
    
    php artisan key:generate --force --no-interaction
    php artisan storage:link --force
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction
    
    touch /var/www/storage/.initialized
    echo "✅ Setup complete"
else
    echo "📦 Running pending migrations..."
    php artisan migrate --force --no-interaction
fi

php artisan config:clear
php artisan route:clear

echo "🚀 Starting Laravel dev server..."
exec php artisan serve --host=0.0.0.0 --port=8000
