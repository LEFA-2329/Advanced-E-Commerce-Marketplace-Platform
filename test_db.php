<?php
require_once 'db_connection.php';

try {
    // Test the database connection
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    
    echo "<h2>Database Connection Successful!</h2>";
    echo "<p>PostgreSQL Version: " . htmlspecialchars($version) . "</p>";
    
    // Test if tables exist
    $tables = ['users', 'products', 'customers', 'orders'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')");
        $exists = $stmt->fetchColumn() ? 'Yes' : 'No';
        echo "<p>Table '$table' exists: $exists</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2>Database Connection Failed</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check your database credentials in db_connection.php</p>";
}
?>
