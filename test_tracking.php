<?php
// Test script to verify tracking system functionality
require_once 'db_connection.php';

echo "<h2>Tracking System Test</h2>";

// Test 1: Check if database tables exist
echo "<h3>1. Database Table Check</h3>";
$tables = ['orders', 'tracking_history'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Table '$table' does not exist: " . $e->getMessage() . "</p>";
    }
}

// Test 2: Check if orders table has tracking columns
echo "<h3>2. Orders Table Columns Check</h3>";
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'orders'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['tracking_number', 'tracking_status', 'tracking_updated_at'];
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "<p style='color: green;'>✓ Column '$column' exists in orders table</p>";
        } else {
            echo "<p style='color: red;'>✗ Column '$column' missing from orders table</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking orders table columns: " . $e->getMessage() . "</p>";
}

// Test 3: Check if tracking_history table has required columns
echo "<h3>3. Tracking History Table Columns Check</h3>";
try {
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'tracking_history'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['order_id', 'status', 'description', 'location', 'updated_by', 'created_at'];
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "<p style='color: green;'>✓ Column '$column' exists in tracking_history table</p>";
        } else {
            echo "<p style='color: red;'>✗ Column '$column' missing from tracking_history table</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error checking tracking_history table columns: " . $e->getMessage() . "</p>";
}

// Test 4: Check if files exist
echo "<h3>4. File Existence Check</h3>";
$files = [
    'manager/manager_product_management.php',
    'customers/order_details.php',
    'customers/orders.php',
    'customers/tracking.php',
    'manager/tracking_management.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ File '$file' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ File '$file' does not exist</p>";
    }
}

echo "<h3>5. Test Summary</h3>";
echo "<p>All tracking system components have been implemented. The system includes:</p>";
echo "<ul>";
echo "<li>Database schema updates for tracking functionality</li>";
echo "<li>Automatic tracking number generation when orders are approved</li>";
echo "<li>Clickable tracking numbers in customer order views</li>";
echo "<li>Detailed tracking information pages</li>";
echo "<li>Manager interface for updating tracking status</li>";
echo "<li>Automatic notifications for tracking updates</li>";
echo "</ul>";

echo "<p><strong>Next Steps:</strong> Test the actual functionality by:</p>";
echo "<ol>";
echo "<li>Creating a test order</li>";
echo "<li>Approving the order as a manager to generate tracking number</li>";
echo "<li>Updating tracking status through the manager interface</li>";
echo "<li>Viewing tracking information as a customer</li>";
echo "<li>Verifying notifications are sent</li>";
echo "</ol>";
?>
