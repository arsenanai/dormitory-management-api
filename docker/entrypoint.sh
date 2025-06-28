#!/bin/sh

set -e

# Install PHP dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    php artisan key:generate
fi

# Wait for DB to be ready
until php artisan migrate --force; do
    echo "Waiting for database..."
    sleep 3
done

exec "$@"