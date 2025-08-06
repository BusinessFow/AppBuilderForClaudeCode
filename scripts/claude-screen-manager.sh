#!/bin/bash

# Claude Screen Manager Script for Debian
# This script manages screen sessions for Claude with proper permissions
# Designed for Debian installation at /srv/app/AppBuilderForClaudeCode
# Usage: ./claude-screen-manager.sh <action> <project_id> [additional_params]

ACTION=$1
PROJECT_ID=$2
SCREEN_NAME="claude_${PROJECT_ID}"

# Debian-specific paths
CLAUDE_PATH="/usr/local/bin/claude"

# For Debian systems, use the actual installation path
# If running from development, detect dynamically; otherwise use production path
if [ -d "/srv/app/AppBuilderForClaudeCode" ]; then
    BASE_PATH="/srv/app/AppBuilderForClaudeCode"
else
    # Fallback for development environment
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    BASE_PATH="$( cd "$SCRIPT_DIR/.." && pwd )"
fi

# Function to check if screen session exists
screen_exists() {
    screen -ls | grep -q "$1"
    return $?
}

# Function to log messages
log_message() {
    LOG_DIR="${BASE_PATH}/storage/logs"
    mkdir -p "$LOG_DIR"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG_DIR}/claude-screen.log"
}

case "$ACTION" in
    start)
        PROJECT_PATH=$3
        SESSION_ID=$4
        COMM_DIR="${BASE_PATH}/storage/app/claude-sessions/${SESSION_ID}"
        LOG_FILE="${COMM_DIR}/claude.log"
        INPUT_PIPE="${COMM_DIR}/input.pipe"
        
        # Create communication directory if it doesn't exist
        mkdir -p "$COMM_DIR"
        chmod 755 "$COMM_DIR"
        # Try to set ownership - use current user if www-data/www doesn't exist
        chown -R www-data:www-data "$COMM_DIR" 2>/dev/null || \
        chown -R _www:_www "$COMM_DIR" 2>/dev/null || \
        chown -R $(whoami):$(whoami) "$COMM_DIR" 2>/dev/null
        
        # Kill any existing screen session
        if screen_exists "$SCREEN_NAME"; then
            screen -S "$SCREEN_NAME" -X quit 2>/dev/null
            sleep 1
        fi
        
        # Create named pipe for input
        if [ -e "$INPUT_PIPE" ]; then
            rm -f "$INPUT_PIPE"
        fi
        mkfifo "$INPUT_PIPE"
        chmod 666 "$INPUT_PIPE"
        
        # Clear log file
        > "$LOG_FILE"
        chmod 666 "$LOG_FILE"
        
        # Start screen session with Claude
        export PATH="/usr/local/bin:$PATH"
        
        # Create the command to run
        CLAUDE_CMD="cd '$PROJECT_PATH' && echo 'Working directory: '$(pwd) && tail -f '$INPUT_PIPE' | '$CLAUDE_PATH' chat --no-color 2>&1 | tee '$LOG_FILE'"
        
        # Start screen session
        screen -dmS "$SCREEN_NAME" bash -c "$CLAUDE_CMD"
        
        # Wait a moment for screen to start
        sleep 1
        
        # Get PID of screen session
        PID=$(screen -ls | grep "$SCREEN_NAME" | awk '{print $1}' | cut -d. -f1)
        
        if [ -n "$PID" ]; then
            echo "$PID"
            log_message "Started screen session $SCREEN_NAME with PID $PID for project $PROJECT_ID"
            exit 0
        else
            log_message "Failed to start screen session $SCREEN_NAME for project $PROJECT_ID"
            exit 1
        fi
        ;;
        
    stop)
        if screen_exists "$SCREEN_NAME"; then
            screen -S "$SCREEN_NAME" -X quit
            log_message "Stopped screen session $SCREEN_NAME for project $PROJECT_ID"
            echo "Screen session stopped"
        else
            log_message "Screen session $SCREEN_NAME not found for project $PROJECT_ID"
            echo "Screen session not found"
        fi
        exit 0
        ;;
        
    send)
        MESSAGE=$3
        SESSION_ID=$4
        INPUT_PIPE="${BASE_PATH}/storage/app/claude-sessions/${SESSION_ID}/input.pipe"
        
        if ! screen_exists "$SCREEN_NAME"; then
            echo "Screen session not found"
            log_message "Cannot send message - screen session $SCREEN_NAME not found"
            exit 1
        fi
        
        if [ ! -p "$INPUT_PIPE" ]; then
            echo "Input pipe not found"
            log_message "Cannot send message - input pipe not found at $INPUT_PIPE"
            exit 1
        fi
        
        # Send message to named pipe
        echo "$MESSAGE" > "$INPUT_PIPE"
        
        log_message "Sent message to $SCREEN_NAME: $MESSAGE"
        echo "Message sent"
        exit 0
        ;;
        
    status)
        if screen_exists "$SCREEN_NAME"; then
            PID=$(screen -ls | grep "$SCREEN_NAME" | awk '{print $1}' | cut -d. -f1)
            echo "running:$PID"
        else
            echo "stopped"
        fi
        exit 0
        ;;
        
    list)
        screen -ls | grep "claude_" | while read line; do
            echo "$line"
        done
        exit 0
        ;;
        
    attach)
        if screen_exists "$SCREEN_NAME"; then
            screen -r "$SCREEN_NAME"
        else
            echo "Screen session not found"
            exit 1
        fi
        ;;
        
    *)
        echo "Usage: $0 {start|stop|send|status|list|attach} <project_id> [additional_params]"
        echo ""
        echo "Actions:"
        echo "  start <project_id> <project_path> <session_id>  - Start Claude screen session"
        echo "  stop <project_id>                                - Stop Claude screen session"
        echo "  send <project_id> <message> <session_id>         - Send message to Claude"
        echo "  status <project_id>                              - Check if screen session is running"
        echo "  list                                             - List all Claude screen sessions"
        echo "  attach <project_id>                              - Attach to screen session (for debugging)"
        exit 1
        ;;
esac