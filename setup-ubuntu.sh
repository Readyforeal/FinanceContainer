#!/bin/bash
set -euo pipefail

# ============================================================================
# Better With 90 — Native Ubuntu Setup Script
# Run as your normal user (NOT root). Will prompt for sudo when needed.
# ============================================================================

APP_NAME="better-with-90"
APP_DIR="/var/www/$APP_NAME"
APP_DOMAIN="finance.local"
DB_NAME="steward"
DB_USER="steward"
DB_PASS="steward_secret_$(openssl rand -hex 4)"
PHP_VERSION="8.4"

echo ""
echo "======================================"
echo "  Better With 90 — Ubuntu Setup"
echo "======================================"
echo ""
echo "This script will install and configure:"
echo "  - PHP $PHP_VERSION + extensions"
echo "  - PostgreSQL 16"
echo "  - Redis"
echo "  - Nginx"
echo "  - Node.js + npm"
echo "  - Composer"
echo "  - Ollama (GPU-accelerated)"
echo ""
echo "App will be available at: http://$APP_DOMAIN"
echo ""
read -p "Press Enter to continue (Ctrl+C to cancel)..."

# ============================================================================
# 1. System packages
# ============================================================================
echo ""
echo "[1/9] Installing system packages..."

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

# Node.js (via NodeSource if not already installed)
if ! command -v node &> /dev/null; then
    echo "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# Composer
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
echo "[2/9] Installing Ollama..."

if ! command -v ollama &> /dev/null; then
    curl -fsSL https://ollama.com/install.sh | sh
fi

# Enable and start Ollama service
sudo systemctl enable ollama
sudo systemctl start ollama

echo "  Ollama installed. Model will be pulled at the end (it's large)."

# ============================================================================
# 3. PostgreSQL setup
# ============================================================================
echo ""
echo "[3/9] Configuring PostgreSQL..."

sudo systemctl enable postgresql
sudo systemctl start postgresql

# Create database and user
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;"

# Also create test database
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}_test'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME}_test OWNER $DB_USER;"

sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME}_test TO $DB_USER;"

echo "  Database '$DB_NAME' created with user '$DB_USER'."

# ============================================================================
# 4. Redis setup
# ============================================================================
echo ""
echo "[4/9] Configuring Redis..."

sudo systemctl enable redis-server
sudo systemctl start redis-server

echo "  Redis running."

# ============================================================================
# 5. Deploy application
# ============================================================================
echo ""
echo "[5/9] Deploying application..."

# Create app directory
sudo mkdir -p $APP_DIR
sudo chown $USER:$USER $APP_DIR

# Copy project files (use rsync to handle hidden files properly)
STEWARD_SRC="$HOME/FinanceContainer/steward"
if [ ! -d "$STEWARD_SRC" ]; then
    # Maybe cloned with a different repo name
    STEWARD_SRC="$HOME/better-with-90/steward"
fi
if [ ! -d "$STEWARD_SRC" ]; then
    echo "ERROR: Could not find the steward/ directory."
    echo "Looked in ~/FinanceContainer/steward/ and ~/better-with-90/steward/"
    echo "Make sure you've cloned the repo or extracted the tarball."
    exit 1
fi

# Copy everything including hidden files
cp -a "$STEWARD_SRC/." "$APP_DIR/"

# Verify critical files exist
if [ ! -f "$APP_DIR/artisan" ]; then
    echo "ERROR: artisan file not found in $APP_DIR after copy."
    echo "Contents of $APP_DIR:"
    ls -la "$APP_DIR/"
    exit 1
fi

# Set permissions for Nginx
sudo chown -R $USER:www-data $APP_DIR
sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

# Configure .env FIRST (before any artisan or composer commands)
cd $APP_DIR

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

MAIL_MAILER=log
MAIL_FROM_ADDRESS="steward@localhost"
MAIL_FROM_NAME="Better With 90"
ENVFILE

# Install PHP dependencies FIRST (artisan needs vendor/autoload.php)
composer install --no-dev --optimize-autoloader --no-interaction

# Now we can use artisan
php artisan key:generate --force
php artisan config:clear
php artisan cache:clear 2>/dev/null || true

# Install and build frontend
npm ci
npm run build

# Verify DB connection before migrating
echo "  Verifying database connection..."
php artisan db:show --database=pgsql 2>/dev/null || {
    echo "  WARNING: Could not connect to PostgreSQL. Checking .env..."
    grep "DB_" .env
    echo ""
    echo "  Make sure PostgreSQL is running: sudo systemctl status postgresql"
    exit 1
}

# Run migrations and seed
php artisan migrate --force
php artisan db:seed --force

# Cache config for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "  Application deployed to $APP_DIR"

# ============================================================================
# 6. Nginx configuration
# ============================================================================
echo ""
echo "[6/9] Configuring Nginx..."

sudo tee /etc/nginx/sites-available/$APP_NAME > /dev/null <<NGINX
server {
    listen 80;
    server_name $APP_DOMAIN;
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

# Enable the site
sudo ln -sf /etc/nginx/sites-available/$APP_NAME /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test and reload
sudo nginx -t
sudo systemctl enable nginx
sudo systemctl reload nginx

# Add local domain to hosts file
if ! grep -q "$APP_DOMAIN" /etc/hosts; then
    echo "127.0.0.1  $APP_DOMAIN" | sudo tee -a /etc/hosts > /dev/null
fi

echo "  Nginx configured for http://$APP_DOMAIN"

# ============================================================================
# 7. Queue worker (systemd service)
# ============================================================================
echo ""
echo "[7/9] Setting up queue worker service..."

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

sudo systemctl daemon-reload
sudo systemctl enable $APP_NAME-worker
sudo systemctl start $APP_NAME-worker

echo "  Queue worker running."

# ============================================================================
# 8. Scheduler (systemd timer)
# ============================================================================
echo ""
echo "[8/9] Setting up Laravel scheduler..."

# Scheduler service
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
sudo systemctl enable $APP_NAME-scheduler
sudo systemctl start $APP_NAME-scheduler

echo "  Scheduler running."

# ============================================================================
# 9. Pull Ollama model
# ============================================================================
echo ""
echo "[9/9] Pulling Ollama model..."
echo "  This downloads ~40GB. It will take a while but runs in the background."
echo "  You can check progress with: ollama list"
echo ""

# Pull in background
nohup ollama pull llama3.1:70b-instruct-q4_K_M > /var/log/$APP_NAME-ollama-pull.log 2>&1 &

# ============================================================================
# Done!
# ============================================================================
echo ""
echo "======================================"
echo "  Setup Complete!"
echo "======================================"
echo ""
echo "  App URL:      http://$APP_DOMAIN"
echo "  DB Name:      $DB_NAME"
echo "  DB User:      $DB_USER"
echo "  DB Password:  $DB_PASS"
echo ""
echo "  Login:        admin@steward.local / password"
echo "                member@steward.local / password"
echo ""
echo "  Services:"
echo "    sudo systemctl status nginx"
echo "    sudo systemctl status php${PHP_VERSION}-fpm"
echo "    sudo systemctl status postgresql"
echo "    sudo systemctl status redis-server"
echo "    sudo systemctl status ollama"
echo "    sudo systemctl status $APP_NAME-worker"
echo "    sudo systemctl status $APP_NAME-scheduler"
echo ""
echo "  Logs:"
echo "    tail -f /var/log/$APP_NAME-worker.log"
echo "    tail -f /var/log/$APP_NAME-scheduler.log"
echo "    tail -f $APP_DIR/storage/logs/laravel.log"
echo ""
echo "  Ollama model is downloading in the background."
echo "  Check progress: ollama list"
echo "  Chat will work once the model finishes downloading."
echo ""
echo "  IMPORTANT: Update your Plaid keys and email settings:"
echo "    nano $APP_DIR/.env"
echo ""
echo "  Change these passwords after first login!"
echo ""
echo "  Save this DB password somewhere safe: $DB_PASS"
echo ""
