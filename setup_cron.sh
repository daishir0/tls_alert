#!/bin/bash
# Setup cron job for TLS certificate checker
# This script adds a cron job to run the certificate checker daily at 16:30

# Get the absolute path to the check_certificates.sh script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHECKER_SCRIPT="$SCRIPT_DIR/check_certificates.sh"

# Make sure the checker script is executable
chmod +x "$CHECKER_SCRIPT"

# Create a temporary file with the new cron job
TEMP_CRON=$(mktemp)
crontab -l > "$TEMP_CRON" 2>/dev/null || echo "# TLS Alert cron jobs" > "$TEMP_CRON"

# Check if the cron job already exists
if ! grep -q "$CHECKER_SCRIPT" "$TEMP_CRON"; then
    # Add the new cron job to run daily at 16:30
    echo "30 16 * * * $CHECKER_SCRIPT" >> "$TEMP_CRON"
    
    # Install the new crontab
    crontab "$TEMP_CRON"
    echo "Cron job installed successfully. TLS certificate checker will run daily at 16:30."
else
    echo "Cron job already exists."
fi

# Clean up
rm "$TEMP_CRON"

echo "To verify the cron job, run: crontab -l"