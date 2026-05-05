<?php
session_start();
require_once 'db_connection.php';

// Get product ID from URL parameter
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    header("Location: public_furniture_catalog.php");
    exit;
}

// Fetch product details (only furniture category)
$query = "SELECT p.*, pr.discount_percent, pr.promotion_type, pr.is_active as promotion_active
          FROM products p
          LEFT JOIN promotions pr ON p.product_id = pr.product_id
          AND pr.is_active = true
          AND pr.start_date <= CURRENT_DATE
          AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
          WHERE p.product_id = ? AND p.category = 'furniture'";

$stmt = $pdo->prepare($query);
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    // Product not found or not a furniture item
    header("Location: public_furniture_catalog.php");
    exit;
}

if (empty($product['model_3d_url'])) {
    // No 3D model available for this furniture item
    header("Location: public_furniture_catalog.php");
    exit;
}

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

    $quantity = (int)$_POST['quantity'];

    // Add to cart logic
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

// Calculate final price
$final_price = $product['price'];
if ($product['discount_percent'] > 0 && $product['promotion_active']) {
    $final_price = $product['price'] * (1 - $product['discount_percent'] / 100);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - 3D View</title>
    <link rel="stylesheet" href="CuttingEdge/CuttingEdge/main.css">
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
    <script src="https://kit.fontawesome.com/f9b4791500.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
        /* Additional styles for integration */
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary-color-900);
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 1000;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 1);
            transform: scale(1.05);
        }

        .success-message {
            position: absolute;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #c3e6cb;
            z-index: 1000;
            font-weight: 500;
        }

        .login-prompt {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff3cd;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
            z-index: 10000;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .login-prompt a {
            color: #0079d9;
            font-weight: 600;
            text-decoration: none;
        }

        .login-prompt a:hover {
            text-decoration: underline;
        }

        .login-prompt .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #856404;
        }
    </style>
</head>
<body>
    <a href="public_furniture_catalog.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to Catalog
    </a>

    <?php if (isset($_GET['added']) && $_GET['added'] == '1'): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> Product added to cart successfully!
        </div>
    <?php endif; ?>

    <?php if (!$is_logged_in && isset($_GET['login_required'])): ?>
        <div class="login-prompt">
            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
            <strong>Login Required</strong>
            <p style="margin: 15px 0;">Please <a href="unified_login.php?return_url=<?= urlencode($_SERVER['REQUEST_URI']) ?>">sign in</a> to add items to your cart.</p>
        </div>
    <?php endif; ?>

    <video autoplay playsinline id="camera"></video>

    <!-- Entire Model Box -->
    <div class="model-box">
        <model-viewer id="modelViewer"
                      src="<?php echo htmlspecialchars($product['model_3d_url'] ?: 'CuttingEdge/CuttingEdge/office_chair.glb'); ?>"
                      alt="<?php echo htmlspecialchars($product['name']); ?>"
                      ar
                      ar-placement="floor"
                      ar-modes="webxr scene-viewer quick-look"
                      camera-controls
                      interaction-prompt="none"
                      auto-rotate>
        </model-viewer>
    </div>

    <!-- header with navigations -->
    <header id="header">
        <nav>
            <!-- Scale -->
            <label for="model-scale" class="scale-box">
                Scale:
                <input type="range" id="model-scale" min="0.1" max="3" step="0.01" value="1">
            </label>
        </nav>
        <div class="group">
            <aside id="info"><i class="fa-solid fa-info"></i></aside>
            <aside id="below"><i class="fa-solid fa-eye"></i></aside>
        </div>
    </header>

    <footer class="cover">
        <button id="visit-site-btn" class="btn">
            <i class="fa-solid fa-eye"></i>
            View Details
        </button>
        <form method="POST" action="" style="display: inline;">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" name="add_to_cart" class="btn" id="add-to-cart-btn">
                <i class="fa-solid fa-cart-shopping"></i>
                Add to Cart
            </button>
        </form>
        <button slot="ar-button" class="btn">AR</button>
    </footer>

    <!-- Info modal -->
    <div id="info-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="close-btn">&times;</span>
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            <p><?php echo htmlspecialchars($product['description']); ?></p>
            <ul>
                <li>Status: <?php echo $product['stock_quantity'] > 0 ? 'Available' : 'Out of Stock'; ?></li>
                <li>Stock: <?php echo intval($product['stock_quantity']); ?> available</li>
                <?php if ($product['discount_percent'] > 0 && $product['promotion_active']): ?>
                    <li>Price: <span style="text-decoration: line-through; color: #999;">R <?php echo number_format($product['price'], 2); ?></span>
                        <span style="color: #0079d9; font-weight: bold;">R <?php echo number_format($final_price, 2); ?></span>
                        <span style="color: #ff6b35;">(<?php echo $product['discount_percent']; ?>% OFF)</span></li>
                <?php else: ?>
                    <li>Price: R <?php echo number_format($product['price'], 2); ?> inc tax</li>
                <?php endif; ?>
                <li>Category: <?php echo htmlspecialchars(ucfirst($product['category'])); ?></li>
            </ul>
            <form method="POST" action="">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" name="add_to_cart" class="modal-pay">
                    <i class="fas fa-cart-plus"></i>
                    Add to Cart
                </button>
            </form>
        </div>
    </div>

    <script src="CuttingEdge/CuttingEdge/main.js"></script>
    <script>
        // Override the visit site button to go back to catalog
        document.getElementById('visit-site-btn').addEventListener('click', function() {
            window.location.href = 'public_furniture_catalog.php';
        });

        // Handle add to cart button click for non-logged in users
        document.getElementById('add-to-cart-btn').addEventListener('click', function(e) {
            <?php if (!$is_logged_in): ?>
                e.preventDefault();
                window.location.href = 'unified_login.php?return_url=' + encodeURIComponent(window.location.href);
                return false;
            <?php endif; ?>
        });

        // Enhanced modal functionality
        const infoBox = document.getElementById('info');
        const infoModal = document.getElementById('info-modal');
        const closeBtn = document.getElementById('close-btn');

        infoBox.addEventListener('click', () => {
            infoModal.style.display = 'flex';
        });

        closeBtn.addEventListener('click', () => {
            infoModal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === infoModal) {
                infoModal.style.display = 'none';
            }
        });

        // Toggle footer visibility when eye icon is clicked
        const belowBtn = document.getElementById('below');
        const footer = document.querySelector('.cover');

        belowBtn.addEventListener('click', function() {
            footer.classList.toggle('uncover');
        });

        // Auto-hide success message after 5 seconds
        const successMessage = document.querySelector('.success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
