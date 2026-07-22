FROM composer:2.10.2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

FROM dunglas/frankenphp:1.9-php8.4-bookworm
RUN install-php-extensions pdo_pgsql redis pcntl intl zip exif
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor /app/vendor
RUN php artisan package:discover --ansi && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8000
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=500"]
