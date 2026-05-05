<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_ids']) || !is_array($input['notification_ids'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid notification IDs']);
    exit;
}

$notification_ids = $input['notification_ids'];
$placeholders = implode(',', array_fill(0, count($notification_ids), '?'));

// Mark notifications as read
$stmt = $pdo->prepare("
    UPDATE notifications 
    SET is_read = true 
    WHERE notification_id IN ($placeholders) AND customer_id = ?
");
$params = array_merge($notification_ids, [$_SESSION['user_id']]);
$stmt->execute($params);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'marked_count' => $stmt->rowCount()]);
?>
