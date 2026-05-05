<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header('Location: ../unified_login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Product Management - Manager Dashboard</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="manager_styles.css" />
    <style>
        .container{
            margin-top:80px;
            margin-bottom:20px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="manager_dashboard.php">Products Management</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
             <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link " href="manager_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="manager_product_management.php"><i class="fa-solid fa-bag-shopping"></i> Product Management</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_promotions_management.php"><i class="fa-solid fa-percent"></i> Promotions</a></li>
                <li class="nav-item"><a class="nav-link" href="ordered_products_history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php
// Handle product approval toggle, delete, and updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_approval'])) {
        $product_id = intval($_POST['product_id']);
        // Fetch current approval status and stock
        $stmt = $pdo->prepare("SELECT approved, stock_quantity FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        if ($product) {
            // Ensure approved is boolean
            $current_approved = filter_var($product['approved'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($current_approved === null) {
                $current_approved = false;
            }
            // Only approve if stock_quantity > 0
            $new_approved = ($product['stock_quantity'] > 0) ? (!$current_approved) : false;
            $new_approved = $new_approved ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE products SET approved = ? WHERE product_id = ?");
            $stmt->execute([$new_approved, $product_id]);

            // Add customer feedback notification for all customers who ordered this product
            $message = $new_approved ? "Product ID $product_id has been approved and is now available in the store." : "Product ID $product_id has been unapproved and is currently not available.";
            
            // Update order status from 'pending' to 'approved' for orders containing this product
            if ($new_approved) {
                // Generate tracking number and update order status
                $update_order_stmt = $pdo->prepare("
                    UPDATE orders
                    SET order_status = 'approved',
                        tracking_number = 'TRK' || LPAD(order_id::text, 8, '0'),
                        tracking_status = 'processing',
                        tracking_updated_at = CURRENT_TIMESTAMP
                    WHERE order_id IN (
                        SELECT DISTINCT o.order_id
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE oi.product_id = ? AND o.order_status = 'pending'
                    )
                ");
                $update_order_stmt->execute([$product_id]);
                
                // Add tracking history entries
                $tracking_history_stmt = $pdo->prepare("
                    INSERT INTO tracking_history (order_id, status, description, updated_by)
                    SELECT order_id, 'processing', 'Order approved and processing started', 'System'
                    FROM orders 
                    WHERE order_id IN (
                        SELECT DISTinct o.order_id
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE oi.product_id = ? AND o.order_status = 'approved'
                    )
                ");
                $tracking_history_stmt->execute([$product_id]);

                // Move approved product and related order info to history and delete from products
                $history_insert_stmt = $pdo->prepare("
                    INSERT INTO ordered_products_history (product_id, product_name, price, stock_quantity, approved, customer_username, order_id, order_date, deleted, archived_at)
                    SELECT p.product_id, p.name, p.price, p.stock_quantity, p.approved, c.username, o.order_id, o.order_date, FALSE, NOW()
                    FROM products p
                    JOIN order_items oi ON p.product_id = oi.product_id
                    JOIN orders o ON oi.order_id = o.order_id
                    JOIN customers c ON o.customer_id = c.customer_id
                    WHERE p.product_id = ?
                ");
                $history_insert_stmt->execute([$product_id]);

                $archive_stmt = $pdo->prepare("UPDATE products SET archived = TRUE WHERE product_id = ?");
                $archive_stmt->execute([$product_id]);
            }
            
            $stmt = $pdo->prepare("SELECT DISTINCT o.customer_id FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.product_id = ?");
            $stmt->execute([$product_id]);
            $customers = $stmt->fetchAll();
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (customer_id, message) VALUES (?, ?)");
            foreach ($customers as $customer) {
                $notif_stmt->execute([$customer['customer_id'], $message]);
            }
        }
    } elseif (isset($_POST['update_stock'])) {
        $product_id = intval($_POST['product_id']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE product_id = ?");
        $stmt->execute([$stock_quantity, $product_id]);
        
        // If stock is updated to 0, automatically unapprove the product
        if ($stock_quantity == 0) {
            $stmt = $pdo->prepare("UPDATE products SET approved = FALSE WHERE product_id = ?");
            $stmt->execute([$product_id]);
        }
    }
    // Redirect to avoid form resubmission
    header("Location: manager_product_management.php");
    exit;
}

// Fetch all products in the system
$stmt = $pdo->prepare("
    SELECT p.*
    FROM products p
    ORDER BY p.created_at DESC
");
$stmt->execute();
$products = $stmt->fetchAll();
?>

<div class="container">
    <h1>Product Management</h1>

    <div class="row">
        <?php foreach ($products as $product): ?>
            <div class="col-md-2 mb-4">
                <div class="card h-100">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="../images/<?= htmlspecialchars(basename($product['image_url'])) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>" style="height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                            <span class="text-muted">No Image</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                        <p class="card-text mb-1">
                            <strong>Price:</strong> R <?= number_format($product['price'], 2) ?>
                        </p>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>" />
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Stock:</span>
                                <input type="number" name="stock_quantity" class="form-control" value="<?= intval($product['stock_quantity']) ?>" min="0" />
                                <button type="submit" name="update_stock" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                        <p class="card-text mb-2">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?= $product['approved'] ? 'success' : 'warning' ?>">
                                <?= $product['approved'] ? 'Approved' : 'Pending' ?>
                            </span>
                            <?php if ($product['stock_quantity'] <= 10): ?>
                                <span class="badge bg-danger ms-1 low-stock-badge">Low Stock!</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="card-footer">
                        <form method="POST" class="d-grid gap-2">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>" />
                            <button type="submit" name="toggle_approval" class="btn btn-sm btn-<?= ($product['approved'] ? 'warning' : 'success') ?>">
                                <?= $product['approved'] ? 'Unapprove' : 'Approve' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <hr />

    <!-- <h2>View Products Ordered by Customer</h2>
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 400px;">
            <input type="text" name="customer_username" class="form-control" placeholder="Enter customer username" value="<?= htmlspecialchars($_GET['customer_username'] ?? '') ?>" />
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form> -->

    <?php
    if (!empty($_GET['customer_username'])) {
        $customer_username = trim($_GET['customer_username']);
        // Fetch customer ID and name
        $stmt = $pdo->prepare("SELECT customer_id, username FROM customers WHERE username = ?");
        $stmt->execute([$customer_username]);
        $customer = $stmt->fetch();

        if ($customer) {
            // Handle approve all products ordered by this customer
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_all'])) {
                $stmt = $pdo->prepare("
                    UPDATE products
                    SET approved = TRUE
                    WHERE product_id IN (
                        SELECT DISTINCT oi.product_id
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE o.customer_id = ?
                    )
                ");
                $stmt->execute([$customer['customer_id']]);

            // Update order statuses and generate tracking numbers
            $update_order_stmt = $pdo->prepare("
                UPDATE orders
                SET order_status = 'approved',
                    tracking_number = 'TRK' || LPAD(order_id::text, 8, '0'),
                    tracking_status = 'processing',
                    tracking_updated_at = CURRENT_TIMESTAMP
                WHERE customer_id = ? AND order_status = 'pending'
            ");
            $update_order_stmt->execute([$customer['customer_id']]);
            
            // Add tracking history entries
            $tracking_history_stmt = $pdo->prepare("
                INSERT INTO tracking_history (order_id, status, description, updated_by)
                SELECT order_id, 'processing', 'Order approved and processing started', 'System'
                FROM orders 
                WHERE customer_id = ? AND order_status = 'approved'
            ");
            $tracking_history_stmt->execute([$customer['customer_id']]);

                // Insert notifications for the customer
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (customer_id, message) VALUES (?, ?)");
                $message = "All your ordered products have been approved and are now available in the store.";
                $notif_stmt->execute([$customer['customer_id'], $message]);

                // Redirect to avoid form resubmission
                header("Location: manager_product_management.php?customer_username=" . urlencode($customer_username));
                exit;
            }

            // Fetch orders and approved products grouped by order for this customer
            $stmt = $pdo->prepare("
                SELECT o.order_id, o.order_date, p.product_id, p.name, p.price
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE o.customer_id = ? AND p.approved = TRUE
                ORDER BY o.order_date DESC, o.order_id, p.name
            ");
            $stmt->execute([$customer['customer_id']]);
            $rows = $stmt->fetchAll();

            // Group products by order
            $orders = [];
            foreach ($rows as $row) {
                $order_id = $row['order_id'];
                if (!isset($orders[$order_id])) {
                    $orders[$order_id] = [
                        'order_date' => $row['order_date'],
                        'products' => []
                    ];
                }
                $orders[$order_id]['products'][] = [
                    'product_id' => $row['product_id'],
                    'name' => $row['name'],
                    'price' => $row['price']
                ];
            }
            ?>
            <h3>Approved Products Ordered by <?= htmlspecialchars($customer_username) ?></h3>
            <form method="POST" style="margin-bottom: 1rem;">
                <button type="submit" name="approve_all" class="btn btn-success">Approve All Products Ordered by Customer</button>
            </form>
            <?php if (count($orders) === 0): ?>
                <p>No approved products found for this customer.</p>
            <?php else: ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Order Date</th>
                            <th>Products</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order_id => $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order_id) ?></td>
                                <td><?= htmlspecialchars($order['order_date']) ?></td>
                                <td>
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product ID</th>
                                                <th>Name</th>
                                                <th>Price (R)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($order['products'] as $product): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($product['product_id']) ?></td>
                                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                                    <td><?= number_format($product['price'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php
            endif;
        } else {
            echo '<p class="text-danger">Customer not found.</p>';
        }
    }
    ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
