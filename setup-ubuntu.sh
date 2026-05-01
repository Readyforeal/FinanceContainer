#!/bin/bash
set -euo pipefail

# ============================================================================
# Better With 90 — Native Ubuntu Setup Script
# Run from the repo root. The repo itself becomes the live app directory.
# ============================================================================

APP_NAME="better-with-90"
APP_DOMAIN="finance.home"
DB_NAME="steward"
DB_USER="steward"
DB_PASS="steward_secret_$(openssl rand -hex 4)"
PHP_VERSION="8.4"
APP_DIR="$(cd "$(dirname "$0")" && pwd)"

echo ""
echo "======================================"
echo "  Better With 90 — Ubuntu Setup"
echo "======================================"
echo ""
echo "App directory: $APP_DIR"
echo "App will be available at: http://$APP_DOMAIN"
echo ""
read -p "Press Enter to continue (Ctrl+C to cancel)..."

# ============================================================================
# 1. System packages
# ============================================================================
echo ""
echo "[1/8] Installing system packages..."

sudo apt update
sudo apt install -y \
    nginx \
    postgresql postgresql-contrib \
    redis-server \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-gd \
    unzip \
    git \
    curl

if ! command -v node &> /dev/null; then
    echo "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo apt install -y nodejs
fi

if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

echo "  Done."

# ============================================================================
# 2. Ollama
# ============================================================================
echo ""
echo "[2/8] Installing Ollama..."

if ! command -v ollama &> /dev/null; then
    curl -fsSL https://ollama.com/install.sh | sh
fi

sudo systemctl enable ollama
sudo systemctl start ollama

echo "  Ollama installed."

# ============================================================================
# 3. PostgreSQL setup
# ============================================================================
echo ""
echo "[3/8] Configuring PostgreSQL..."

sudo systemctl enable postgresql
sudo systemctl start postgresql

sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}_test'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME}_test OWNER $DB_USER;"

sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME}_test TO $DB_USER;"

echo "  Database '$DB_NAME' created."

# ============================================================================
# 4. Redis setup
# ============================================================================
echo ""
echo "[4/8] Configuring Redis..."

sudo systemctl enable redis-server
sudo systemctl start redis-server

echo "  Redis running."

# ============================================================================
# 5. Deploy application (in-place — repo IS the app)
# ============================================================================
echo ""
echo "[5/8] Setting up application..."

cd "$APP_DIR"

# Set permissions for Nginx
sudo chown -R $USER:www-data "$APP_DIR"
sudo chmod -R 775 storage bootstrap/cache

# Write .env
cat > .env <<ENVFILE
APP_NAME="Better With 90"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://$APP_DOMAIN

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

OLLAMA_HOST=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.1:8b

MAIL_MAILER=log
MAIL_FROM_ADDRESS="steward@localhost"
MAIL_FROM_NAME="Better With 90"
ENVFILE

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# Laravel setup
php artisan key:generate --force
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "  Application ready at $APP_DIR"

# ============================================================================
# 6. Nginx configuration
# ============================================================================
echo ""
echo "[6/8] Configuring Nginx..."

LOCAL_IP=$(hostname -I | awk '{print $1}')

sudo tee /etc/nginx/sites-available/$APP_NAME > /dev/null <<NGINX
server {
    listen 80;
    server_name $APP_DOMAIN $LOCAL_IP;
    root $APP_DIR/public;
    index index.php;

    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl enable nginx
sudo systemctl reload nginx

if ! grep -q "$APP_DOMAIN" /etc/hosts; then
    echo "127.0.0.1  $APP_DOMAIN" | sudo tee -a /etc/hosts > /dev/null
fi

echo "  Nginx configured for http://$APP_DOMAIN and http://$LOCAL_IP"

# ============================================================================
# 7. Systemd services
# ============================================================================
echo ""
echo "[7/8] Setting up queue worker and scheduler..."

sudo tee /etc/systemd/system/$APP_NAME-worker.service > /dev/null <<SERVICE
[Unit]
Description=Better With 90 Queue Worker
After=network.target postgresql.service redis-server.service

[Service]
User=$USER
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan queue:work redis --queue=default,ai --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/log/$APP_NAME-worker.log
StandardError=append:/var/log/$APP_NAME-worker.log

[Install]
WantedBy=multi-user.target
SERVICE

sudo tee /etc/systemd/system/$APP_NAME-scheduler.service > /dev/null <<SERVICE
[Unit]
Description=Better With 90 Laravel Scheduler
After=network.target postgresql.service redis-server.service

[Service]
User=$USER
Group=www-data
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=5
StandardOutput=append:/var/log/$APP_NAME-scheduler.log
StandardError=append:/var/log/$APP_NAME-scheduler.log

[Install]
WantedBy=multi-user.target
SERVICE

sudo systemctl daemon-reload
sudo systemctl enable $APP_NAME-worker $APP_NAME-scheduler
sudo systemctl restart $APP_NAME-worker $APP_NAME-scheduler

echo "  Services running."

# ============================================================================
# 8. Ollama model
# ============================================================================
echo ""
echo "[8/8] Pulling Ollama model..."

sudo touch /var/log/$APP_NAME-ollama-pull.log
sudo chown $USER:$USER /var/log/$APP_NAME-ollama-pull.log

if ! ollama list | grep -q "llama3.1:8b"; then
    nohup ollama pull llama3.1:8b > /var/log/$APP_NAME-ollama-pull.log 2>&1 &
    echo "  Model downloading in background. Check: ollama list"
else
    echo "  Model already available."
fi

# ============================================================================
# Done!
# ============================================================================
echo ""
echo "======================================"
echo "  Setup Complete!"
echo "======================================"
echo ""
echo "  App URL:      http://$APP_DOMAIN"
echo "  Also:         http://$LOCAL_IP"
echo "  App Dir:      $APP_DIR"
echo ""
echo "  DB Password:  $DB_PASS"
echo ""
echo "  Login:        admin@steward.local / password"
echo "                member@steward.local / password"
echo ""
echo "  To update after git pull:"
echo "    cd $APP_DIR"
echo "    composer install --no-dev --optimize-autoloader"
echo "    npm ci && npm run build"
echo "    php artisan migrate --force"
echo "    php artisan config:cache && php artisan view:cache"
echo "    sudo systemctl restart $APP_NAME-worker"
echo ""
echo "  Save this DB password: $DB_PASS"
echo ""
