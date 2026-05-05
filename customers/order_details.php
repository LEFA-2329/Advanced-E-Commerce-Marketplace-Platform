<?php
session_start();
require_once '../db_connection.php';

// Redirect to login if not authenticated as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: ../unified_login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = intval($_GET['order_id']);

// Verify order belongs to customer
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ?");
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

 // Fetch order items with product images
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.image_url 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.product_id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Order Details - Store System</title>
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
         body{
            overflow-y: scroll;
        }
        :root {
            --primary-color: purple;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --font-color: #333;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .order-details-container {
            max-width: 1000px;
            margin: 100px auto 50px;
            padding: 20px;
            margin-top: 0px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            margin-bottom: 30px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        
        .back-link:hover {
            background-color: #f0f0f0;
        }
        
        .order-header-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .order-header-card h2 {
            margin: 0;
            color: var(--primary-color);
        }
        
        .order-status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-meta-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        .meta-item i {
            color: var(--primary-color);
            width: 20px;
        }
        
        .shipping-address {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .shipping-address h3 {
            margin: 0 0 15px 0;
            color: var(--primary-color);
        }
        
        .empty-order {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .empty-order i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .order-items-section {
            margin-bottom: 30px;
        }
        
        .order-items-section h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .order-item-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .item-image {
            border-radius: 50%;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid #f0f0f0;
            transition:all 0.7s ease;
        }
        .item-image:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(128,0,128,0.2);
            transform:scale(1.05);
        }
        .image{
            width: 80px;
            height: 80px;

        }
        .item-details {
            flex: 1;
        }
        
        .item-name {
            margin: 0 0 10px 0;
            color: var(--font-color);
        }
        
        .item-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: var(--secondary-color);
        }
        
        .item-total {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-color);
        }
        
        .order-summary-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row.total {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
            border-bottom: none;
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid #eee;
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

        .header-search {
            display: flex;
            align-items: center;
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

        /* Responsive Styles */
        @media (max-width: 1024px) {
            .order-details-container {
                padding: 15px;
            }

            .header-search input[type="search"] {
                width: 200px;
            }

            .order-item-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .item-image {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 768px) {
            .order-details-container {
                margin-top: 80px;
                padding: 10px;
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

            .order-meta-info {
                grid-template-columns: 1fr;
            }

            .order-item-card {
                padding: 15px;
            }

            .item-meta {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .order-details-container {
                margin-top: 70px;
                padding: 5px;
            }

            .order-header-card, .shipping-address, .order-item-card, .order-summary-card {
                padding: 15px;
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

            .item-name {
                font-size: 1rem;
            }

            .item-total {
                font-size: 1rem;
            }

            .summary-row {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- <header class="main-header">
        <div class="logo">Your Shopping Cart</div>
        <div class="header-search">
            <form method="GET" action="product_browse.php" style="display: flex; align-items: center; gap: 10px;">
                <input type="search" name="search" placeholder="Search for products..." style="width: 200px; padding: 0.5rem; border: 1px solid #ccc; border-radius: 5px;" />
                <button type="submit" style="padding: 0.5rem; border: none; background-color: var(--primary-color); color: white; border-radius: 5px; cursor: pointer;">Search</button>
            </form>
        </div>
    </header> -->

    <div class="order-details-container">
        <a href="orders.php" class="back-link">&larr; Back to Orders</a>
        
        <div class="order-header-card">
            <!-- <h2>Order #<?= htmlspecialchars($order['order_id']) ?></h2> -->
            <div class="order-status-badge status-<?= htmlspecialchars($order['order_status']) ?>">
                <?= htmlspecialchars(ucfirst($order['order_status'])) ?>
            </div>
        </div>
        
        <div class="order-meta-info">
            <div class="meta-item">
                <i class="fas fa-calendar"></i>
                <span>Order Date: <?= date('F j, Y', strtotime($order['order_date'])) ?></span>
            </div>
            <div class="meta-item">
                <i class="fas fa-truck"></i>
                <span>Tracking: 
                    <?php if ($order['tracking_number']): ?>
                        <a href="tracking.php?order_id=<?= $order['order_id'] ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                            <?= htmlspecialchars($order['tracking_number']) ?>
                        </a>
                    <?php else: ?>
                        Not assigned yet
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="shipping-address">
            <h3>Shipping Address</h3>
            <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
        </div>

        <?php if (count($order_items) === 0): ?>
            <div class="empty-order">
                <i class="fas fa-shopping-bag"></i>
                <h3>No Items Found</h3>
                <p>This order doesn't contain any items.</p>
            </div>
        <?php else: ?>
            <div class="order-items-section">
                <h3>Order Items (<?= count($order_items) ?>)</h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item-card">
                        <div class="item-image">
                            <img class="image" src="../images/<?= htmlspecialchars(basename($item['image_url'])) ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGMEYwIi8+Cjx0ZXh0IHg9IjQwIiB5PSI0MCIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNipemU9IjEyIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgYXZhaWxhYmxlPC90ZXh0Pgo8L3N2Zz4K'">
                        </div>
                        <div class="item-details">
                            <h4 class="item-name"><?= htmlspecialchars($item['name']) ?></h4>
                            <div class="item-meta">
                                <span class="quantity">Quantity: <?= intval($item['quantity']) ?></span>
                                <span class="price">Unit Price: R <?= number_format($item['unit_price'], 2) ?></span>
                            </div>
                        </div>
                        <div class="item-total">
                            <span class="total-price">R <?= number_format($item['unit_price'] * $item['quantity'], 2) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-summary-card">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>R <?= number_format($order['total_amount'], 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span>Free</span>
                </div>
                <div class="summary-row total">
                    <span>Order Total:</span>
                    <span>R <?= number_format($order['total_amount'], 2) ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="main-footer">
        <nav class="footer-nav">
            <a href="product_browse.php"><i class="fas fa-store"></i></a>
            <a href="wishlist.php"><i class="fas fa-heart"></i></a>
            <a href="orders.php"><i class="fa-solid fa-bag-shopping"></i></a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
        </nav>
    </footer>
</body>
</html>
