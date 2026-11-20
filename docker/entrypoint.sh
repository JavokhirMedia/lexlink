#!/bin/sh
# Runs as root: prepares the shared storage volume, caches config/routes/views/events
# for THIS container, then drops to www-data (php-fpm drops privileges itself).
set -e
cd /var/www/html

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

as_www() { setpriv --reuid=www-data --regid=www-data --init-groups "$@"; }

if [ "${LEXLINK_SKIP_OPTIMIZE:-0}" != "1" ] && [ -f .env ]; then
    as_www php artisan optimize --quiet || echo "[entrypoint] WARNING: php artisan optimize failed" >&2
fi

if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi
exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
