<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$customer_id = $_SESSION['user_id'];

// Fetch unread notifications for the customer
$stmt = $pdo->prepare("
    SELECT notification_id, message, created_at 
    FROM notifications 
    WHERE customer_id = ? AND is_read = false 
    ORDER BY created_at DESC
");
$stmt->execute([$customer_id]);
$notifications = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode(['notifications' => $notifications]);
?>
