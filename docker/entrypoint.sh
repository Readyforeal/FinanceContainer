#!/bin/sh
set -e

cd /app

# Generate key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate --force

# Seed categories if needed
php artisan db:seed --class=CategorySeeder --force 2>/dev/null || true

# Cache config
php artisan config:cache
php artisan view:cache

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache

# Start supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
