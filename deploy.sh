#!/bin/bash
set -e

echo "=============================="
echo " StewardAI Production Deploy"
echo "=============================="

# Detect app directory (where this script lives)
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

echo ""
echo "[1/8] Fixing storage directory structure..."
mkdir -p storage/framework/{cache/data,sessions,views,testing}
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo "[2/8] Setting permissions..."
# Detect web server user
WEB_USER="www-data"
if id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
fi

chmod -R 775 storage bootstrap/cache
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
echo "    Web user: $WEB_USER"

echo "[3/8] Configuring environment for Redis..."
if [ -f .env ]; then
    # Backup current .env
    cp .env .env.backup.$(date +%Y%m%d%H%M%S)

    # Set cache, session, and queue to use Redis
    sed -i 's/^CACHE_STORE=.*/CACHE_STORE=redis/' .env
    sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env
    sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env

    # Add Redis config if not present
    grep -q "^REDIS_HOST=" .env || echo "REDIS_HOST=127.0.0.1" >> .env
    grep -q "^REDIS_PORT=" .env || echo "REDIS_PORT=6379" >> .env
    grep -q "^REDIS_PASSWORD=" .env || echo "REDIS_PASSWORD=null" >> .env

    # Add cache/session/queue lines if they don't exist at all
    grep -q "^CACHE_STORE=" .env || echo "CACHE_STORE=redis" >> .env
    grep -q "^SESSION_DRIVER=" .env || echo "SESSION_DRIVER=redis" >> .env
    grep -q "^QUEUE_CONNECTION=" .env || echo "QUEUE_CONNECTION=redis" >> .env

    echo "    Cache: redis, Session: redis, Queue: redis"
else
    echo "    WARNING: No .env file found. Copy .env.example and configure manually."
fi

echo "[4/8] Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --production 2>/dev/null || npm install --production

echo "[5/8] Building assets..."
npm run build

echo "[6/8] Running migrations..."
php artisan migrate --force

echo "[7/8] Clearing and rebuilding caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[8/8] Restarting services..."
php artisan queue:restart 2>/dev/null || true

# Restart PHP-FPM if available
if systemctl is-active --quiet php*-fpm 2>/dev/null; then
    FPM_SERVICE=$(systemctl list-units --type=service --state=running | grep php | grep fpm | awk '{print $1}' | head -1)
    if [ -n "$FPM_SERVICE" ]; then
        systemctl restart "$FPM_SERVICE"
        echo "    Restarted $FPM_SERVICE"
    fi
fi

echo ""
echo "=============================="
echo " Deploy complete!"
echo "=============================="
echo ""
echo "If you still see errors, check:"
echo "  - Redis is running: redis-cli ping"
echo "  - Storage writable: ls -la storage/"
echo "  - Laravel logs: tail -f storage/logs/laravel.log"
