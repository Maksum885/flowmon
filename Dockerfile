# syntax=docker/dockerfile:1

# ---- tahap 1: pasang pustaka PHP tanpa paket pengembangan ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- tahap 2: aplikasi (php-fpm; dipakai juga oleh listener dan scheduler) ----
FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache libpq \
 && apk add --no-cache --virtual .build postgresql-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql pcntl opcache \
 && apk del .build \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
 && chmod +x /usr/local/bin/entrypoint \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/spool bootstrap/cache \
 && rm -f bootstrap/cache/*.php \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---- tahap 3: web server Caddy (membawa berkas publik) ----
FROM caddy:2-alpine AS web
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public