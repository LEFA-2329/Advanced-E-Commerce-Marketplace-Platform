# Order Status Update System

This system automatically updates orders to "delivered" status after 3-5 business days from the shipping date.

## Features

- **Automatic Updates**: Orders are automatically marked as delivered after 3-5 business days
- **Business Days Only**: Only counts Monday-Friday (excludes weekends)
- **Random Delay**: Uses random 3-5 business day delay for realistic delivery times
- **Customer Notifications**: Automatically notifies customers when orders are delivered
- **Tracking History**: Maintains complete tracking history for each order
- **Manual Override**: Web interface for manual execution and testing

## Files

- `update_order_status.php` - Main script that processes order updates
- `manual_order_update.php` - Web interface for manual execution
- `setup_cron_job.bat` - Windows setup script
- `setup_cron_linux.sh` - Linux setup script

## Setup Instructions

### Option 1: Automated Setup (Recommended)

#### Windows
1. Run the setup script as Administrator:
   ```batch
   scripts\setup_cron_job.bat
   ```
2. The script will create a scheduled task that runs daily at 2:00 AM

#### Linux/Unix
1. Make the setup script executable:
   ```bash
   chmod +x scripts/setup_cron_linux.sh
   ```
2. Run the setup script:
   ```bash
   ./scripts/setup_cron_linux.sh
   ```
3. The script will add a cron job that runs daily at 2:00 AM

### Option 2: Manual Cron Setup

#### Linux/Unix
1. Open crontab editor:
   ```bash
   crontab -e
   ```
2. Add this line to run daily at 2:00 AM:
   ```
   0 2 * * * php /path/to/your/store/scripts/update_order_status.php >> /path/to/your/store/scripts/order_update_log.txt 2>&1
   ```

#### Windows Task Scheduler
1. Open Task Scheduler
2. Create a new task with these settings:
   - **Name**: Store_Order_Status_Update
   - **Trigger**: Daily at 2:00 AM
   - **Action**: Start a program
   - **Program**: `php.exe`
   - **Arguments**: `C:\path\to\store\scripts\update_order_status.php`
   - **Start in**: `C:\path\to\store\scripts\`

## Manual Testing

### Web Interface
1. Access the manual update interface:
   ```
   http://your-domain/scripts/manual_order_update.php
   ```
2. Login as an Owner or Manager
3. Click "Update Order Statuses Now"

### Command Line
Run the script directly:
```bash
php scripts/update_order_status.php
```

## How It Works

1. **Daily Execution**: The script runs automatically every day at 2:00 AM
2. **Order Selection**: Finds all orders with status "shipped"
3. **Business Day Calculation**:
   - Calculates 3-5 business days from shipping date
   - Excludes weekends (Saturday, Sunday)
   - Uses random delay for realistic timing
4. **Status Updates**:
   - Updates order status to "delivered"
   - Updates tracking status to "delivered"
   - Sets tracking_updated_at timestamp
5. **Tracking History**: Adds entry to tracking_history table
6. **Notifications**: Creates notification for customer
7. **Logging**: Logs all actions to order_update_log.txt

## Business Logic

- **Business Days**: Monday (1) through Friday (5)
- **Delivery Window**: 3-5 business days after shipping
- **Random Variation**: Uses `rand(3, 5)` for realistic delivery times
- **Order Status Flow**:
  ```
  pending → shipped → delivered
  ```
- **Tracking Status Flow**:
  ```
  order_placed → processing → packaging → shipped → out_for_delivery → delivered
  ```

## Monitoring

### Log Files
- `order_update_log.txt` - Contains execution logs and results
- PHP error logs - Contains any script errors

### Database Tracking
- `tracking_history` table contains all status changes
- `notifications` table contains customer notifications

### Manual Checks
You can monitor order updates by:
1. Checking the log file: `tail -f scripts/order_update_log.txt`
2. Querying the database:
   ```sql
   SELECT order_id, order_status, tracking_status, tracking_updated_at
   FROM orders
   WHERE order_status = 'delivered'
   ORDER BY tracking_updated_at DESC
   LIMIT 10;
   ```

## Troubleshooting

### Common Issues

1. **Script not running**:
   - Check cron/task scheduler setup
   - Verify PHP path is correct
   - Check file permissions

2. **Database connection errors**:
   - Verify database credentials in `db_connection.php`
   - Check database server is running

3. **No orders being updated**:
   - Check that orders exist with "shipped" status
   - Verify shipping dates are in the past
   - Check business day calculations

### Debug Mode
Add this to the top of `update_order_status.php` for debugging:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Customization

### Change Delivery Timeframe
Modify the `getRandomBusinessDaysDelay()` function:
```php
function getRandomBusinessDaysDelay() {
    return rand(2, 7); // Change to 2-7 business days
}
```

### Change Execution Time
Update the cron schedule:
- Every 6 hours: `0 */6 * * *`
- Every hour: `0 * * * *`
- Every 30 minutes: `*/30 * * * *`

### Add Holiday Exclusions
Extend the `isBusinessDay()` function to exclude holidays:
```php
function isBusinessDay($date) {
    $dayOfWeek = date('N', strtotime($date));
    $holidays = ['2024-12-25', '2024-01-01']; // Add your holidays

    return $dayOfWeek >= 1 && $dayOfWeek <= 5 && !in_array($date, $holidays);
}
```

## Security Notes

- The script requires proper file permissions
- Database credentials should be secured
- Consider running the script as a limited user
- Log files should be monitored for sensitive data

## Support

For issues or questions:
1. Check the log files for error messages
2. Verify database connectivity
3. Test with manual execution
4. Check file permissions and PHP configuration
