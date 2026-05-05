
<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header('Location: ../unified_login.php');
    exit;
}

$errors = [];
$success = '';

// Handle add promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_promotion'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $promotion_type = trim($_POST['promotion_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $discount_percent = floatval($_POST['discount_percent'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_active = isset($_POST['is_active']) ? true : false;

    if ($product_id <= 0) {
        $errors[] = "Please select a valid product.";
    }
    if ($promotion_type === '') {
        $errors[] = "Promotion type is required.";
    }
    if ($discount_percent < 0 || $discount_percent > 100) {
        $errors[] = "Discount percent must be between 0 and 100.";
    }
    if ($start_date === '' || $end_date === '') {
        $errors[] = "Start and end dates are required.";
    }
    if (strtotime($start_date) > strtotime($end_date)) {
        $errors[] = "Start date cannot be after end date.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO promotions (product_id, promotion_type, description, discount_percent, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $promotion_type, $description, $discount_percent, $start_date, $end_date, $is_active]);
        $success = "Promotion added successfully.";
    }
}

// Fetch promotions with product names
$stmt = $pdo->prepare("
    SELECT pr.*, p.name AS product_name
    FROM promotions pr
    LEFT JOIN products p ON pr.product_id = p.product_id
    ORDER BY pr.start_date DESC
");
$stmt->execute();
$promotions = $stmt->fetchAll();

 // Fetch products for selection
$stmt = $pdo->prepare("SELECT product_id, name FROM products WHERE approved = TRUE AND archived = FALSE ORDER BY name ASC");
$stmt->execute();
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Promotions Management - Manager Dashboard</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="manager_styles.css" />
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="manager_dashboard.php">Promotion management</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link " href="manager_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_product_management.php"><i class="fa-solid fa-bag-shopping"></i> Product Management</a></li>
                <li class="nav-item"><a class="nav-link active" href="manager_promotions_management.php"><i class="fa-solid fa-percent"></i> Promotions</a></li>
                <li class="nav-item"><a class="nav-link" href="ordered_products_history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container" style="padding-top: 80px;">
   

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Add New Promotion</h2>
    <form method="POST" class="mb-4">
            <div class="mb-3">
                <label for="product_id" class="form-label">Product</label>
                <select id="product_id" name="product_id" class="form-select" required>
                    <option value="">-- Select a product --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['product_id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <div class="mb-3">
            <label for="promotion_type" class="form-label">Promotion Type</label>
            <input type="text" id="promotion_type" name="promotion_type" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label for="discount_percent" class="form-label">Discount Percent</label>
            <input type="number" id="discount_percent" name="discount_percent" class="form-control" min="0" max="100" step="0.01" required />
        </div>
        <div class="mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" id="start_date" name="start_date" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" id="end_date" name="end_date" class="form-control" required />
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" id="is_active" name="is_active" class="form-check-input" checked />
            <label for="is_active" class="form-check-label">Active</label>
        </div>
        <button type="submit" name="add_promotion" class="btn btn-primary">Add Promotion</button>
    </form>

    <h2>Existing Promotions</h2>
    <?php if (count($promotions) === 0): ?>
        <p>No promotions found.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Promotion ID</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Discount (%)</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Active</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                        <td><?= htmlspecialchars($promo['promotion_id']) ?></td>
                        <td><?= htmlspecialchars($promo['product_name']) ?></td>
                        <td><?= htmlspecialchars($promo['promotion_type']) ?></td>
                        <td><?= nl2br(htmlspecialchars($promo['description'])) ?></td>
                        <td><?= htmlspecialchars($promo['discount_percent']) ?></td>
                        <td><?= htmlspecialchars($promo['start_date']) ?></td>
                        <td><?= htmlspecialchars($promo['end_date']) ?></td>
                        <td><?= $promo['is_active'] ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function filterProducts() {
    const input = document.getElementById('productSearch');
    const filter = input.value.toLowerCase();
    const options = document.querySelectorAll('.product-option');
    
    if (filter === '') {
        // Show all options when search is empty
        options.forEach(option => {
            option.style.display = '';
        });
    } else {
        // Filter options based on search
        options.forEach(option => {
            const text = option.textContent || option.innerText;
            if (text.toLowerCase().includes(filter)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    }
}

// Initialize - show all products by default
document.addEventListener('DOMContentLoaded', function() {
    const options = document.querySelectorAll('.product-option');
    options.forEach(option => {
        option.style.display = '';
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
