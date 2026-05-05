<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header('Location: ../unified_login.php');
    exit;
}

// Fetch archived ordered products grouped by customer and order
$stmt = $pdo->prepare("
    SELECT customer_username, order_id, order_date, product_id, product_name, price, stock_quantity, approved, deleted, archived_at
    FROM ordered_products_history
    ORDER BY archived_at DESC, customer_username, order_id
");
$stmt->execute();
$rows = $stmt->fetchAll();

// Group data by customer and order
$grouped = [];
foreach ($rows as $row) {
    $customer = $row['customer_username'];
    $order_id = $row['order_id'];
    if (!isset($grouped[$customer])) {
        $grouped[$customer] = [];
    }
    if (!isset($grouped[$customer][$order_id])) {
        $grouped[$customer][$order_id] = [
            'order_date' => $row['order_date'],
            'archived_at' => $row['archived_at'],
            'products' => []
        ];
    }
    $grouped[$customer][$order_id]['products'][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Ordered Products History - Manager Dashboard</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
     <link rel="stylesheet" href="manager_styles.css" />
    <style>
        body {
            padding-top: 70px;
            font-family: 'Poppins', sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            color: purple;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="manager_dashboard.php">Odered products History</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
             <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="manager_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_product_management.php"><i class="fa-solid fa-bag-shopping"></i> Product Management</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_promotions_management.php"><i class="fa-solid fa-percent"></i> Promotions</a></li>
                <li class="nav-item"><a class="nav-link active" href="ordered_products_history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
   
    <?php if (empty($grouped)): ?>
        <p>No archived ordered products found.</p>
    <?php else: ?>
        <?php foreach ($grouped as $customer => $orders): ?>
            <h3>Customer: <?= htmlspecialchars($customer) ?></h3>
            <?php foreach ($orders as $order_id => $order): ?>
                <h5>Order ID: <?= htmlspecialchars($order_id) ?> (Order Date: <?= htmlspecialchars($order['order_date']) ?>, Archived At: <?= htmlspecialchars($order['archived_at']) ?>)</h5>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Price (R)</th>
                            <th>Stock Quantity</th>
                            <th>Approved</th>
                            <th>Deleted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['products'] as $product): ?>
                            <tr>
                            <td>
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="../images/<?= htmlspecialchars(basename($product['image_url'])) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" style="max-width: 80px; max-height: 80px; object-fit: contain;" />
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                            <td><?= number_format($product['price'], 2) ?></td>
                            <td><?= intval($product['stock_quantity']) ?></td>
                            <td><?= $product['approved'] ? 'Yes' : 'No' ?></td>
                            <td><?= $product['deleted'] ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
