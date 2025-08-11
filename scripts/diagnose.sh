#!/bin/bash

# Diagnostic Script for AppBuilderForClaudeCode
# Helps identify common issues with Claude integration

echo "======================================"
echo "AppBuilderForClaudeCode Diagnostics"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Use production path on Debian, fallback to dynamic detection
if [ -d "/srv/app/AppBuilderForClaudeCode" ]; then
    BASE_PATH="/srv/app/AppBuilderForClaudeCode"
else
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    BASE_PATH="$( cd "$SCRIPT_DIR/.." && pwd )"
fi

echo "Base path: $BASE_PATH"
echo ""

# Function to print colored status
print_status() {
    if [ "$1" = "ok" ]; then
        echo -e "${GREEN}✓${NC} $2"
    elif [ "$1" = "warning" ]; then
        echo -e "${YELLOW}⚠${NC} $2"
    else
        echo -e "${RED}✗${NC} $2"
    fi
}

echo "1. System Information"
echo "====================="
echo "OS: $(lsb_release -d 2>/dev/null | cut -f2 || uname -a)"
echo "Current user: $(whoami)"
echo "PHP version: $(php -v | head -n 1)"
echo "Node version: $(node -v 2>/dev/null || echo 'Not installed')"
echo ""

echo "2. Required Software"
echo "===================="

# Check Claude CLI
if command -v claude &> /dev/null; then
    print_status "ok" "Claude CLI: $(which claude)"
    # Check if claude is working
    if claude --version &>/dev/null; then
        print_status "ok" "Claude CLI is working"
    else
        print_status "error" "Claude CLI is installed but not working"
    fi
else
    print_status "error" "Claude CLI not found"
    echo "  Install with: npm install -g @anthropic-ai/claude-code"
fi

# Check screen
if command -v screen &> /dev/null; then
    print_status "ok" "screen: $(which screen)"
else
    print_status "error" "screen not installed"
    echo "  Install with: sudo apt-get install screen"
fi

# Check web server user
if id -u www-data >/dev/null 2>&1; then
    print_status "ok" "Web user www-data exists"
    WEB_USER="www-data"
elif id -u _www >/dev/null 2>&1; then
    print_status "ok" "Web user _www exists"
    WEB_USER="_www"
else
    print_status "warning" "Standard web user not found"
    WEB_USER=$(whoami)
fi
echo ""

echo "3. Directory Structure"
echo "======================"

# Check required directories
dirs=(
    "storage/app/claude-sessions"
    "storage/app/claude-home"
    "storage/logs"
    "storage/app/public"
    "bootstrap/cache"
)

for dir in "${dirs[@]}"; do
    if [ -d "$BASE_PATH/$dir" ]; then
        perms=$(stat -c %a "$BASE_PATH/$dir" 2>/dev/null || stat -f %A "$BASE_PATH/$dir" 2>/dev/null)
        owner=$(stat -c %U "$BASE_PATH/$dir" 2>/dev/null || stat -f %Su "$BASE_PATH/$dir" 2>/dev/null)
        
        if [ -w "$BASE_PATH/$dir" ]; then
            print_status "ok" "$dir (perms: $perms, owner: $owner)"
        else
            print_status "warning" "$dir not writable (perms: $perms, owner: $owner)"
        fi
    else
        print_status "error" "$dir does not exist"
    fi
done
echo ""

echo "4. Script Permissions"
echo "===================="

scripts=(
    "scripts/claude-screen-manager.sh"
    "scripts/fix-permissions.sh"
    "scripts/diagnose.sh"
)

for script in "${scripts[@]}"; do
    if [ -f "$BASE_PATH/$script" ]; then
        if [ -x "$BASE_PATH/$script" ]; then
            print_status "ok" "$script is executable"
        else
            print_status "error" "$script is not executable"
        fi
    else
        print_status "error" "$script not found"
    fi
done
echo ""

echo "5. Sudo Configuration"
echo "===================="

SUDOERS_FILE="/etc/sudoers.d/claude-screen-manager"
if [ -f "$SUDOERS_FILE" ]; then
    if sudo -n -l 2>/dev/null | grep -q "claude-screen-manager.sh"; then
        print_status "ok" "Sudoers configured for claude-screen-manager.sh"
    else
        print_status "warning" "Sudoers file exists but may not be properly configured"
        echo "  Check with: sudo visudo -c -f $SUDOERS_FILE"
    fi
else
    print_status "error" "Sudoers file not found at $SUDOERS_FILE"
    echo "  Run: sudo $BASE_PATH/scripts/fix-permissions.sh"
fi
echo ""

echo "6. Laravel Configuration"
echo "========================"

cd "$BASE_PATH"

# Check .env file
if [ -f .env ]; then
    print_status "ok" ".env file exists"
    
    # Check database connection
    if php artisan tinker --execute="DB::connection()->getPdo();" &>/dev/null; then
        print_status "ok" "Database connection working"
    else
        print_status "error" "Database connection failed"
        echo "  Check .env database settings"
    fi
else
    print_status "error" ".env file not found"
    echo "  Create with: cp .env.example .env && php artisan key:generate"
fi

# Check storage link
if [ -L public/storage ]; then
    print_status "ok" "Storage link exists"
else
    print_status "warning" "Storage link missing"
    echo "  Create with: php artisan storage:link"
fi
echo ""

echo "7. Active Screen Sessions"
echo "========================"

# Check for Claude screen sessions
sessions=$(screen -ls 2>/dev/null | grep claude_ || true)
if [ -n "$sessions" ]; then
    print_status "ok" "Found Claude screen sessions:"
    echo "$sessions" | while read line; do
        echo "  - $line"
    done
else
    print_status "warning" "No active Claude screen sessions"
fi
echo ""

echo "8. Recent Logs"
echo "============="

# Check Laravel log
LARAVEL_LOG="$BASE_PATH/storage/logs/laravel.log"
if [ -f "$LARAVEL_LOG" ]; then
    errors=$(tail -n 50 "$LARAVEL_LOG" | grep -i error | wc -l)
    if [ "$errors" -gt 0 ]; then
        print_status "warning" "Found $errors errors in recent Laravel logs"
        echo "  Last error:"
        tail -n 50 "$LARAVEL_LOG" | grep -i error | tail -n 1 | cut -c1-100
    else
        print_status "ok" "No recent errors in Laravel log"
    fi
else
    print_status "warning" "Laravel log not found"
fi

# Check Claude manager log
CLAUDE_LOG="$BASE_PATH/storage/logs/claude-screen.log"
if [ -f "$CLAUDE_LOG" ]; then
    print_status "ok" "Claude manager log exists"
    echo "  Last entry: $(tail -n 1 "$CLAUDE_LOG" | cut -c1-100)"
else
    print_status "warning" "Claude manager log not found"
fi
echo ""

echo "9. Testing Claude Manager"
echo "========================"

# Test claude-screen-manager.sh
MANAGER_SCRIPT="$BASE_PATH/scripts/claude-screen-manager.sh"
if [ -x "$MANAGER_SCRIPT" ]; then
    # Test list command
    if $MANAGER_SCRIPT list &>/dev/null; then
        print_status "ok" "Claude manager script is working"
    else
        print_status "error" "Claude manager script failed"
        echo "  Test manually: $MANAGER_SCRIPT list"
    fi
else
    print_status "error" "Claude manager script not executable"
fi
echo ""

echo "10. Quick Fixes"
echo "==============="
echo ""

issues=0

# Check if permissions need fixing
if [ ! -w "$BASE_PATH/storage" ]; then
    echo "• Storage not writable. Fix with:"
    echo "  sudo $BASE_PATH/scripts/fix-permissions.sh"
    ((issues++))
fi

# Check if Claude CLI needs installation
if ! command -v claude &> /dev/null; then
    echo "• Claude CLI not installed. Install with:"
    echo "  npm install -g @anthropic-ai/claude-code"
    ((issues++))
fi

# Check if screen needs installation
if ! command -v screen &> /dev/null; then
    echo "• screen not installed. Install with:"
    echo "  sudo apt-get install screen"
    ((issues++))
fi

# Check if sudoers needs configuration
if [ ! -f "$SUDOERS_FILE" ]; then
    echo "• Sudoers not configured. Fix with:"
    echo "  sudo $BASE_PATH/scripts/fix-permissions.sh"
    ((issues++))
fi

if [ "$issues" -eq 0 ]; then
    print_status "ok" "No issues detected!"
else
    echo ""
    print_status "warning" "Found $issues issue(s) that need attention"
fi
echo ""

echo "======================================"
echo "Diagnostics Complete"
echo "======================================"
echo ""
echo "If issues persist after fixes, check:"
echo "1. Full Laravel log: tail -f $BASE_PATH/storage/logs/laravel.log"
echo "2. Claude log: tail -f $BASE_PATH/storage/logs/claude-screen.log"
echo "3. System log: journalctl -xe"
echo "4. Nginx error log: tail -f /var/log/nginx/error.log"