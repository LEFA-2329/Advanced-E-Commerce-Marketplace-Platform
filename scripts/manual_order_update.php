<?php
/**
 * Manual Order Status Update
 * Web interface to manually trigger order status updates
 * Useful for testing or manual execution
 */

session_start();
require_once '../db_connection.php';

// Redirect if not logged in as admin/owner/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Owner', 'Manager'])) {
    header('Location: ../unified_login.php');
    exit;
}

$message = '';
$updatedCount = 0;

if (isset($_POST['update_orders'])) {
    // Include the update script
    ob_start();
    include 'update_order_status.php';
    $output = ob_get_clean();

    $message = "Order status update completed. Check the output below for details.";
    $scriptOutput = $output;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Order Status Update - Store System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 20px auto;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .output {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manual Order Status Update</h1>

        <div class="info">
            <h3>ℹ️ Information</h3>
            <p>This tool automatically updates orders to "delivered" status after 3-5 business days from shipping.</p>
            <ul>
                <li>Orders in "shipped" status are checked daily</li>
                <li>Delivery occurs after 3-5 business days (Monday-Friday)</li>
                <li>Weekends and holidays are excluded from the count</li>
                <li>Customers receive notifications when orders are delivered</li>
            </ul>
        </div>

        <?php if ($message): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <button type="submit" name="update_orders" class="btn">Update Order Statuses Now</button>
        </form>

        <?php if (isset($scriptOutput)): ?>
            <h3>Script Output:</h3>
            <div class="output">
                <?php echo htmlspecialchars($scriptOutput); ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../index.php" style="color: #007bff; text-decoration: none;">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
