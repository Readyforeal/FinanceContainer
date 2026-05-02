#!/bin/bash
set -e

APP_DIR="/home/jamie/FinanceContainer"
APP_USER="jamie"
WEB_USER="www-data"

echo "=============================="
echo " StewardAI Full Reset & Deploy"
echo "=============================="
echo ""
cd "$APP_DIR"

# -----------------------------------------------
# 1. Fix ownership (jamie owns app, www-data owns storage)
# -----------------------------------------------
echo "[1/9] Fixing ownership..."
chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

# -----------------------------------------------
# 2. Nuke generated files
# -----------------------------------------------
echo "[2/9] Wiping generated files..."
rm -rf storage/framework/cache/data/*
rm -rf storage/framework/sessions/*
rm -rf storage/framework/views/*
rm -rf storage/logs/*
rm -rf bootstrap/cache/*.php
rm -rf vendor node_modules public/build

# -----------------------------------------------
# 3. Recreate storage structure
# -----------------------------------------------
echo "[3/9] Rebuilding storage directories..."
mkdir -p storage/framework/{cache/data,sessions,views,testing}
mkdir -p storage/logs
mkdir -p bootstrap/cache
touch storage/logs/laravel.log

# -----------------------------------------------
# 4. Environment file
# -----------------------------------------------
echo "[4/9] Setting up environment..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "    Copied .env.example -> .env"
    echo ""
    echo "    *** Set your DB_PASSWORD in .env before continuing ***"
    echo "    nano $APP_DIR/.env"
    echo ""
    read -p "    Press Enter after editing .env..."
fi

# -----------------------------------------------
# 5. Install dependencies (as jamie, not www-data)
# -----------------------------------------------
echo "[5/9] Installing PHP dependencies..."
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction

echo "[6/9] Installing Node dependencies & building..."
sudo -u "$APP_USER" npm ci --production 2>/dev/null || sudo -u "$APP_USER" npm install --production
sudo -u "$APP_USER" npm run build

# -----------------------------------------------
# 7. Give www-data write access to storage + cache only
# -----------------------------------------------
echo "[7/9] Setting web server permissions..."
chown -R "$WEB_USER":"$WEB_USER" storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# -----------------------------------------------
# 8. Laravel setup (as www-data)
# -----------------------------------------------
echo "[8/9] Laravel setup..."
if grep -q "^APP_KEY=$" .env 2>/dev/null; then
    sudo -u "$WEB_USER" php artisan key:generate --force
    echo "    Generated APP_KEY"
fi

sudo -u "$WEB_USER" php artisan migrate --force
sudo -u "$WEB_USER" php artisan db:seed --class=CategorySeeder --force
sudo -u "$WEB_USER" php artisan config:cache
sudo -u "$WEB_USER" php artisan route:cache
sudo -u "$WEB_USER" php artisan view:cache

# -----------------------------------------------
# 9. Restart services
# -----------------------------------------------
echo "[9/9] Restarting services..."
sudo -u "$WEB_USER" php artisan queue:restart 2>/dev/null || true

FPM=$(systemctl list-units --type=service --state=running 2>/dev/null | grep -oP 'php[\d.]+-fpm\.service' | head -1)
if [ -n "$FPM" ]; then
    systemctl restart "$FPM"
    echo "    Restarted $FPM"
fi

systemctl restart nginx
echo "    Restarted nginx"

echo ""
echo "=============================="
echo " Deploy complete!"
echo "=============================="
echo ""
echo "Verify:"
echo "  redis-cli ping"
echo "  curl -I http://localhost"
echo "  tail storage/logs/laravel.log"
