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

# Host/kredensial dibaca dari env container, bukan dihardcode seperti dulu
# ("mysql" + root tanpa password): shared-postgres hidup di luar compose
# project ini, jadi nama host dan user-nya bisa berbeda per lingkungan.
DB_HOST="${DB_HOST:-shared-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_USERNAME="${DB_USERNAME:-blukios_app}"

echo "⏳ Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
# pg_isready hanya mengecek apakah server menerima koneksi -- tidak login,
# jadi tidak perlu password di sini. Kegagalan autentikasi akan muncul di
# langkah migrate berikutnya, dengan pesan yang jauh lebih jelas.
while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -q 2>/dev/null; do
    sleep 2
done
echo "✅ PostgreSQL ready"

# First-time setup
if [ ! -f /var/www/storage/.initialized ]; then
    echo "🔧 Running first-time setup..."
    
    # APP_KEY sengaja TIDAK di-generate di sini. Compose mewajibkannya lewat
    # ${APP_KEY:?}, jadi key selalu datang dari luar dan sama untuk api, queue,
    # scheduler, dan reverb. Generate di titik ini pernah berarti: hilangnya
    # marker .initialized diam-diam mengganti kunci enkripsi produksi.
    php artisan storage:link --force
    php artisan migrate --force --no-interaction
    # Hanya permission dan role. DatabaseSeeder ikut memanggil DemoSeeder,
    # yang membuat admin/buyer/seller dengan password tertulis di repo.
    php artisan db:seed --class=Database\Seeders\ProductionSeeder --force --no-interaction
    
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
