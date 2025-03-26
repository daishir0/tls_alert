#!/bin/bash
# TLS Certificate Checker Script
# This script runs the TLS certificate checker and can be used in cron jobs

# Change to the script directory
cd "$(dirname "$0")"

# Run the PHP script
php index.php > /dev/null 2>&1

# Check if the script executed successfully
if [ $? -eq 0 ]; then
  echo "Certificate check completed successfully."
  exit 0
else
  echo "Error: Certificate check failed."
  exit 1
fi