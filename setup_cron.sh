#!/bin/bash

# Check if crontab command is available
if ! command -v crontab >/dev/null 2>&1; then
    echo "Error: crontab command not found. Please ensure cron is installed."
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_PHP_PATH="$SCRIPT_DIR/cron.php"

echo "=== Task Scheduler CRON Setup ==="
echo "Script directory: $SCRIPT_DIR"
echo "CRON PHP path: $CRON_PHP_PATH"

if [ ! -f "$CRON_PHP_PATH" ]; then
    echo "Error: cron.php not found in $SCRIPT_DIR"
    exit 1
fi

# Detect PHP binary path for better compatibility
PHP_BINARY=""

# Common PHP binary locations to check
PHP_PATHS=(
    "/usr/bin/php"
    "/usr/local/bin/php"
    "/opt/php/bin/php"
    "/bin/php"
    "/usr/bin/php8"
    "/usr/bin/php7"
    "/usr/local/php/bin/php"
)

# First try to use 'which php' command
if command -v php >/dev/null 2>&1; then
    PHP_BINARY=$(which php)
    echo "✅ Found PHP using 'which': $PHP_BINARY"
else
    echo "⚠️  'which php' failed, checking common locations..."
    
    # Check common locations
    for path in "${PHP_PATHS[@]}"; do
        if [ -x "$path" ]; then
            PHP_BINARY="$path"
            echo "✅ Found PHP at: $PHP_BINARY"
            break
        fi
    done
fi

# If still not found, ask user or use default
if [ -z "$PHP_BINARY" ]; then
    echo "❌ Could not automatically detect PHP binary."
    echo "Common locations checked:"
    for path in "${PHP_PATHS[@]}"; do
        echo "  - $path"
    done
    echo ""
    read -p "Please enter the full path to your PHP binary (or press Enter for /usr/bin/php): " USER_PHP_PATH
    
    if [ -n "$USER_PHP_PATH" ]; then
        if [ -x "$USER_PHP_PATH" ]; then
            PHP_BINARY="$USER_PHP_PATH"
            echo "✅ Using user-specified PHP: $PHP_BINARY"
        else
            echo "❌ Error: $USER_PHP_PATH is not executable or does not exist"
            exit 1
        fi
    else
        PHP_BINARY="/usr/bin/php"
        echo "⚠️  Using default: $PHP_BINARY (may not work on all systems)"
    fi
fi

# Test PHP binary
echo "🧪 Testing PHP binary..."
if "$PHP_BINARY" -v >/dev/null 2>&1; then
    PHP_VERSION=$("$PHP_BINARY" -v | head -n 1)
    echo "✅ PHP test successful: $PHP_VERSION"
else
    echo "❌ Error: PHP binary test failed. Please check the path: $PHP_BINARY"
    exit 1
fi

# Make cron.php executable
chmod +x "$CRON_PHP_PATH"

# Create a temporary file for the new crontab
TEMP_CRONTAB=$(mktemp)

# Get current crontab (ignore errors if no crontab exists)
crontab -l > "$TEMP_CRONTAB" 2>/dev/null || true

# Define the CRON command (runs every hour) with detected PHP binary
CRON_COMMAND="0 * * * * $PHP_BINARY $CRON_PHP_PATH"

# Check if the CRON job already exists
if grep -Fq "$CRON_PHP_PATH" "$TEMP_CRONTAB"; then
    echo "CRON job for Task Scheduler already exists."
    echo "Current crontab entries for this script:"
    grep "$CRON_PHP_PATH" "$TEMP_CRONTAB"
    
    # Ask if user wants to update the existing entry
    read -p "Do you want to update the existing CRON job with the new PHP path? (y/N): " UPDATE_CRON
    if [[ $UPDATE_CRON =~ ^[Yy]$ ]]; then
        # Remove old entries and add new one
        grep -v "$CRON_PHP_PATH" "$TEMP_CRONTAB" > "${TEMP_CRONTAB}.tmp"
        mv "${TEMP_CRONTAB}.tmp" "$TEMP_CRONTAB"
        echo "$CRON_COMMAND" >> "$TEMP_CRONTAB"
        echo "✅ Updated existing CRON job"
    else
        echo "ℹ️  Keeping existing CRON job unchanged"
    fi
else
    # Add the new CRON job
    echo "$CRON_COMMAND" >> "$TEMP_CRONTAB"
    echo "✅ Added new CRON job"
fi

# Install the new crontab
if crontab "$TEMP_CRONTAB"; then
    echo "✅ CRON job successfully installed!"
    echo "Task reminders will be sent every hour."
    echo "Command: $CRON_COMMAND"
else
    echo "❌ Error: Failed to install crontab"
    rm -f "$TEMP_CRONTAB"
    exit 1
fi

# Clean up temporary file
rm -f "$TEMP_CRONTAB"

# Create necessary files with proper permissions
echo "📁 Creating/verifying data files..."

touch "$SCRIPT_DIR/tasks.txt"
touch "$SCRIPT_DIR/subscribers.txt"
touch "$SCRIPT_DIR/pending_subscriptions.txt"
touch "$SCRIPT_DIR/cron_log.txt"
touch "$SCRIPT_DIR/email_log.txt"
touch "$SCRIPT_DIR/system_log.txt"
touch "$SCRIPT_DIR/unsubscribe_tokens.txt"

# Initialize empty files if they're empty
if [ ! -s "$SCRIPT_DIR/tasks.txt" ]; then
    echo "[]" > "$SCRIPT_DIR/tasks.txt"
fi

if [ ! -s "$SCRIPT_DIR/subscribers.txt" ]; then
    echo "[]" > "$SCRIPT_DIR/subscribers.txt"
fi

if [ ! -s "$SCRIPT_DIR/pending_subscriptions.txt" ]; then
    echo "{}" > "$SCRIPT_DIR/pending_subscriptions.txt"
fi

if [ ! -s "$SCRIPT_DIR/unsubscribe_tokens.txt" ]; then
    echo "{}" > "$SCRIPT_DIR/unsubscribe_tokens.txt"
fi

# Set proper permissions
chmod 644 "$SCRIPT_DIR"/*.txt
chmod 644 "$SCRIPT_DIR"/*.php

# Create log rotation script
cat > "$SCRIPT_DIR/rotate_logs.sh" << 'EOF'
#!/bin/bash

# Log rotation script for Task Scheduler
# This script rotates logs when they exceed 10MB

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAX_SIZE=10485760  # 10MB in bytes

rotate_log() {
    local log_file="$1"
    local base_name=$(basename "$log_file" .txt)
    
    if [ -f "$log_file" ] && [ $(stat -f%z "$log_file" 2>/dev/null || stat -c%s "$log_file" 2>/dev/null || echo 0) -gt $MAX_SIZE ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Rotating $log_file (size exceeded 10MB)"
        
        # Keep last 5 rotated logs
        for i in {4..1}; do
            if [ -f "${SCRIPT_DIR}/${base_name}.${i}.txt" ]; then
                mv "${SCRIPT_DIR}/${base_name}.${i}.txt" "${SCRIPT_DIR}/${base_name}.$((i+1)).txt"
            fi
        done
        
        # Move current log to .1
        mv "$log_file" "${SCRIPT_DIR}/${base_name}.1.txt"
        
        # Create new empty log
        touch "$log_file"
        chmod 644 "$log_file"
        
        echo "$(date '+%Y-%m-%d %H:%M:%S') - Log rotation completed for $base_name" >> "$log_file"
    fi
}

# Rotate logs if they're too large
rotate_log "$SCRIPT_DIR/cron_log.txt"
rotate_log "$SCRIPT_DIR/email_log.txt"
rotate_log "$SCRIPT_DIR/system_log.txt"

# Clean up old rotated logs (older than 30 days)
find "$SCRIPT_DIR" -name "*.*.txt" -type f -mtime +30 -delete 2>/dev/null || true

EOF

chmod +x "$SCRIPT_DIR/rotate_logs.sh"

echo ""
echo "=== Setup completed successfully! ==="
echo "Files created/verified:"
echo "  - tasks.txt"
echo "  - subscribers.txt" 
echo "  - pending_subscriptions.txt"
echo "  - cron_log.txt"
echo "  - email_log.txt"
echo "  - system_log.txt"
echo "  - unsubscribe_tokens.txt"
echo "  - rotate_logs.sh (log rotation script)"
echo ""
echo "=== PHP Configuration ==="
echo "Detected PHP binary: $PHP_BINARY"
echo "CRON command: $CRON_COMMAND"
echo ""
echo "=== Testing Commands ==="
echo "To test the cron job manually:"
echo "  $PHP_BINARY $CRON_PHP_PATH"
echo ""
echo "To rotate logs manually:"
echo "  $SCRIPT_DIR/rotate_logs.sh"
echo ""
echo "To view logs:"
echo "  cat $SCRIPT_DIR/cron_log.txt"
echo "  cat $SCRIPT_DIR/email_log.txt"
echo "  cat $SCRIPT_DIR/system_log.txt"
echo ""
echo "To check current crontab:"
echo "  crontab -l"
echo ""
echo "=== Log Management ==="
echo "📋 Log files will automatically rotate when they exceed 10MB"
echo "📋 Old rotated logs are kept for 30 days then automatically deleted"
echo "📋 Run rotate_logs.sh manually if needed"
echo ""
echo "=== Next Steps ==="
echo "1. Test the application by visiting index.php"
echo "2. Add some tasks"
echo "3. Subscribe to email notifications"
echo "4. Run the CRON job manually to test email functionality"
echo "5. The CRON job will automatically run every hour"
echo "6. Monitor log files for any issues"