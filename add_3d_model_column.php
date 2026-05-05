<?php
require_once 'db_connection.php';

try {
    // Add 3D model link column to products table
    $sql = "ALTER TABLE products ADD COLUMN IF NOT EXISTS model_3d_url VARCHAR(500);";
    $pdo->exec($sql);

    echo "Successfully added model_3d_url column to products table.\n";

} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}
?>
