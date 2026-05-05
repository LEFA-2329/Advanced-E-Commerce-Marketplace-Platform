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

// Fetch tracking history
$stmt = $pdo->prepare("
    SELECT * FROM tracking_history 
    WHERE order_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$order_id]);
$tracking_history = $stmt->fetchAll();

// Get tracking status display names
$status_display = [
    'order_placed' => 'Order Placed',
    'processing' => 'Processing',
    'packaging' => 'Packaging',
    'shipped' => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered'
];

// Get status descriptions
$status_descriptions = [
    'order_placed' => 'Your order has been received and is being processed.',
    'processing' => 'Your order is being processed and prepared for packaging.',
    'packaging' => 'Your items are being carefully packaged for shipment.',
    'shipped' => 'Your order has been shipped and is on its way to you.',
    'out_for_delivery' => 'Your order is out for delivery and will arrive soon.',
    'delivered' => 'Your order has been successfully delivered.'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Order Tracking - Store System</title>
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
        .tracking-container {
            max-width: 800px;
            margin: 100px auto 50px;
            padding: 20px;
            
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
        
        .order-header {
            background: linear-gradient(135deg, var(--primary-color), #6a11cb);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .tracking-info {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .current-status {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-packaging { background: #fff3cd; color: #856404; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-out_for_delivery { background: #cce5ff; color: #004085; }
        .status-delivered { background: #d4edda; color: #155724; }
        
        .tracking-timeline {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        
        .timeline-item {
            display: flex;
            margin-bottom: 25px;
            position: relative;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 35px;
            bottom: -25px;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            position: relative;
            z-index: 2;
            flex-shrink: 0;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-date {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--font-color);
        }
        
        .timeline-description {
            color: var(--secondary-color);
            font-size: 0.95rem;
        }
        
        .estimated-delivery {
            background: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
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
            .tracking-container {
                padding: 15px;
            }

            .header-search input[type="search"] {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .tracking-container {
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
        }

        @media (max-width: 480px) {
            .tracking-container {
                margin-top: 70px;
                padding: 5px;
            }

            .order-header, .tracking-info, .tracking-timeline {
                padding: 15px;
            }

            .status-badge {
                font-size: 1rem;
                padding: 10px 20px;
            }

            .timeline-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .timeline-icon {
                margin-bottom: 10px;
                margin-right: 0;
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

    <div class="tracking-container">
        <a href="order_details.php?order_id=<?= $order_id ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Order Details
        </a>
        
        <div class="order-header">
            <p>Tracking Number: <strong><?= htmlspecialchars($order['tracking_number']) ?></strong></p>
        </div>
        
        <div class="tracking-info">
            <div class="current-status">
                <span class="status-badge status-<?= htmlspecialchars($order['tracking_status']) ?>">
                    <?= htmlspecialchars($status_display[$order['tracking_status']] ?? ucfirst($order['tracking_status'])) ?>
                </span>
                <p><?= htmlspecialchars($status_descriptions[$order['tracking_status']] ?? 'Your order is being processed.') ?></p>
            </div>
            
            <?php if ($order['estimated_delivery_date']): ?>
                <div class="estimated-delivery">
                    <strong>Estimated Delivery:</strong> 
                    <?= date('F j, Y', strtotime($order['estimated_delivery_date'])) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tracking-timeline">
            <h3>Tracking History</h3>
            
            <?php if (count($tracking_history) === 0): ?>
                <p>No tracking history available yet.</p>
            <?php else: ?>
                <?php foreach ($tracking_history as $history): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <?= date('M j, Y g:i A', strtotime($history['created_at'])) ?>
                            </div>
                            <div class="timeline-title">
                                <?= htmlspecialchars($status_display[$history['status']] ?? ucfirst($history['status'])) ?>
                                <?php if ($history['location']): ?>
                                    <small style="color: var(--secondary-color);">(<?= htmlspecialchars($history['location']) ?>)</small>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-description">
                                <?= htmlspecialchars($history['description']) ?>
                                <?php if ($history['updated_by']): ?>
                                    <br><small>Updated by: <?= htmlspecialchars($history['updated_by']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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
