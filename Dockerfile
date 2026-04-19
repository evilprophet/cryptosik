FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-dev \
    --optimize-autoloader \
    --no-scripts

COPY . .

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron libsqlite3-dev libzip-dev pkg-config unzip \
    && docker-php-ext-install pdo_sqlite zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/:80>/:8080>/g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY docker/cron/laravel-scheduler /etc/cron.d/laravel-scheduler
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 0644 /etc/cron.d/laravel-scheduler \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
