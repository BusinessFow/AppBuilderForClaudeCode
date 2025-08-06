# Claude Screen Manager Setup for Debian

## Quick Installation (Debian)

For a complete Debian installation at `/srv/app/AppBuilderForClaudeCode`:

```bash
sudo bash scripts/install-debian.sh
```

## Manual Installation Steps

### 1. Make scripts executable
```bash
chmod +x scripts/claude-screen-manager.sh
chmod +x scripts/setup-permissions.sh
chmod +x scripts/install-debian.sh
```

### 2. Configure sudo permissions (REQUIRED)

For Debian production server:
```bash
# Copy the sudoers configuration
sudo cp scripts/claude-sudoers /etc/sudoers.d/claude-screen-manager
sudo chmod 0440 /etc/sudoers.d/claude-screen-manager

# OR manually edit sudoers:
sudo visudo
# Add this line:
www-data ALL=(ALL) NOPASSWD: /srv/app/AppBuilderForClaudeCode/scripts/claude-screen-manager.sh
```

### 3. Update ClaudeProcessManager.php

If you want to use sudo, update line 52 in `app/Services/ClaudeProcessManager.php`:

```php
// Change from:
exec($command, $output, $returnCode);

// To:
exec('sudo ' . $command, $output, $returnCode);
```

Also update similar lines in:
- Line 144 (sendCommand method)
- Line 208 (stopSession method)  
- Line 297 (isRunning method)

### 4. Test the script manually

```bash
# Start a test session
./scripts/claude-screen-manager.sh start 1 /path/to/project 1

# Check status
./scripts/claude-screen-manager.sh status 1

# Send a message
./scripts/claude-screen-manager.sh send 1 "Hello Claude" 1

# Stop the session
./scripts/claude-screen-manager.sh stop 1
```

### 5. Create log file

```bash
touch storage/logs/claude-screen.log
chmod 666 storage/logs/claude-screen.log
```

## Security Notes

- The script runs screen sessions as the web server user (www-data or _www)
- Each project has its own isolated screen session
- Communication happens through named pipes with proper permissions
- All actions are logged to storage/logs/claude-screen.log

## Troubleshooting

### Permission Denied
- Check sudoers configuration
- Verify script is executable
- Check ownership of storage directories

### Screen not found
- Install screen: `brew install screen` (macOS) or `apt-get install screen` (Linux)

### Claude not found
- Verify Claude CLI is installed at `/usr/local/bin/claude`
- Check PATH in the script

### Named pipe issues
- Ensure the storage/app/claude-sessions directory is writable
- Check that mkfifo is available on your system