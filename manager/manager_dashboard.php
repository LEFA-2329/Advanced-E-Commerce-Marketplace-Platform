<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header('Location: ../unified_login.php');
    exit;
}

// Fetch list of customers who have pending orders only with their latest order status
$stmt = $pdo->prepare("
    SELECT c.customer_id, c.username, o_latest.latest_order_status
    FROM customers c
    JOIN (
        SELECT o1.customer_id, o1.order_status AS latest_order_status
        FROM orders o1
        WHERE o1.order_date = (
            SELECT MAX(o2.order_date) FROM orders o2 WHERE o2.customer_id = o1.customer_id
        )
    ) o_latest ON c.customer_id = o_latest.customer_id
    WHERE o_latest.latest_order_status = 'pending'
    ORDER BY c.username ASC
");
$stmt->execute();
$customers = $stmt->fetchAll();

// Fetch inventory summary
$stmt = $pdo->prepare("SELECT product_id, name, stock_quantity FROM products ORDER BY stock_quantity ASC LIMIT 10");
$stmt->execute();
$inventory = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manager Dashboard - Store System</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="manager_styles.css" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Manager Dashboard</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="manager_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_product_management.php"><i class="fa-solid fa-bag-shopping"></i> Product Management</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_promotions_management.php"><i class="fa-solid fa-percent"></i> Promotions</a></li>
                <li class="nav-item"><a class="nav-link" href="ordered_products_history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <!-- <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h1> -->

    <section class="mt-4">
        <h2>Customers Who Placed Orders</h2>
        <?php if (count($customers) === 0): ?>
            <p>No customers with orders found.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                      
                        <th>Username</th>
                        <th>Status</th>
                        <th>View Orders</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            
                            <td><?= htmlspecialchars($customer['username']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($customer['latest_order_status'])) ?></td>
                            <td><a href="manager_product_management.php?customer_username=<?= urlencode($customer['username']) ?>" class="btn btn-primary btn-sm">View Orders</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="mt-5">
        <h2>Inventory Summary (Low Stock)</h2>
        <?php if (count($inventory) === 0): ?>
            <p>No inventory data found.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Name</th>
                        <th>Stock Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $product): ?>
                        <tr class="<?= $product['stock_quantity'] < 5 ? 'low-stock' : '' ?>">
                            <td><?= htmlspecialchars($product['product_id']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= intval($product['stock_quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
