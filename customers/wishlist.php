<?php
session_start();
require_once '../db_connection.php';

// Redirect to login if not authenticated as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: ../unified_login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Get customer province and age for filtering
$customer_stmt = $pdo->prepare("SELECT gender, age, province FROM customers WHERE customer_id = ?");
$customer_stmt->execute([$customer_id]);
$customer = $customer_stmt->fetch();
$customer_gender = $customer['gender'] ?? null;
$customer_age = $customer['age'] ?? null;
$customer_province = $customer['province'] ?? null;

// Define age groups
if (!function_exists('getAgeGroup')) {
    function getAgeGroup($age) {
        if ($age >= 18 && $age <= 25) return '18-25';
        if ($age >= 26 && $age <= 35) return '26-35';
        if ($age >= 36 && $age <= 45) return '36-45';
        if ($age >= 46 && $age <= 55) return '46-55';
        if ($age >= 56 && $age <= 65) return '56-65';
        if ($age >= 66) return '66+';
        return null;
    }
}

$customer_age_group = $customer_age ? getAgeGroup($customer_age) : null;

// Function to assign allowed categories based on province and age group
if (!function_exists('assignProductToProvinceAndAgeGroup')) {
    function assignProductToProvinceAndAgeGroup($province, $age_group) {
        $provinceMapping = [
            'Limpopo' => ['Electronics', 'Clothing', 'Home & Garden'],
            'Mpumalanga' => ['Electronics', 'Sports', 'Beauty'],
            'North West' => ['Electronics', 'Home & Garden'],
            'Gauteng' => ['Electronics', 'Clothing', 'Beauty', 'Sports'],
            'KwaZulu-Natal' => ['Electronics', 'Home & Garden', 'Sports'],
            'Western Cape' => ['Electronics', 'Clothing', 'Home & Garden'],
            'Eastern Cape' => ['Electronics', 'Home & Garden'],
            'Free State' => ['Electronics', 'Sports', 'Beauty'],
            'Northern Cape' => ['Electronics', 'Home & Garden'],
            // Add more provinces as needed
        ];

        $ageGroupMapping = [
            '18-25' => ['Electronics', 'Clothing', 'Sports', 'Beauty'],
            '26-35' => ['Electronics', 'Home & Garden'],
            '36-45' => ['Electronics', 'Home & Garden', 'Sports'],
            '46-55' => ['Electronics', 'Home & Garden'],
            '56+' => ['Electronics', 'Home & Garden'],
        ];

        $allowedCategories = [];

        if ($province && isset($provinceMapping[$province])) {
            $allowedCategories = array_merge($allowedCategories, $provinceMapping[$province]);
        }

        if ($age_group && isset($ageGroupMapping[$age_group])) {
            $allowedCategories = array_merge($allowedCategories, $ageGroupMapping[$age_group]);
        }

        // Remove duplicates
        $allowedCategories = array_unique($allowedCategories);

        return $allowedCategories;
    }
}

$allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);

// Handle add to wishlist request
if (isset($_GET['add_to_wishlist']) && is_numeric($_GET['add_to_wishlist'])) {
    $product_id = intval($_GET['add_to_wishlist']);

    try {
        $stmt = $pdo->prepare("INSERT INTO wishlist (customer_id, product_id) VALUES (?, ?)");
        $stmt->execute([$customer_id, $product_id]);
        $_SESSION['wishlist_message'] = "Product added to wishlist!";
    } catch (PDOException $e) {
        $_SESSION['wishlist_message'] = "Product is already in your wishlist!";
    }
    header("Location: product_browse.php");
    exit;
}

// Handle remove from wishlist request
if (isset($_GET['remove_from_wishlist']) && is_numeric($_GET['remove_from_wishlist'])) {
    $product_id = intval($_GET['remove_from_wishlist']);

    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$customer_id, $product_id]);
    $_SESSION['wishlist_message'] = "Product removed from wishlist!";
    header("Location: wishlist.php");
    exit;
}

// Fetch wishlist items (show all items customer has added, regardless of demographic filtering)
$query = "
    SELECT p.*, w.added_at
    FROM wishlist w
    JOIN products p ON w.product_id = p.product_id
    WHERE w.customer_id = ?
    ORDER BY w.added_at DESC
";
$params = [$customer_id];

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$wishlist_items = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Wishlist - Store System</title>
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

        body {
            font-family: var(--font-family);
            background-color: var(--background-color);
            color: var(--font-color);
            margin: 0;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 120px;
            padding-bottom: 80px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .product-card {
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0 0 1rem 0;
        }

        .wishlist-actions {
            margin-top: auto;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .empty-wishlist {
            text-align: center;
            padding: 3rem;
            color: var(--secondary-color);
        }

        /* Footer */
        .main-footer {
            background-color: var(--card-bg);
            border-top: 1px solid #eee;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1rem;
        }

        .footer-nav {
            display: flex;
            gap: 1rem;
        }

        .footer-nav a {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .footer-nav a:hover, .footer-nav a.active {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-add-to-cart-footer {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding-top: 100px;
                padding-bottom: 100px;
            }

            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .main-footer {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.5rem;
            }

            .footer-nav {
                justify-content: center;
            }

            .btn-add-to-cart-footer {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding-top: 80px;
                padding-bottom: 120px;
            }

            .product-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>My Wishlist</h1>

        <?php if (isset($_SESSION['wishlist_message'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['wishlist_message']) ?>
                <?php unset($_SESSION['wishlist_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (count($wishlist_items) === 0): ?>
            <div class="empty-wishlist">
                <i class="fas fa-heart" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h3>Your wishlist is empty</h3>
                <p>Start adding products you love!</p>
                <a href="product_browse.php" class="btn btn-primary">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($wishlist_items as $product): ?>
                    <div class="product-card">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="../images/<?= htmlspecialchars(basename($product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" />
                        <?php else: ?>
                            <div style="height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="product-info">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-price">R <?= number_format($product['price'], 2) ?></p>
                            <p>Stock: <?= intval($product['stock_quantity']) ?></p>
                            <div class="wishlist-actions">
                                <a href="cart.php?add_to_cart=<?= $product['product_id'] ?>&quantity=1&view_all=1" class="btn btn-primary">Add to Cart</a>
                                <a href="wishlist.php?remove_from_wishlist=<?= $product['product_id'] ?>" class="btn btn-danger">Remove</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="main-footer">
        <nav class="footer-nav">
            <a href="product_browse.php"><i class="fas fa-store"></i></a>
            <a href="wishlist.php" class="active"><i class="fas fa-heart"></i></a>
            <a href="orders.php"><i class="fa-solid fa-bag-shopping"></i></a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
        </nav>
        <?php if (count($wishlist_items) > 0): ?>
            <form method="POST" action="cart.php" style="display: inline;">
                <input type="hidden" name="view_all" value="1" />
                <?php foreach ($wishlist_items as $product): ?>
                    <input type="hidden" name="product_ids[]" value="<?= $product['product_id'] ?>" />
                    <input type="hidden" name="quantities[<?= $product['product_id'] ?>]" value="1" />
                <?php endforeach; ?>
                <button type="submit" name="add_selected" class="btn-add-to-cart-footer"> <i class="fa-solid fa-plus"></i> Add All to Cart</button>
            </form>
        <?php endif; ?>
    </footer>
</body>
</html>
