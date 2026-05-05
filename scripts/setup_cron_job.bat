@echo off
REM Setup Cron Job for Order Status Updates
REM This script helps set up automated order status updates on Windows

echo Setting up automated order status updates...
echo.

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP is not found in PATH. Please ensure PHP is installed and added to PATH.
    echo You can download PHP from: https://windows.php.net/download/
    pause
    exit /b 1
)

echo PHP found. Setting up scheduled task...

REM Get the current directory
set "SCRIPT_DIR=%~dp0"
set "PHP_SCRIPT=%SCRIPT_DIR%update_order_status.php"

REM Create the scheduled task
schtasks /create /tn "Store_Order_Status_Update" /tr "php \"%PHP_SCRIPT%\" >> \"%SCRIPT_DIR%order_update_log.txt\" 2>&1" /sc daily /st 02:00 /ru System /rl highest /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Scheduled task created successfully!
    echo The order status update will run daily at 2:00 AM.
    echo.
    echo Task Details:
    echo - Name: Store_Order_Status_Update
    echo - Schedule: Daily at 2:00 AM
    echo - Script: %PHP_SCRIPT%
    echo - Log file: %SCRIPT_DIR%order_update_log.txt
    echo.
    echo You can modify this task using:
    echo schtasks /query /tn "Store_Order_Status_Update"
    echo schtasks /change /tn "Store_Order_Status_Update" /st HH:MM
    echo schtasks /delete /tn "Store_Order_Status_Update"
) else (
    echo.
    echo ERROR: Failed to create scheduled task.
    echo Please run this script as Administrator or check permissions.
)

echo.
pause
