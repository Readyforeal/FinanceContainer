#!/bin/bash
set -e

APP_DIR="/home/jamie/FinanceContainer"
WEB_USER="www-data"
WEB_GROUP="www-data"

echo "=============================="
echo " StewardAI Full Reset & Deploy"
echo "=============================="
echo ""
echo "App directory: $APP_DIR"
echo "Web user: $WEB_USER"
echo ""

cd "$APP_DIR"

# -----------------------------------------------
# 1. Nuke all generated/cached files
# -----------------------------------------------
echo "[1/9] Wiping generated files..."
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/sessions/*
rm -rf storage/framework/views/*
rm -rf storage/logs/*
rm -rf bootstrap/cache/*.php
rm -rf vendor
rm -rf node_modules
rm -rf public/build

# -----------------------------------------------
# 2. Recreate storage structure
# -----------------------------------------------
echo "[2/9] Rebuilding storage directories..."
mkdir -p storage/framework/{cache/data,sessions,views,testing}
mkdir -p storage/logs
mkdir -p bootstrap/cache
touch storage/logs/laravel.log

# -----------------------------------------------
# 3. Set ownership FIRST (before anything writes files)
# -----------------------------------------------
echo "[3/9] Setting ownership and permissions..."
chown -R "$WEB_USER":"$WEB_GROUP" "$APP_DIR"
chmod -R 775 storage bootstrap/cache
chmod +x artisan

# -----------------------------------------------
# 4. Environment file
# -----------------------------------------------
echo "[4/9] Setting up environment..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "    Copied .env.example -> .env"
    echo "    *** EDIT .env NOW: set DB_PASSWORD and APP_KEY ***"
fi

# Generate app key if not set
if grep -q "^APP_KEY=$" .env; then
    sudo -u "$WEB_USER" php artisan key:generate --force
    echo "    Generated APP_KEY"
fi

# -----------------------------------------------
# 5. Install dependencies AS the web user
# -----------------------------------------------
echo "[5/9] Installing PHP dependencies..."
sudo -u "$WEB_USER" composer install --no-dev --optimize-autoloader --no-interaction

echo "[6/9] Installing Node dependencies..."
npm ci --production 2>/dev/null || npm install --production

# -----------------------------------------------
# 7. Build frontend
# -----------------------------------------------
echo "[7/9] Building assets..."
npm run build

# -----------------------------------------------
# 8. Database & caches
# -----------------------------------------------
echo "[8/9] Running migrations and seeding..."
sudo -u "$WEB_USER" php artisan migrate --force
sudo -u "$WEB_USER" php artisan db:seed --class=CategorySeeder --force

echo "[9/9] Caching configuration..."
sudo -u "$WEB_USER" php artisan config:cache
sudo -u "$WEB_USER" php artisan route:cache
sudo -u "$WEB_USER" php artisan view:cache

# -----------------------------------------------
# Fix ownership again (npm/build may have created files as root)
# -----------------------------------------------
chown -R "$WEB_USER":"$WEB_GROUP" "$APP_DIR"

# -----------------------------------------------
# Restart services
# -----------------------------------------------
echo ""
echo "Restarting services..."
sudo -u "$WEB_USER" php artisan queue:restart 2>/dev/null || true

FPM=$(systemctl list-units --type=service --state=running 2>/dev/null | grep -oP 'php[\d.]+-fpm\.service' | head -1)
if [ -n "$FPM" ]; then
    systemctl restart "$FPM"
    echo "    Restarted $FPM"
fi

systemctl restart nginx
echo "    Restarted nginx"

# -----------------------------------------------
# Verify
# -----------------------------------------------
echo ""
echo "=============================="
echo " Deploy complete!"
echo "=============================="
echo ""
echo "Verify:"
echo "  redis-cli ping                     # should return PONG"
echo "  curl -I http://localhost            # should return 200"
echo "  tail storage/logs/laravel.log       # check for errors"
echo ""
echo "If you need to set the DB password:"
echo "  nano $APP_DIR/.env"
echo "  sudo -u $WEB_USER php artisan config:cache"
