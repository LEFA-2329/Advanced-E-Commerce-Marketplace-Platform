<?php
session_start();
require_once 'db_connection.php';

// Query furniture products
$query = "SELECT p.*, pr.discount_percent, pr.promotion_type, pr.is_active as promotion_active
          FROM products p
          LEFT JOIN promotions pr ON p.product_id = pr.product_id
          AND pr.is_active = true
          AND pr.start_date <= CURRENT_DATE
          AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
          WHERE p.category = 'furniture' AND p.stock_quantity > 0
          ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Customer';

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!$is_logged_in) {
        // Redirect to login with return URL
        $return_url = urlencode($_SERVER['REQUEST_URI']);
        header("Location: unified_login.php?return_url=$return_url");
        exit;
    }

    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    // Add to cart logic (similar to cart.php)
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }

    // Redirect back with success message
    header("Location: " . $_SERVER['REQUEST_URI'] . "&added=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Furniture Collection - Store System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: purple;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --font-color: #333;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 8px rgba(0,0,0,0.1);
            --font-family: 'Poppins', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--background-color);
            color: var(--font-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 0;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            border-radius: 15px;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .product-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--font-color);
        }

        .product-description {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 15px;
            line-height: 1.5;
            flex-grow: 1;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .product-stock {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-add-cart {
            background: var(--primary-color);
            color: white;
            flex: 1;
        }

        .btn-add-cart:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #f8f9fa;
            color: var(--secondary-color);
            border: 1px solid #dee2e6;
            width: 45px;
            height: 45px;
            justify-content: center;
            padding: 0;
        }

        .btn-view:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .quantity-input {
            width: 60px;
            padding: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }

        .login-prompt {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
            text-align: center;
        }

        .login-prompt a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }

        .login-prompt a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .product-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .product-card {
                margin: 0 auto;
                max-width: 400px;
            }

            .product-actions {
                flex-direction: column;
            }

            .btn-add-cart {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.8rem;
            }

            .product-info {
                padding: 15px;
            }

            .product-title {
                font-size: 1.1rem;
            }

            .product-price {
                font-size: 1.3rem;
            }
        }

        /* Sale/Promotion styling */
        .product-card.sale-item {
            position: relative;
            border: 2px solid #ff6b35;
        }

        .product-card.sale-item::after {
            content: 'SALE';
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #ff6b35, #ff4500);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.8rem;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-couch"></i> Our Furniture Collection</h1>
            <p>Discover premium furniture pieces for your home</p>
        </div>

        <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> Product added to cart successfully!
            </div>
        <?php endif; ?>

        <?php if (!$is_logged_in): ?>
            <div class="login-prompt">
                <i class="fas fa-info-circle"></i>
                <strong>Want to purchase?</strong> <a href="unified_login.php?return_url=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Sign in</a> to add items to your cart and complete your order.
            </div>
        <?php endif; ?>

        <?php if (count($products) > 0): ?>
            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card <?= (!empty($product['discount_percent']) && $product['promotion_active']) ? 'sale-item' : '' ?>">
                        <img src="images/<?= htmlspecialchars(basename($product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" />

                        <div class="product-info">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-description"><?= htmlspecialchars(substr($product['description'], 0, 150)) ?><?= strlen($product['description']) > 150 ? '...' : '' ?></p>

                            <?php if ($product['discount_percent'] > 0 && $product['promotion_active']): ?>
                                <p class="product-price">
                                    <span style="text-decoration: line-through; color: #999; margin-right: 10px; font-size: 1rem;">
                                        R <?= number_format($product['price'], 2) ?>
                                    </span>
                                    <span>R <?= number_format($product['price'] * (1 - $product['discount_percent'] / 100), 2) ?></span>
                                    <span style="color: #ff6b35; font-size: 0.9rem; margin-left: 5px;">
                                        (<?= $product['discount_percent'] ?>% OFF)
                                    </span>
                                </p>
                            <?php else: ?>
                                <p class="product-price">R <?= number_format($product['price'], 2) ?></p>
                            <?php endif; ?>

                            <p class="product-stock">Stock: <?= intval($product['stock_quantity']) ?> available</p>

                            <div class="product-actions">
                                <form method="POST" action="" style="display: flex; gap: 10px; align-items: center; flex: 1;">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>" />
                                    <input type="number" name="quantity" value="1" min="1" max="<?= intval($product['stock_quantity']) ?>" class="quantity-input" />
                                    <button type="submit" name="add_to_cart" class="btn btn-add-cart">
                                        <i class="fas fa-cart-plus"></i>
                                        Add to Cart
                                    </button>
                                </form>
                                <?php if (!empty($product['model_3d_url'])): ?>
                                    <a href="3d_viewer.php?product_id=<?= $product['product_id'] ?>" class="btn btn-view" title="View 3D Model">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-view" onclick="alert('3D model not available for this product')" title="3D Model Not Available">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 15px; box-shadow: var(--card-shadow);">
                <i class="fas fa-couch" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 20px;"></i>
                <h3 style="color: var(--secondary-color); margin-bottom: 10px;">No Furniture Available</h3>
                <p style="color: var(--secondary-color);">We're currently updating our furniture collection. Please check back soon!</p>
            </div>
        <?php endif; ?>
    </div>


</body>
</html>
