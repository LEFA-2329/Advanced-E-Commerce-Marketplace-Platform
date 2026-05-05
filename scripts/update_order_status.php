<?php
/**
 * Order Status Update Script
 * Automatically updates orders to "delivered" status after 3-5 business days
 * This script should be run daily via cron job
 */

require_once '../db_connection.php';

function isBusinessDay($date) {
    $dayOfWeek = date('N', strtotime($date)); // 1 = Monday, 7 = Sunday
    return $dayOfWeek >= 1 && $dayOfWeek <= 5; // Monday to Friday
}

function addBusinessDays($startDate, $businessDaysToAdd) {
    $currentDate = strtotime($startDate);
    $addedDays = 0;

    while ($addedDays < $businessDaysToAdd) {
        $currentDate = strtotime('+1 day', $currentDate);
        if (isBusinessDay(date('Y-m-d', $currentDate))) {
            $addedDays++;
        }
    }

    return date('Y-m-d', $currentDate);
}

function getRandomBusinessDaysDelay() {
    // Return random number between 3-5 business days
    return rand(3, 5);
}

try {
    echo "Starting order status update process...\n";

    // Get all orders that are shipped but not yet delivered
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.order_date, o.tracking_updated_at, o.customer_id, o.total_amount
        FROM orders o
        WHERE o.order_status = 'shipped'
        AND o.tracking_status != 'delivered'
        ORDER BY o.tracking_updated_at ASC
    ");

    $stmt->execute();
    $shippedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($shippedOrders) . " shipped orders to process.\n";

    $updatedCount = 0;

    foreach ($shippedOrders as $order) {
        $orderId = $order['order_id'];
        $shippedDate = $order['tracking_updated_at']; // When order was marked as shipped
        $customerId = $order['customer_id'];
        $orderAmount = $order['total_amount'];

        // Calculate delivery date (3-5 business days after shipping)
        $businessDaysDelay = getRandomBusinessDaysDelay();
        $deliveryDate = addBusinessDays($shippedDate, $businessDaysDelay);
        $currentDate = date('Y-m-d');

        echo "Order #$orderId: Shipped on $shippedDate, Delivery date: $deliveryDate, Current date: $currentDate\n";

        // Check if current date is on or after the calculated delivery date
        if ($currentDate >= $deliveryDate) {
            // Update order status to delivered
            $updateStmt = $pdo->prepare("
                UPDATE orders
                SET order_status = 'delivered',
                    tracking_status = 'delivered',
                    tracking_updated_at = CURRENT_TIMESTAMP
                WHERE order_id = ?
            ");
            $updateStmt->execute([$orderId]);

            // Add tracking history entry
            $historyStmt = $pdo->prepare("
                INSERT INTO tracking_history (order_id, status, description, location, updated_by, created_at)
                VALUES (?, 'delivered', 'Order has been successfully delivered to the customer', 'Customer Address', 'System', CURRENT_TIMESTAMP)
            ");
            $historyStmt->execute([$orderId]);

            // Create notification for customer
            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications (customer_id, message, is_read, created_at)
                VALUES (?, ?, FALSE, CURRENT_TIMESTAMP)
            ");
            $message = "Great news! Your order #$orderId for R" . number_format($orderAmount, 2) . " has been delivered successfully.";
            $notificationStmt->execute([$customerId, $message]);

            echo "✓ Order #$orderId marked as delivered\n";
            $updatedCount++;
        } else {
            echo "✗ Order #$orderId not yet ready for delivery (will be delivered on $deliveryDate)\n";
        }
    }

    echo "\nProcess completed. Updated $updatedCount orders to delivered status.\n";

    // Log the script execution
    $logStmt = $pdo->prepare("
        INSERT INTO tracking_history (order_id, status, description, location, updated_by, created_at)
        VALUES (NULL, 'system_log', 'Order status update script executed. Updated $updatedCount orders.', 'System', 'Automated Script', CURRENT_TIMESTAMP)
    ");
    $logStmt->execute();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";

    // Log the error
    try {
        $errorStmt = $pdo->prepare("
            INSERT INTO tracking_history (order_id, status, description, location, updated_by, created_at)
            VALUES (NULL, 'system_error', 'Order status update script error: " . addslashes($e->getMessage()) . "', 'System', 'Automated Script', CURRENT_TIMESTAMP)
        ");
        $errorStmt->execute();
    } catch (Exception $logError) {
        echo "Failed to log error: " . $logError->getMessage() . "\n";
    }
}

echo "Script execution finished.\n";
?>
