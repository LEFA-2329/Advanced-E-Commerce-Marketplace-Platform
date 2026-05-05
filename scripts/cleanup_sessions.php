<?php
require_once '../db_connection.php';

try {
    // Clean up expired sessions (older than 30 minutes)
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE last_active < NOW() - INTERVAL '30 minutes'");
    $stmt->execute();
    $sessions_deleted = $stmt->rowCount();

    // Clean up expired lockouts
    $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = 0, lockout_until = NULL WHERE lockout_until < NOW()");
    $stmt->execute();
    $lockouts_reset = $stmt->rowCount();

    echo "Cleanup completed: $sessions_deleted expired sessions deleted, $lockouts_reset lockouts reset.\n";
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
?>
