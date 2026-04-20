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

if [ ! -L bootstrap/cache ]; then
    rm -rf bootstrap/cache
    ln -s /var/www/html/data/bootstrap-cache /var/www/html/bootstrap/cache
fi

# Keep migration files from the image in /var/www/html/database.
# Persist only the SQLite file in /var/www/html/data/database.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    db_path="${DB_DATABASE:-database/database.sqlite}"

    if [ "$db_path" = "database/database.sqlite" ]; then
        export DB_DATABASE=/var/www/html/data/database/database.sqlite
    fi

    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
fi

chown -R www-data:www-data data storage bootstrap/cache
chmod -R ug+rwX data

php artisan package:discover --ansi
php artisan migrate --force --no-interaction

cron

exec "$@"
