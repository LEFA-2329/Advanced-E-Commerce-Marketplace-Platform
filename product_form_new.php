<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header("Location: unified_login.php");
    exit;
}

$username = $_SESSION['username'];

$edit_mode = false;
$product = [
    'name' => '',
    'description' => '',
    'price' => '',
    'stock_quantity' => '',
    'category' => '',
    'image_url' => '',
    'model_3d_url' => ''
];

$errors = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_mode = true;
    $product_id = intval($_GET['edit']);
    $owner_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND owner_id = ?");
    $stmt->execute([$product_id, $owner_id]);
    $product = $stmt->fetch();
    if (!$product) {
        header("Location: product_management.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $category = trim($_POST['category']);
    $image_url = trim($_POST['image_url']);
    $model_3d_url = trim($_POST['model_3d_url']);
    $owner_id = $_SESSION['user_id'];

    if (empty($name)) {
        $errors[] = "Product name is required.";
    }
    if (!is_numeric($price) || $price < 0) {
        $errors[] = "Price must be a non-negative number.";
    }
    if (!is_numeric($stock_quantity) || $stock_quantity < 0) {
        $errors[] = "Stock quantity must be a non-negative integer.";
    }

    if (empty($errors)) {
        if ($edit_mode) {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock_quantity = ?, category = ?, image_url = ?, model_3d_url = ?, updated_at = NOW() WHERE product_id = ? AND owner_id = ?");
            $stmt->execute([$name, $description, $price, $stock_quantity, $category, $image_url, $model_3d_url, $product_id, $owner_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category, image_url, model_3d_url, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $stock_quantity, $category, $image_url, $model_3d_url, $owner_id]);
        }
        header("Location: product_management.php");
        exit;
    } else {
        $product = [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'stock_quantity' => $stock_quantity,
            'category' => $category,
            'image_url' => $image_url,
            'model_3d_url' => $model_3d_url
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $edit_mode ? 'Edit' : 'Add' ?> Product - Store System</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
    <div class="sidebar">
        <div class="logo">Store System</div>
        <nav>
            <a href="owner_dashboard.php">Dashboard</a>
            <a href="product_management.php" class="active">Products</a>
            <a href="promotions_management.php">Promotions</a>
            <a href="analytics.php">Analytics</a>
            <a href="compliance_reports.php">Compliance</a>
            <a href="financial_analytics.php">Financials</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="container py-5" style="max-width: 600px;">
            <h2><?= $edit_mode ? 'Edit' : 'Add' ?> Product</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $edit_mode ? 'product_form.php?edit=' . $product_id : 'product_form.php' ?>" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Product Name *</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>" />
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($product['description']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="price" class="form-label">Price (ZAR) *</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required value="<?= htmlspecialchars($product['price']) ?>" />
                </div>
                <div class="mb-3">
                    <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                    <input type="number" min="0" class="form-control" id="stock_quantity" name="stock_quantity" required value="<?= htmlspecialchars($product['stock_quantity']) ?>" />
                </div>
                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <input type="text" class="form-control" id="category" name="category" value="<?= htmlspecialchars($product['category']) ?>" />
                </div>
                <div class="mb-3">
                    <label for="model_3d_url" class="form-label">3D Model URL (optional, for Furniture category)</label>
                    <input type="url" class="form-control" id="model_3d_url" name="model_3d_url" placeholder="https://example.com/3dmodel" value="<?= htmlspecialchars($product['model_3d_url'] ?? '') ?>" />
                </div>
                <div class="mb-3">
                    <label for="image_url" class="form-label">Image Filename (from images folder)</label>
                    <input type="text" class="form-control" id="image_url" name="image_url" placeholder="e.g. product_image.jpg" value="<?= htmlspecialchars($product['image_url']) ?>" />
                </div>
                <button type="submit" class="btn btn-success"><?= $edit_mode ? 'Update' : 'Add' ?> Product</button>
                <a href="product_management.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
