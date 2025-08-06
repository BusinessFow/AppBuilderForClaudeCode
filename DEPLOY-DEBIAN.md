# Deployment Guide for Debian

This guide covers deploying AppBuilderForClaudeCode on a Debian server at `/srv/app/AppBuilderForClaudeCode`.

## Prerequisites

- Debian 11 (Bullseye) or Debian 12 (Bookworm)
- Root or sudo access
- At least 2GB RAM
- 10GB free disk space

## Quick Installation

1. **Download the application**:
```bash
sudo mkdir -p /srv/app
cd /srv/app
git clone [your-repository-url] AppBuilderForClaudeCode
cd AppBuilderForClaudeCode
```

2. **Run the installation script**:
```bash
sudo bash scripts/install-debian.sh
```

This script will:
- Install all system dependencies (PHP, MySQL, Nginx, Node.js, etc.)
- Configure permissions
- Set up the database
- Configure Nginx
- Set up sudo permissions for Claude manager

## Manual Installation

### Step 1: Install System Dependencies

```bash
sudo apt update
sudo apt install -y \
    php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd \
    php8.2-bcmath php8.2-intl \
    composer nginx mysql-server screen git curl wget
```

### Step 2: Install Node.js

```bash
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt install -y nodejs
```

### Step 3: Install Claude CLI

```bash
# Follow official Claude CLI installation guide
# Visit: https://docs.anthropic.com/claude/docs/claude-cli
```

### Step 4: Set Up Application

```bash
cd /srv/app/AppBuilderForClaudeCode

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env with your database credentials
nano .env
```

### Step 5: Set Permissions

```bash
# Run the setup script
sudo bash scripts/setup-permissions.sh

# Or manually:
sudo chown -R www-data:www-data /srv/app/AppBuilderForClaudeCode
sudo chmod -R 755 storage bootstrap/cache
sudo chmod +x scripts/*.sh
```

### Step 6: Configure Sudo for Claude Manager

```bash
sudo cp scripts/claude-sudoers /etc/sudoers.d/claude-screen-manager
sudo chmod 0440 /etc/sudoers.d/claude-screen-manager
```

### Step 7: Configure Database

```bash
# Create database
mysql -u root -p
CREATE DATABASE appbuilder;
CREATE USER 'appbuilder'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON appbuilder.* TO 'appbuilder'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php artisan migrate
```

### Step 8: Configure Nginx

Create `/etc/nginx/sites-available/appbuilder`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
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
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/appbuilder /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 9: Set Up Queue Worker (Optional)

```bash
sudo cp scripts/appbuilder-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable appbuilder-queue
sudo systemctl start appbuilder-queue
```

### Step 10: Create Admin User

```bash
cd /srv/app/AppBuilderForClaudeCode
sudo -u www-data php artisan make:filament-user
```

## Directory Structure

```
/srv/app/AppBuilderForClaudeCode/
├── storage/
│   ├── app/
│   │   └── claude-sessions/    # Claude session files
│   └── logs/
│       └── claude-screen.log   # Claude manager logs
├── scripts/
│   ├── claude-screen-manager.sh
│   ├── setup-permissions.sh
│   └── install-debian.sh
└── public/                      # Nginx document root
```

## Troubleshooting

### Permission Issues

```bash
# Reset permissions
sudo bash /srv/app/AppBuilderForClaudeCode/scripts/setup-permissions.sh
```

### Claude Not Starting

1. Check if Claude CLI is installed:
```bash
which claude
```

2. Check screen sessions:
```bash
screen -ls
```

3. Check logs:
```bash
tail -f /srv/app/AppBuilderForClaudeCode/storage/logs/claude-screen.log
tail -f /srv/app/AppBuilderForClaudeCode/storage/logs/laravel.log
```

### Database Connection Issues

```bash
# Test MySQL connection
mysql -u appbuilder -p appbuilder

# Check .env file
cat /srv/app/AppBuilderForClaudeCode/.env | grep DB_
```

## Maintenance

### Update Application

```bash
cd /srv/app/AppBuilderForClaudeCode
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Monitor Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Claude manager logs
tail -f storage/logs/claude-screen.log

# Nginx logs
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log
```

### Restart Services

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
sudo systemctl restart appbuilder-queue  # if using queue worker
```

## Security Recommendations

1. **Use HTTPS**: Install Let's Encrypt SSL certificate
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

2. **Configure Firewall**:
```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

3. **Regular Updates**:
```bash
sudo apt update && sudo apt upgrade
```

4. **Backup Database**:
```bash
mysqldump -u appbuilder -p appbuilder > backup.sql
```

## Support

For issues specific to Debian deployment, check:
- Logs at `/srv/app/AppBuilderForClaudeCode/storage/logs/`
- System logs: `journalctl -xe`
- Nginx logs: `/var/log/nginx/`