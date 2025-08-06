#!/bin/bash

# Installation script for AppBuilderForClaudeCode on Debian
# Target directory: /srv/app/AppBuilderForClaudeCode

set -e  # Exit on error

echo "======================================"
echo "AppBuilderForClaudeCode - Debian Setup"
echo "======================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
   echo "Please run as root (use sudo)"
   exit 1
fi

# Set installation path
INSTALL_PATH="/srv/app/AppBuilderForClaudeCode"

# Check if already installed
if [ -d "$INSTALL_PATH" ]; then
    echo "Application already exists at $INSTALL_PATH"
    read -p "Do you want to update permissions only? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
    UPDATE_ONLY=true
else
    UPDATE_ONLY=false
fi

if [ "$UPDATE_ONLY" = false ]; then
    echo "Step 1: Installing system dependencies..."
    apt-get update
    apt-get install -y \
        php8.2-cli \
        php8.2-fpm \
        php8.2-mysql \
        php8.2-xml \
        php8.2-mbstring \
        php8.2-curl \
        php8.2-zip \
        php8.2-gd \
        php8.2-bcmath \
        php8.2-intl \
        composer \
        nginx \
        mysql-server \
        screen \
        git \
        curl \
        wget

    echo ""
    echo "Step 2: Installing Node.js..."
    if ! command -v node &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
        apt-get install -y nodejs
    fi

    echo ""
    echo "Step 3: Creating application directory..."
    mkdir -p /srv/app
    cd /srv/app

    echo ""
    echo "Step 4: Cloning repository (or copying files)..."
    echo "Please provide the repository URL or path to your application files:"
    read -p "Repository URL or local path: " SOURCE_PATH
    
    if [[ $SOURCE_PATH == http* ]] || [[ $SOURCE_PATH == git* ]]; then
        git clone "$SOURCE_PATH" AppBuilderForClaudeCode
    else
        cp -r "$SOURCE_PATH" AppBuilderForClaudeCode
    fi
fi

cd "$INSTALL_PATH"

echo ""
echo "Step 5: Setting up Laravel application..."

# Copy .env file if it doesn't exist
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "Created .env file from .env.example"
        echo "Please edit .env file with your database credentials"
    else
        echo "Warning: No .env file found. Please create one."
    fi
fi

# Install composer dependencies
echo "Installing composer dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader

# Install npm dependencies
echo "Installing npm dependencies..."
npm install
npm run build

echo ""
echo "Step 6: Setting up permissions..."

# Create necessary directories
mkdir -p storage/app/claude-sessions
mkdir -p storage/app/claude-home
mkdir -p storage/logs
mkdir -p storage/app/public/screenshots
mkdir -p bootstrap/cache

# Set ownership
chown -R www-data:www-data .
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# Make scripts executable
chmod +x scripts/claude-screen-manager.sh
chmod +x scripts/setup-permissions.sh
chmod +x scripts/install-debian.sh

echo ""
echo "Step 7: Configuring sudo permissions for Claude manager..."

# Copy sudoers file
cp scripts/claude-sudoers /etc/sudoers.d/claude-screen-manager
chmod 0440 /etc/sudoers.d/claude-screen-manager
echo "Sudoers configured for www-data user"

echo ""
echo "Step 8: Setting up Laravel..."

# Generate application key if needed
sudo -u www-data php artisan key:generate --force

# Run migrations
echo "Running database migrations..."
sudo -u www-data php artisan migrate --force

# Clear and cache configs
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Create storage link
sudo -u www-data php artisan storage:link

echo ""
echo "Step 9: Configuring Nginx..."

# Create Nginx configuration
cat > /etc/nginx/sites-available/appbuilder << 'EOF'
server {
    listen 80;
    server_name appbuilderforclaudecode.test;
    root /srv/app/AppBuilderForClaudeCode/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Enable site
ln -sf /etc/nginx/sites-available/appbuilder /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

echo ""
echo "Step 10: Installing Claude CLI..."

if ! command -v claude &> /dev/null; then
    echo "Claude CLI not found. Please install it manually:"
    echo "Visit: https://docs.anthropic.com/claude/docs/claude-cli"
    echo ""
    echo "For Debian, you can try:"
    echo "curl -fsSL https://claude.ai/install.sh | sh"
else
    echo "Claude CLI already installed at: $(which claude)"
fi

echo ""
echo "======================================"
echo "Installation Complete!"
echo "======================================"
echo ""
echo "Next steps:"
echo "1. Edit the .env file with your database credentials:"
echo "   nano $INSTALL_PATH/.env"
echo ""
echo "2. Set your domain in /etc/hosts or DNS"
echo ""
echo "3. Install Claude CLI if not already installed"
echo ""
echo "4. Access the application at http://appbuilderforclaudecode.test"
echo ""
echo "5. Create an admin user:"
echo "   cd $INSTALL_PATH"
echo "   sudo -u www-data php artisan make:filament-user"
echo ""
echo "Logs are available at:"
echo "  - Laravel: $INSTALL_PATH/storage/logs/"
echo "  - Claude: $INSTALL_PATH/storage/logs/claude-screen.log"
echo "  - Nginx: /var/log/nginx/"
echo ""