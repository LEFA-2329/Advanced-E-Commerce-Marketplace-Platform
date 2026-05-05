<?php
require_once 'db_connection.php';

try {
    // Create login_attempts table
    $sql = "
    CREATE TABLE IF NOT EXISTS login_attempts (
        attempt_id SERIAL PRIMARY KEY,
        user_email VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) NOT NULL, -- 'customer', 'manager', 'owner'
        attempt_count INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        lockout_until TIMESTAMP NULL,
        UNIQUE (user_email, user_type)
    );
    ";
    $pdo->exec($sql);
    echo "login_attempts table created successfully.\n";

    // Check if last_active column exists in user_sessions, if not add it
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='user_sessions' AND column_name='last_active'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE user_sessions ADD COLUMN last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added last_active column to user_sessions.\n";
    } else {
        echo "last_active column already exists in user_sessions.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
