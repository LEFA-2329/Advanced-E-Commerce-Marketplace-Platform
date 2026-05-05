<?php
require_once '../db_connection.php';

// Archive products and customer orders older than 24 hours

// Calculate cutoff datetime
$cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));

// Select products approved or deleted older than 24 hours
// For deleted products, we need to track them before deletion, so this script assumes soft delete or logs elsewhere
// Here, we archive approved products older than 24 hours

// Fetch approved products older than 24 hours
$stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.price, p.stock_quantity, p.approved,
           c.username AS customer_username, o.order_id, o.order_date
    FROM products p
    JOIN order_items oi ON p.product_id = oi.product_id
    JOIN orders o ON oi.order_id = o.order_id
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE p.approved = TRUE AND p.created_at <= ?
");
$stmt->execute([$cutoff]);
$rows = $stmt->fetchAll();

if (count($rows) > 0) {
    $insert_stmt = $pdo->prepare("
        INSERT INTO ordered_products_history
        (product_id, product_name, price, stock_quantity, approved, customer_username, order_id, order_date, deleted, archived_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, FALSE, NOW())
    ");

    foreach ($rows as $row) {
        $insert_stmt->execute([
            $row['product_id'],
            $row['name'],
            $row['price'],
            $row['stock_quantity'],
            $row['approved'],
            $row['customer_username'],
            $row['order_id'],
            $row['order_date']
        ]);
    }

    // Delete archived products from products table
    $product_ids = array_unique(array_column($rows, 'product_id'));
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $del_stmt = $pdo->prepare("DELETE FROM products WHERE product_id IN ($placeholders)");
    $del_stmt->execute($product_ids);
}
?>
