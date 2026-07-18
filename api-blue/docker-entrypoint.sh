#!/bin/bash
set -e

# Merge Docker-specific env overrides into .env
# Uses a temp file + cp to preserve the bind-mount inode (sed -i would break it)
if [ -f /var/www/.env.docker ]; then
    cp /var/www/.env /tmp/.env.merged
    while IFS='=' read -r key value; do
        [[ -z "$key" || "$key" =~ ^# ]] && continue
        if grep -q "^${key}=" /tmp/.env.merged 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" /tmp/.env.merged
        else
            echo "${key}=${value}" >> /tmp/.env.merged
        fi
    done < /var/www/.env.docker
    cp /tmp/.env.merged /var/www/.env
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

php artisan config:cache
php artisan route:cache

# storage/ & bootstrap/cache ke-bind-mount dari host, ownership-nya sering balik
# ke host-user (root/UID lain) setelah rebuild/restart container -- entrypoint ini
# jalan sebagai root sebelum php-fpm fork worker sebagai www-data, jadi paling
# aman dibetulkan di sini tiap start daripada nunggu error tempnam()/permission
# denied muncul dulu baru fix manual
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "🚀 Starting PHP-FPM..."
exec php-fpm -F
