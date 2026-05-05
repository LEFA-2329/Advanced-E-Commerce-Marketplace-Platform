<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header("Location: unified_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$errors = [];
$success = '';

// Handle accept or decline promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept_promotion'])) {
        $promotion_id = intval($_POST['promotion_id']);
        // Fetch promotion and product info
        $stmt = $pdo->prepare("SELECT * FROM promotions WHERE promotion_id = ?");
        $stmt->execute([$promotion_id]);
        $promotion = $stmt->fetch();

        if ($promotion) {
            // Update promotion to active
            $update_promo = $pdo->prepare("UPDATE promotions SET is_active = TRUE WHERE promotion_id = ?");
            $update_promo->execute([$promotion_id]);

            // Decrease product price by discount percent
            $stmt = $pdo->prepare("SELECT price FROM products WHERE product_id = ?");
            $stmt->execute([$promotion['product_id']]);
            $product = $stmt->fetch();

            if ($product) {
                $new_price = $product['price'] * (1 - $promotion['discount_percent'] / 100);
                $update_product = $pdo->prepare("UPDATE products SET price = ? WHERE product_id = ?");
                $update_product->execute([$new_price, $promotion['product_id']]);
            }
            $success = "Promotion accepted and product price updated.";
        } else {
            $errors[] = "Promotion not found.";
        }
    } elseif (isset($_POST['decline_promotion'])) {
        $promotion_id = intval($_POST['promotion_id']);
        $update_promo = $pdo->prepare("UPDATE promotions SET is_active = FALSE WHERE promotion_id = ?");
        $update_promo->execute([$promotion_id]);
        $success = "Promotion declined.";
    }
}

// Fetch profile image filename
$stmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$profile_image = $user && $user['profile_image'] ? $user['profile_image'] : 'default_profile.png';

// Fetch promotions for products owned by this owner
$stmt = $pdo->prepare("
    SELECT pr.*, p.name AS product_name
    FROM promotions pr
    JOIN products p ON pr.product_id = p.product_id
    WHERE p.owner_id = ?
    ORDER BY pr.start_date DESC
");
$stmt->execute([$user_id]);
$promotions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Promotions Management - Store System</title>
    <link rel="stylesheet" href="products.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        .main-content h2, .main-content p {
            color:#aaa;
        }
        .main-content table{
            border-collapse: collapse;
            width: 100%;
            border:1px solid #ddd;
            border-bottom:4px solid #aaa;
        }
        .main-content th, .main-content td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .main-content th {
            background-color: #f2f2f2;
            font-style:italic;
        }
        .btn-decline{
            border:none;
            outline:none;
            background:none;
            background-color:darkred;
            color:white;
            border-radius:5px;
            padding:5px 10px;
        }
           .btn-accept{
            border:none;
            outline:none;
            background:none;
            background-color:green;
            color:white;
            border-radius:5px;
            padding:5px 10px;
        }
        .border{
            height:2px;
            width:100%;
            background:linear-gradient(to right, #333, #333, #aaa, #ddd, #ddd, transparent);
            margin-top:10rem;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="user-info" style="padding: 15px; text-align: center; border-bottom: 1px solid #ddd;">
        <img class="profile-image" src="images/<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" /> 
        <div class="profile-name"><?= htmlspecialchars($username) ?><small style="color:rgb(145, 255, 0);font-weight:bold;font-size:2rem;text-shadow:none;margin-left:10px;">.</small></div>
    </div>
    <div class="logo">My Store</div>
    <nav>
        <a href="owner_dashboard.php"><i class="fa-solid fa-house"></i>Home</a>
        <a href="product_management.php"><i class="fas fa-box-open"></i>Products</a>
        <a href="promotions_management.php" class="active" style="background:#00aaa2;color:white;"><i class="fas fa-tags"></i>Promotions</a>
        <a href="analytics.php"><i class="fas fa-chart-pie"></i>Analytics</a>
         <a href="AI_business_intelligence.php"><i class="fa-solid fa-robot"></i>Business Intel</a>
       
        <a href="logout.php" class="logout"><i class="fa-solid fa-power-off"></i></a>
        <a href="settings.php" class="settings"><i class="fa-solid fa-gear"  style="cursor:pointer"></i></a>
    </nav>
</div>

<div class="main-content">
    <h2>Promotions Management</h2>
    <p>Manage pricing and promotional offers for your products.</p>

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

    <?php if (count($promotions) === 0): ?>
        <p>No promotions found.</p>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                   
                    <th>Product</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Discount (%)</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $promo): ?>
                    <tr>
                       
                        <td style="font-style:italic; font-weight:bold;"><?= htmlspecialchars($promo['product_name']) ?></td>
                        <td style="color:green;font-weight:bold"><?= htmlspecialchars($promo['promotion_type']) ?></td>
                        <td><?= nl2br(htmlspecialchars($promo['description'])) ?></td>
                        <td><?= htmlspecialchars($promo['discount_percent']) ?></td>
                        <td><?= htmlspecialchars($promo['start_date']) ?></td>
                        <td><?= htmlspecialchars($promo['end_date']) ?></td>
                        <td><?= $promo['is_active'] ? 'Yes' : 'No' ?></td>
                        <td>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="promotion_id" value="<?= $promo['promotion_id'] ?>" />
                                <button class="btn-accept" type="submit" name="accept_promotion" class="btn btn-success btn-sm" <?= $promo['is_active'] ? 'disabled' : '' ?>>Accept</button>
                            </form>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="promotion_id" value="<?= $promo['promotion_id'] ?>" />
                                <button class="btn-decline" type="submit" name="decline_promotion" class="btn btn-danger btn-sm" <?= !$promo['is_active'] ? 'disabled' : '' ?>>Decline</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

   <div class="border"></div>
</div>
</body>
</html>
</div>
</body>
</html>
