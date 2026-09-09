#!/bin/bash
set -e

cd /var/www/html

# Run migrations when enabled
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force --no-interaction
fi

# Cache configuration for production
if [ "${APP_ENV}" = "production" ]; then
    echo "Optimizing application..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache 2>/dev/null || true
fi

# Storage link
php artisan storage:link 2>/dev/null || true

exec "$@"
