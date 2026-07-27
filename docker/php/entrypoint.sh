#!/usr/bin/env sh
set -e

if [ ! -f vendor/autoload.php ] || [ composer.lock -nt vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
