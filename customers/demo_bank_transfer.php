<?php
session_start();
require_once '../db_connection.php';

// Redirect to login if not authenticated as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: ../unified_login.php');
    exit;
}

$customer_id = $_SESSION['user_id'];
$username = $_SESSION['username']; // Assuming username is stored in session

// Get the latest order ID and total amount for this customer
$stmt = $pdo->prepare("SELECT order_id, total_amount FROM orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 1");
$stmt->execute([$customer_id]);
$latest_order = $stmt->fetch();
$order_id = $latest_order ? $latest_order['order_id'] : null;
$order_total = $latest_order ? $latest_order['total_amount'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transfer_amount = trim($_POST['transfer_amount'] ?? '');

    if (empty($transfer_amount) || !is_numeric($transfer_amount) || $transfer_amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (abs($transfer_amount - $order_total) > 0.01) { // Allow small floating point differences
        $error = "Amount must exactly match your order total of R " . number_format($order_total, 2) . ". Please enter R " . number_format($order_total, 2);
    } else {
            // Record the bank transfer in the database
            try {
                $pdo->beginTransaction();

                // Insert bank transfer record
                $stmt = $pdo->prepare("INSERT INTO bank_transfers (order_id, transfer_status, transaction_reference) VALUES (?, 'completed', ?)");
                $stmt->execute([$order_id, 'BANK_TRANSFER_' . time()]);

                // Update order status to approved
                $stmt = $pdo->prepare("UPDATE orders SET order_status = 'approved' WHERE order_id = ?");
                $stmt->execute([$order_id]);

                // Generate tracking number
                $tracking_number = 'TRK' . strtoupper(substr(md5(time() . $order_id), 0, 12));
                
                // Update order with tracking number
                $stmt = $pdo->prepare("UPDATE orders SET tracking_number = ?, tracking_status = 'processing' WHERE order_id = ?");
                $stmt->execute([$tracking_number, $order_id]);

                // Commit the transaction
                $pdo->commit();

                // Show success page with loading animation and redirect options
                $success = true;
                $tracking_info = $tracking_number;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to process the bank transfer: " . $e->getMessage();
            }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bank Transfer - Demo</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .success-message {
            text-align: center;
            padding: 20px;
            background: #d4edda;
            color: #155724;
            border-radius: 10px;
            margin: 20px 0;
        }
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 10px;
        }
        .redirect-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .redirect-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <!-- <header class="main-header">
        <div class="logo">My Store</div>
        <div class="header-search">
            <form method="GET" action="product_browse.php" style="display: flex; align-items: center; gap: 10px;">
                <input type="search" name="search" placeholder="Search for products..." style="width: 200px; padding: 0.5rem; border: 1px solid #ccc; border-radius: 5px;" />
                <button type="submit" style="padding: 0.5rem; border: none; background-color: var(--primary-color); color: white; border-radius: 5px; cursor: pointer;">Search</button>
            </form>
        </div>
    </header> -->

    <div class="container">
        <?php if (isset($success) && $success): ?>
            <!-- Success Section -->
            <div id="loadingOverlay" class="loading-overlay" style="display: flex;">
                <div class="spinner"></div>
                <p style="margin-top: 20px; font-size: 18px;">Processing your payment...</p>
            </div>

            <div id="successSection" style="display: none;">
                <div class="success-message">
                    <div class="success-icon">✓</div>
                    <h3>Payment Successful!</h3>
                    <p>Your bank transfer of R <?= number_format($transfer_amount, 2) ?> has been processed successfully.</p>
                    <?php if (isset($tracking_info)): ?>
                    <p><strong>Your tracking number: <?= htmlspecialchars($tracking_info) ?></strong></p>
                    <p>Your order has been approved and is now being processed.</p>
                    <?php endif; ?>
                </div>

                <div class="redirect-buttons">
                    <a href="product_browse.php" class="redirect-btn btn-primary">Continue Shopping</a>
                    <a href="orders.php" class="redirect-btn btn-secondary">View My Orders</a>
                </div>
            </div>

            <script>
                // Show loading for 30 seconds, then show success
                setTimeout(function() {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    document.getElementById('successSection').style.display = 'block';
                }, 30000); // 30 seconds
            </script>

        <?php else: ?>
            <!-- Bank Transfer Form -->
            <h2>Bank Transfer</h2>
            <p>Welcome, <?= htmlspecialchars($username) ?>! Your order total is R <?= number_format($order_total, 2) ?>. Please enter this exact amount to complete your payment:</p>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="demo_bank_transfer.php">
                <div class="form-group">
                    <label for="transfer_amount">Transfer Amount *</label>
                    <input type="number" id="transfer_amount" name="transfer_amount" required min="0.01" step="0.01" />
                </div>
                <button type="submit">Submit Transfer</button>
            </form>
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
