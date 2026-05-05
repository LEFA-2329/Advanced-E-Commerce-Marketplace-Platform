#!/bin/bash

# Setup Cron Job for Order Status Updates - Linux/Unix
# This script helps set up automated order status updates

echo "Setting up automated order status updates..."
echo

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not found in PATH. Please ensure PHP is installed."
    echo "You can install PHP using: sudo apt install php-cli (Ubuntu/Debian)"
    echo "Or: sudo yum install php-cli (CentOS/RHEL)"
    exit 1
fi

echo "PHP found. Setting up cron job..."

# Get the absolute path of the script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_SCRIPT="$SCRIPT_DIR/update_order_status.php"
LOG_FILE="$SCRIPT_DIR/order_update_log.txt"

# Create the cron job entry
CRON_JOB="0 2 * * * php \"$PHP_SCRIPT\" >> \"$LOG_FILE\" 2>&1"

# Check if cron job already exists
if crontab -l | grep -q "update_order_status.php"; then
    echo "Cron job already exists. Updating..."
    # Remove existing cron job
    crontab -l | grep -v "update_order_status.php" | crontab -
fi

# Add the new cron job
(crontab -l ; echo "$CRON_JOB") | crontab -

if [ $? -eq 0 ]; then
    echo
    echo "SUCCESS: Cron job created successfully!"
    echo "The order status update will run daily at 2:00 AM."
    echo
    echo "Cron Job Details:"
    echo "- Schedule: Daily at 2:00 AM (0 2 * * *)"
    echo "- Script: $PHP_SCRIPT"
    echo "- Log file: $LOG_FILE"
    echo
    echo "You can view current cron jobs with: crontab -l"
    echo "You can edit cron jobs with: crontab -e"
    echo "You can remove this job with: crontab -l | grep -v update_order_status.php | crontab -"
    echo
    echo "To test the script manually, run:"
    echo "php \"$PHP_SCRIPT\""
else
    echo
    echo "ERROR: Failed to create cron job."
    echo "You may need to run this script with sudo or check permissions."
fi

echo
echo "Setup completed."
