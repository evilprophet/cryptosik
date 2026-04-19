#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
    data/database \
    data/storage/logs \
    data/storage/framework/cache/data \
    data/storage/framework/sessions \
    data/storage/framework/views \
    data/storage/framework/testing \
    data/storage/app/private \
    data/storage/app/public \
    data/bootstrap-cache

if [ ! -L storage ]; then
    rm -rf storage
    ln -s /var/www/html/data/storage /var/www/html/storage
fi

if [ ! -L database ]; then
    rm -rf database
    ln -s /var/www/html/data/database /var/www/html/database
fi

if [ ! -L bootstrap/cache ]; then
    rm -rf bootstrap/cache
    ln -s /var/www/html/data/bootstrap-cache /var/www/html/bootstrap/cache
fi

touch database/database.sqlite

chown -R www-data:www-data data storage database bootstrap/cache
chmod -R ug+rwX data

php artisan package:discover --ansi
php artisan migrate --force --no-interaction

cron

exec "$@"
