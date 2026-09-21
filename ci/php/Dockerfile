# Image stage Backend: Lint & Test. Tag-nya hash file ini, jadi hanya di-build ulang saat file ini berubah.
FROM php:8.4-cli

RUN apt-get update -qq \
    && apt-get install -y -qq git unzip curl libsqlite3-dev libzip-dev libssl-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev libonig-dev >/dev/null \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp >/dev/null \
    && docker-php-ext-install pdo_sqlite zip bcmath gd exif mbstring >/dev/null

RUN pecl install mongodb-2.4.1 redis-6.3.0 >/dev/null 2>&1 \
    && docker-php-ext-enable mongodb redis

# Static binary, bukan apt repo MongoDB: tidak butuh GPG key/repo yang bisa basi mengikuti Debian base image.
RUN curl -4 -fsSL -o /tmp/mongo.tgz https://fastdl.mongodb.org/linux/mongodb-linux-x86_64-debian12-7.0.14.tgz \
    && mkdir -p /opt/mongo /data/mongo-ci \
    && tar -xzf /tmp/mongo.tgz -C /opt/mongo --strip-components=1 \
    && rm /tmp/mongo.tgz
