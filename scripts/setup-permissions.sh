#!/bin/bash

# Setup script for Claude Screen Manager on Debian
# Designed for installation at /srv/app/AppBuilderForClaudeCode
# Run this after deployment or when permissions issues occur

echo "Setting up Claude Screen Manager for Debian..."

# Use production path on Debian, fallback to dynamic detection
if [ -d "/srv/app/AppBuilderForClaudeCode" ]; then
    BASE_PATH="/srv/app/AppBuilderForClaudeCode"
else
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    BASE_PATH="$( cd "$SCRIPT_DIR/.." && pwd )"
fi

echo "Base path: $BASE_PATH"

# Create necessary directories
echo "Creating necessary directories..."
mkdir -p "$BASE_PATH/storage/app/claude-sessions"
mkdir -p "$BASE_PATH/storage/logs"
mkdir -p "$BASE_PATH/storage/app/public/screenshots"

# Set permissions for storage directories
echo "Setting storage permissions..."
chmod -R 775 "$BASE_PATH/storage"
chmod -R 775 "$BASE_PATH/bootstrap/cache"

# Make scripts executable
echo "Making scripts executable..."
chmod +x "$BASE_PATH/scripts/claude-screen-manager.sh"
chmod +x "$BASE_PATH/scripts/setup-permissions.sh"

# Detect web server user
if id -u www-data >/dev/null 2>&1; then
    WEB_USER="www-data"
    WEB_GROUP="www-data"
elif id -u _www >/dev/null 2>&1; then
    WEB_USER="_www"
    WEB_GROUP="_www"
else
    WEB_USER=$(whoami)
    WEB_GROUP=$(whoami)
    echo "Warning: Could not detect web server user, using current user: $WEB_USER"
fi

echo "Web server user: $WEB_USER:$WEB_GROUP"

# Set ownership for storage directories
echo "Setting ownership..."
chown -R "$WEB_USER:$WEB_GROUP" "$BASE_PATH/storage" 2>/dev/null || echo "Could not change ownership (may need sudo)"
chown -R "$WEB_USER:$WEB_GROUP" "$BASE_PATH/bootstrap/cache" 2>/dev/null || echo "Could not change ownership (may need sudo)"

# Create .env file check
if [ ! -f "$BASE_PATH/.env" ]; then
    echo "Warning: .env file not found!"
fi

# Check if Claude is installed
if ! command -v claude &> /dev/null; then
    echo "Warning: Claude CLI not found. Please install Claude CLI."
    echo "Visit: https://docs.anthropic.com/claude/docs/claude-cli"
else
    echo "Claude CLI found at: $(which claude)"
fi

# Check if screen is installed (Debian)
if ! command -v screen &> /dev/null; then
    echo "Warning: screen not installed. Installing..."
    # Debian/Ubuntu installation
    sudo apt-get update && sudo apt-get install -y screen
else
    echo "screen found at: $(which screen)"
fi

# Check Node.js installation on Debian
if ! command -v node &> /dev/null && ! command -v nodejs &> /dev/null; then
    echo "Warning: Node.js not found. Installing..."
    # Install Node.js on Debian (via NodeSource repository for latest version)
    curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
    sudo apt-get install -y nodejs
else
    if command -v node &> /dev/null; then
        echo "Node.js found at: $(which node)"
    else
        echo "Node.js found at: $(which nodejs)"
    fi
fi

echo ""
echo "Setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure sudo permissions by running:"
echo "   sudo visudo"
echo "   And add this line:"
echo "   $WEB_USER ALL=(ALL) NOPASSWD: $BASE_PATH/scripts/claude-screen-manager.sh"
echo ""
echo "2. If on production, run:"
echo "   php artisan config:cache"
echo "   php artisan route:cache"
echo "   php artisan view:cache"
echo ""
echo "3. Test the setup by creating a project and starting Claude"