<?php
session_start();

if (isset($_SESSION['user_id'])) {
    require_once '../db_connection.php';
    $session_id = session_id();
    $user_id = $_SESSION['user_id'];

    // Remove session record from user_sessions table
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$session_id, $user_id]);
}

session_unset();
session_destroy();
header("Location: ../unified_login.php");
exit;
?>
