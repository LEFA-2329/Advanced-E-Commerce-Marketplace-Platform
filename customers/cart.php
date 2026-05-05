<?php
session_start();
require_once '../db_connection.php';

// Redirect to login if not authenticated as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: ../unified_login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];
$errors = [];
$success = '';

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

if (isset($_GET['add_to_cart']) && is_numeric($_GET['add_to_cart'])) {
    $product_id = intval($_GET['add_to_cart']);
    $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
    if ($quantity < 1) {
        $errors[] = "Quantity must be at least 1.";
    } else {
        // Check if product is allowed (bypass if viewing all)
        $bypass_check = isset($_GET['view_all']) && $_GET['view_all'] == '1';
        if (!$bypass_check) {
            $product_stmt = $pdo->prepare("SELECT category FROM products WHERE product_id = ?");
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch();
            if ($product && !in_array($product['category'], $allowedCategories)) {
                $errors[] = 'This product is not available in your region.';
            } else {
                // Proceed to add
                // Check if product already in cart
                $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?");
                $stmt->execute([$customer_id, $product_id]);
                $existing = $stmt->fetch();
                if ($existing) {
                    // Update quantity
                    $new_quantity = $existing['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
                    $stmt->execute([$new_quantity, $customer_id, $product_id]);
                } else {
                    // Insert new cart item
                    $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$customer_id, $product_id, $quantity]);
                }
                $success = "Product added to cart.";
            }
        } else {
            // Proceed to add
            // Check if product already in cart
            $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$customer_id, $product_id]);
            $existing = $stmt->fetch();
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
                $stmt->execute([$new_quantity, $customer_id, $product_id]);
            } else {
                // Insert new cart item
                $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$customer_id, $product_id, $quantity]);
            }
            $success = "Product added to cart.";
        }
    }
    // Redirect back to referring page or cart
    $referer = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: $referer");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_selected'])) {
        $product_ids = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $bypass_check = isset($_POST['view_all']) && $_POST['view_all'] == '1';
        foreach ($product_ids as $product_id) {
            $product_id = intval($product_id);
            $quantity = isset($quantities[$product_id]) ? intval($quantities[$product_id]) : 1;
            if ($quantity < 1) {
                $errors[] = "Quantity for product ID $product_id must be at least 1.";
                continue;
            }
            // Check if product is allowed (bypass if viewing all)
            if (!$bypass_check) {
                $product_stmt = $pdo->prepare("SELECT category FROM products WHERE product_id = ?");
                $product_stmt->execute([$product_id]);
                $product = $product_stmt->fetch();
                if ($product && !in_array($product['category'], $allowedCategories)) {
                    $errors[] = "Product ID $product_id is not available in your region.";
                    continue;
                }
            }
            // Check if product already in cart
            $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$customer_id, $product_id]);
            $existing = $stmt->fetch();
            if ($existing) {
                // Update quantity
                $new_quantity = $existing['quantity'] + $quantity;
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
                $stmt->execute([$new_quantity, $customer_id, $product_id]);
            } else {
                // Insert new cart item
                $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$customer_id, $product_id, $quantity]);
            }
        }
        if (empty($errors)) {
            $success = "Selected products added to cart.";
        }
    } elseif (isset($_POST['add_to_cart'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        if ($quantity < 1) {
            $errors[] = "Quantity must be at least 1.";
        } else {
            // Check if product is allowed (bypass if viewing all)
            $bypass_check = isset($_POST['view_all']) && $_POST['view_all'] == '1';
            if (!$bypass_check) {
                $product_stmt = $pdo->prepare("SELECT category FROM products WHERE product_id = ?");
                $product_stmt->execute([$product_id]);
                $product = $product_stmt->fetch();
                if ($product && !in_array($product['category'], $allowedCategories)) {
                    $errors[] = 'This product is not available in your region.';
                } else {
                    // Proceed to add
                    // Check if product already in cart
                    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?");
                    $stmt->execute([$customer_id, $product_id]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        // Update quantity
                        $new_quantity = $existing['quantity'] + $quantity;
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
                        $stmt->execute([$new_quantity, $customer_id, $product_id]);
                    } else {
                        // Insert new cart item
                        $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$customer_id, $product_id, $quantity]);
                    }
                    $success = "Product added to cart.";
                }
            } else {
                // Proceed to add
                // Check if product already in cart
                $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?");
                $stmt->execute([$customer_id, $product_id]);
                $existing = $stmt->fetch();
                if ($existing) {
                    // Update quantity
                    $new_quantity = $existing['quantity'] + $quantity;
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
                    $stmt->execute([$new_quantity, $customer_id, $product_id]);
                } else {
                    // Insert new cart item
                    $stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$customer_id, $product_id, $quantity]);
                }
                $success = "Product added to cart.";
            }
        }
    } elseif (isset($_POST['update_quantity'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        if ($quantity < 1) {
            $errors[] = "Quantity must be at least 1.";
        } else {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $customer_id, $product_id]);
            $success = "Cart updated successfully.";
        }
    } elseif (isset($_POST['remove_item'])) {
        $product_id = intval($_POST['product_id']);
        $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$customer_id, $product_id]);
        $success = "Item removed from cart.";
    }
}

// Fetch cart items (show all items customer has added, regardless of demographic filtering)
$query = "SELECT c.product_id, c.quantity, p.name, p.price, p.image_url, p.stock_quantity, p.category FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.customer_id = ?";
$params = [$customer_id];

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cart_items = $stmt->fetchAll();

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Your Shopping Cart - Store System</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- <link rel="stylesheet" href="styles.css" /> -->
    <style>
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
    font-family: Arial, sans-serif;
    background-color: var(--background);
    margin: 0;
    padding: 0;
    overflow: hidden;
     overflow-y: scroll;
}
        .cart-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 10px;
            margin-top:4rem;
           
        }
        /* Header */
.main-header {
    background-color: var(--card-bg);
    box-shadow: var(--card-shadow);
    padding: 1.4rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
}

.logo {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
}

.main-nav a {
    margin-left: 20px;
    text-decoration: none;
    color: var(--secondary-color);
    font-weight: 500;
    transition: color 0.3s ease;
}

.main-nav a.active, .main-nav a:hover {
    color: var(--primary-color);
}

.main-nav i {
    margin-right: 5px;
}
        .cart-item {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding: 10px 0;
        }
        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            margin-right: 20px;
            border-radius: 8px;
        }
        .cart-item-details {
            flex-grow: 1;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
         
        .quantity-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border:none;
            outline:none;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition:all 0.5s ease;
        }
        .btn-update {
            background: green;
            color: white;
        }
        .btn-update:hover, .btn-update:focus {
            background:none;
            color:green;
            transform:scale(1.5);
        }
        .btn-remove {
            background: darkred;
            color: white;
        }
         .btn-remove:hover, .btn-remove:focus {
            background:none;
            color:darkred;
            transform:scale(1.5);
        }
        .cart-summary {
            text-align: right;
            margin-top: 20px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .btn-checkout {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
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
            justify-content: center;
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

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .cart-container {
                padding: 15px;
            }

            .header-search input[type="search"] {
                width: 200px;
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .cart-item img {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 768px) {
            body {
                overflow: auto;
            }

            .cart-container {
                max-width: 95%;
                margin: 15px auto;
                padding: 10px;
                margin-top: 5rem;
                margin-bottom: 5rem;
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .cart-item img {
                width: 80px;
                height: 80px;
                margin-right: 0;
                margin-bottom: 10px;
            }

            .cart-item-details {
                width: 100%;
            }

            .cart-item-actions {
                width: 100%;
                justify-content: space-between;
            }

            .main-header {
                flex-direction: column;
                align-items: stretch;
                padding: 0.5rem;
            }

            .logo {
                text-align: center;
                margin-bottom: 0.5rem;
            }

            .header-search {
                justify-content: center;
            }

            .header-search form {
                flex-wrap: wrap;
                justify-content: center;
            }

            .header-search input[type="search"] {
                width: 150px;
            }

            .main-footer {
                flex-direction: column;
                gap: 0.5rem;
                padding: 0.5rem;
            }

            .footer-nav {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .cart-container {
                max-width: 100%;
                margin: 10px;
                padding: 8px;
                margin-top: 4rem;
                margin-bottom: 4rem;
            }

            .cart-item img {
                width: 60px;
                height: 60px;
            }

            .header-search input[type="search"] {
                width: 120px;
                font-size: 0.8rem;
            }

            .header-search button {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }

            .footer-nav a {
                font-size: 1rem;
                padding: 0.4rem;
            }
        }
    </style>
</head>
<body>
    <!-- <header class="main-header">
        <div class="logo">My Store</div>
        <nav class="main-nav">
            <a href="product_browse.php"><i class="fas fa-store"></i> Store</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                <a href="orders.php"><i class="fa-solid fa-bag-shopping"></i> My orders</a>
                <a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="../unified_login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="registration.php"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </nav>
        <div class="header-search">
            <form method="GET" action="product_browse.php" style="display: flex; align-items: center; gap: 10px; margin-top: 0.5rem;">
                <input type="search" name="search" placeholder="Search for products..." style="padding: 0.5rem 1rem; border: 1px solid #ccc; border-radius: 25px; font-size: 1rem; outline: none;" />
                <button type="submit" style="padding: 0.5rem 1rem; border: none; background-color: var(--btn-color); color: white; border-radius: 25px; cursor: pointer; font-size: 1rem;">Search</button>
            </form>
        </div>
    </header> -->


    <div class="cart-container">
       

        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="message error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (count($cart_items) === 0): ?>
            <p>Your cart is empty. <a href="product_browse.php">Browse products</a>.</p>
        <?php else: ?>
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <img src="../images/<?= htmlspecialchars(basename($item['image_url'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>" />
                    <div class="cart-item-details">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <p>Price: R <?= number_format($item['price'], 2) ?></p>
                        <p>Stock: <?= intval($item['stock_quantity']) ?></p>
                    </div>
                    <div class="cart-item-actions">
                        <form method="POST" action="cart.php" style="display:inline-block;">
                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>" />
                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= intval($item['stock_quantity']) ?>" class="quantity-input" />
                            <button type="submit" name="update_quantity" class="btn btn-update"><i class="fa-solid fa-pen-to-square"></i></button>
                        <!-- </form>
                        <form method="POST" action="cart.php" style="display:inline-block;"> -->
                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>" />
                            <button type="submit" name="remove_item" class="btn btn-remove" onclick="return confirm('Remove this item from cart?');"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="cart-summary">
                Total: R <?= number_format($total_price, 2) ?>
            </div>
            <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
        <?php endif; ?>
    </div>

    <footer class="main-footer">
        <nav class="footer-nav">
            <a href="product_browse.php"><i class="fas fa-store"></i></a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="wishlist.php"><i class="fas fa-heart"></i></a>
                <a href="orders.php"><i class="fa-solid fa-bag-shopping"></i></a>
                <a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i></a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            <?php else: ?>
                <a href="../unified_login.php"><i class="fas fa-sign-in-alt"></i></a>
                <a href="registration.php"><i class="fas fa-user-plus"></i></a>
            <?php endif; ?>
        </nav>
    </footer>
</body>
</html>
