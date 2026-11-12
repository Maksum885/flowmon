#!/bin/sh
set -e
cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/spool

if [ "$1" = "php-fpm" ]; then
    # hanya kontainer web yang menjalankan migrasi
    php artisan migrate --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"