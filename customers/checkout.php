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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');

    if (empty($shipping_address)) {
        $errors[] = "Shipping address is required.";
    }
    if (empty($payment_method)) {
        $errors[] = "Payment method is required.";
    }

    // Fetch cart items
    $stmt = $pdo->prepare("SELECT c.product_id, c.quantity, p.price, p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.customer_id = ?");
    $stmt->execute([$customer_id]);
    $cart_items = $stmt->fetchAll();

    if (count($cart_items) === 0) {
        $errors[] = "Your cart is empty.";
    }

    // Check stock availability
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock_quantity']) {
            $errors[] = "Insufficient stock for product ID " . $item['product_id'];
            break;
        }
    }

    if (empty($errors)) {
        // Calculate total amount
        $total_amount = 0;
        foreach ($cart_items as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }

        try {
            $pdo->beginTransaction();

            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_status, total_amount, order_date, shipping_address) VALUES (?, 'pending', ?, NOW(), ?)");
            $stmt->execute([$customer_id, $total_amount, $shipping_address]);
            $order_id = $pdo->lastInsertId();

            // Insert order items and update product stock
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);

                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);

                // Check if stock is low and create alert
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
                $updated_stock = $stmt->fetch()['stock_quantity'];
                
                if ($updated_stock <= 10) {
                    $stmt = $pdo->prepare("INSERT INTO inventory_alerts (product_id, alert_type, threshold) VALUES (?, 'low_stock', 10)");
                    $stmt->execute([$item['product_id']]);
                }
            }

            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
            $stmt->execute([$customer_id]);

            $pdo->commit();

            // Redirect to demo bank transfer page for payment
            header("Location: demo_bank_transfer.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to process order: " . $e->getMessage();
        }
    }
} else {
    // Fetch cart items for display
    $stmt = $pdo->prepare("SELECT c.product_id, c.quantity, p.name, p.price FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.customer_id = ?");
    $stmt->execute([$customer_id]);
    $cart_items = $stmt->fetchAll();

    $total_amount = 0;
    foreach ($cart_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Checkout - Store System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="styles.css" />
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
            overflow: hidden;
            overflow-y: scroll;
        }

        .checkout-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .cart-summary {
            margin-bottom: 20px;
        }
        .cart-summary h3 {
            margin-bottom: 10px;
        }
        .cart-item {
            border-bottom: 1px solid #ddd;
            padding: 10px 0;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item-name {
            font-weight: bold;
        }
        .cart-item-qty {
            color: #555;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        input[type="text"], select, textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>


    <div class="checkout-container">
        <h2>Checkout</h2>

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
            <div class="cart-summary">
                <h3>Order Summary</h3>
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <span class="cart-item-name"><?= htmlspecialchars($item['name']) ?></span> -
                        <span class="cart-item-qty">Quantity: <?= intval($item['quantity']) ?></span> -
                        <span class="cart-item-price">Price: R <?= number_format($item['price'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
                <p><strong>Total: R <?= number_format($total_amount, 2) ?></strong></p>
            </div>

            <form method="POST" action="checkout.php" novalidate>
                <div class="form-group">
                    <label for="shipping_address">Shipping Address *</label>
                    <textarea id="shipping_address" name="shipping_address" rows="3" required><?= htmlspecialchars($_POST['shipping_address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select a payment method</option>
                        <option value="credit_card" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'credit_card') ? 'selected' : '' ?>>Credit Card</option>
                        <option value="paypal" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'paypal') ? 'selected' : '' ?>>PayPal</option>
                        <option value="bank_transfer" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>

                <?php if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer'): ?>
                    <div class="form-group">
                        <p style="color: #007bff; font-weight: bold;">
                            <i class="fas fa-info-circle"></i>
                            You selected Bank Transfer. You will be redirected to complete the payment after placing your order.
                        </p>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-submit">Place Order</button>
            </form>
        <?php endif; ?>
    </div>


</body>
</html>
