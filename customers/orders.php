<?php
session_start();
require_once '../db_connection.php';

// Redirect to login if not authenticated as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: ../unified_login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];

// Fetch orders for the customer with order items preview
$stmt = $pdo->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count,
           (SELECT p.image_url FROM order_items oi 
            JOIN products p ON oi.product_id = p.product_id 
            WHERE oi.order_id = o.order_id LIMIT 1) as preview_image
    FROM orders o 
    WHERE o.customer_id = ? 
    ORDER BY o.order_date DESC
");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Your Orders - Store System</title>
    <link rel="stylesheet" href="styles.css" />
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
    background-color: var(--background-color);
    margin: 0;
    padding: 0;
    overflow: auto;
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
        .orders-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 20px;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .order-header {
            background: linear-gradient(135deg, var(--primary-color), #6a11cb);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--font-color);
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-items-preview {
            padding: 20px;
            border-top: 1px solid #eee;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .item-preview {
            text-align: center;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 2px solid #f0f0f0;
            transition:all 0.7s ease;
        }
         .item-image:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(128,0,128,0.2);
            transform:scale(1.05);
        }
        
        .item-name {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .item-quantity {
            font-size: 0.8rem;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .order-actions {
            padding: 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        
        .btn-view-details {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-view-details:hover {
            background: #6a11cb;
            color: white;
        }
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            color: var(--secondary-color);
        }
        
        .empty-orders i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
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
            .orders-container {
                padding: 15px;
            }

            .order-info {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .header-search input[type="search"] {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .orders-container {
                margin-top: 80px;
                padding: 10px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .order-info {
                grid-template-columns: 1fr;
                gap: 10px;
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
            .orders-container {
                margin-top: 70px;
                padding: 5px;
            }

            .order-card {
                margin-bottom: 15px;
            }

            .order-header, .order-info, .order-items-preview, .order-actions {
                padding: 15px;
            }

            .info-label {
                font-size: 0.8rem;
            }

            .info-value {
                font-size: 1rem;
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
        <div class="logo">Your Shopping Cart</div>
        <div class="header-search">
            <form method="GET" action="product_browse.php" style="display: flex; align-items: center; gap: 10px;">
                <input type="search" name="search" placeholder="Search for products..." style="width: 200px; padding: 0.5rem; border: 1px solid #ccc; border-radius: 5px;" />
                <button type="submit" style="padding: 0.5rem; border: none; background-color: var(--primary-color); color: white; border-radius: 5px; cursor: pointer;">Search</button>
            </form>
        </div>
    </header> -->

    <div class="orders-container">
        <h2 style="margin-bottom: 30px; color: var(--primary-color);">Your Orders</h2>
        
        <?php if (count($orders) === 0): ?>
            <div class="empty-orders">
                <i class="fas fa-shopping-bag"></i>
                <h3>No Orders Yet</h3>
                <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="product_browse.php" class="btn-view-details" style="margin-top: 20px;">
                    <i class="fas fa-store"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <!-- <h3 style="margin: 0; font-size: 1.4rem;">Order #<?= htmlspecialchars($order['order_id']) ?></h3> -->
                            <p style="margin: 5px 0 0 0; opacity: 0.9;">Placed on <?= date('M j, Y', strtotime($order['order_date'])) ?></p>
                        </div>
                        <span class="status-badge status-<?= htmlspecialchars($order['order_status']) ?>">
                            <?= htmlspecialchars(ucfirst($order['order_status'])) ?>
                        </span>
                    </div>
                    
                    <div class="order-info">
                        <div class="info-item">
                            <span class="info-label">Total Amount</span>
                            <span class="info-value">R <?= number_format($order['total_amount'], 2) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Items</span>
                            <span class="info-value"><?= intval($order['item_count']) ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tracking Number (Click the number to track)</span>
                            <span class="info-value">
                                <?php if ($order['tracking_number']): ?>
                                    <a href="tracking.php?order_id=<?= $order['order_id'] ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                        <?= htmlspecialchars($order['tracking_number']) ?>
                                    </a>
                                <?php else: ?>
                                    Not assigned
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($order['preview_image']): ?>
                    <div class="order-items-preview">
                        <h4 style="margin: 0; color: var(--secondary-color);">Items in this order:</h4>
                        <div class="items-grid">
                            <div class="item-preview">
                                <img src="../images/<?= htmlspecialchars(basename($order['preview_image'])) ?>" 
                                     alt="Product preview" 
                                     class="item-image"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGMEYwIi8+Cjx0ZXh0IHg9IjQwIiB5PSI0MCIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZWRsZSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5Ij5JbWFnZSBub3QgYXZhaWxhYmxlPC90ZXh0Pgo8L3N2Zz4K'">
                                <div class="item-name">Product Preview</div>
                                <div class="item-quantity">+<?= intval($order['item_count']) - 1 ?> more</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-actions">
                        <a href="order_details.php?order_id=<?= $order['order_id'] ?>" class="btn-view-details">
                            <i class="fas fa-eye"></i> View Full Order Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="main-footer">
        <nav class="footer-nav">
            <a href="product_browse.php"><i class="fas fa-store"></i></a>
            <a href="wishlist.php"><i class="fas fa-heart"></i></a>
            <a href="orders.php" class="active"><i class="fa-solid fa-bag-shopping"></i></a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
        </nav>
    </footer>
</body>
</html>
