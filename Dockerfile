FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader --prefer-dist

COPY . .

# Vite 8 requires node ^20.19.0 || >=22.12.0 - pin the major so a floating tag
# cannot drift below that.
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci --prefer-offline --no-audit --no-fund

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libzip-dev pkg-config unzip \
    && docker-php-ext-install pdo_sqlite pdo_mysql zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Code never changes inside a running container, so timestamp revalidation is pure overhead.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:8080>/g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Only package discovery is env-independent; the other caches are built at boot.
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") === false ? 1 : 0);'

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
