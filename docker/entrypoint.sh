#!/usr/bin/env sh
set -eu

cd /var/www/html

DATA_READY_MARKER=data/.provisioned

# Only the web container provisions the volume; the scheduler waits instead of racing it.
if [ "${1:-}" = "apache2-foreground" ]; then
    mkdir -p \
        data/database \
        data/storage/logs \
        data/storage/framework/cache/data \
        data/storage/framework/sessions \
        data/storage/framework/views \
        data/storage/framework/testing \
        data/storage/app/private \
        data/storage/app/public

    if [ ! -L storage ]; then
        rm -rf storage
        ln -s /var/www/html/data/storage /var/www/html/storage
    fi
else
    if [ ! -L storage ]; then
        rm -rf storage
        ln -s /var/www/html/data/storage /var/www/html/storage
    fi

    waited=0
    while [ ! -f "$DATA_READY_MARKER" ] && [ "$waited" -lt 60 ]; do
        sleep 1
        waited=$((waited + 1))
    done
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

if [ "${1:-}" = "apache2-foreground" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "ERROR: APP_KEY is empty. Cryptosik cannot start without an application key." >&2
        echo "Generate one and put it in your .env file (keep the base64: prefix):" >&2
        echo "" >&2
        echo "    docker compose run --rm app php artisan key:generate --show" >&2
        echo "" >&2
        exit 1
    fi

    php artisan migrate --force --no-interaction

    # Cannot be baked into the image: routes/web.php reads CRYPTOSIK_ADMIN_PATH at registration.
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Must stay last: migrate and the cache warmers run as root and create files as root.
    chown -R www-data:www-data data
    chmod -R ug+rwX data

    touch "$DATA_READY_MARKER"
    chown www-data:www-data "$DATA_READY_MARKER"
fi

if [ "${1:-}" = "php" ]; then
    exec su -p -s /bin/sh www-data -c "$*"
fi

exec "$@"
