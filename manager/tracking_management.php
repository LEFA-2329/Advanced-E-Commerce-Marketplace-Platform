<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and is a manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header('Location: ../unified_login.php');
    exit;
}

// Handle tracking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    
    // Update order tracking status
    $update_stmt = $pdo->prepare("
        UPDATE orders 
        SET tracking_status = ?, tracking_updated_at = CURRENT_TIMESTAMP 
        WHERE order_id = ?
    ");
    $update_stmt->execute([$status, $order_id]);
    
    // Add to tracking history
    $history_stmt = $pdo->prepare("
        INSERT INTO tracking_history (order_id, status, description, location, updated_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $history_stmt->execute([$order_id, $status, $description, $location, $_SESSION['username'] ?? 'Manager']);
    
    // Send notification to customer
    $customer_stmt = $pdo->prepare("
        SELECT customer_id FROM orders WHERE order_id = ?
    ");
    $customer_stmt->execute([$order_id]);
    $customer = $customer_stmt->fetch();
    
    if ($customer) {
        $status_display = [
            'order_placed' => 'Order Placed',
            'processing' => 'Processing',
            'packaging' => 'Packaging',
            'shipped' => 'Shipped',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered'
        ];
        
        $message = "Your order #$order_id status has been updated to: " . ($status_display[$status] ?? ucfirst($status));
        if ($description) {
            $message .= " - $description";
        }
        
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (customer_id, message) VALUES (?, ?)");
        $notif_stmt->execute([$customer['customer_id'], $message]);
    }
    
    header("Location: tracking_management.php?success=1");
    exit;
}

// Fetch orders with tracking information
$stmt = $pdo->prepare("
    SELECT o.order_id, o.customer_id, o.order_date, o.total_amount, 
           o.tracking_number, o.tracking_status, o.tracking_updated_at,
           c.username as customer_username
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.tracking_number IS NOT NULL
    ORDER BY o.tracking_updated_at DESC, o.order_date DESC
");
$stmt->execute();
$orders = $stmt->fetchAll();

// Tracking status options
$status_options = [
    'order_placed' => 'Order Placed',
    'processing' => 'Processing',
    'packaging' => 'Packaging',
    'shipped' => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Tracking Management - Manager Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
     <link rel="stylesheet" href="manager_styles.css" />
    <style>
        body {
            padding-top: 70px;
            font-family: 'Poppins', sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            color: purple;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .status-order_placed { background: #e2e3e5; color: #383d41; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-packaging { background: #fff3cd; color: #856404; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-out_for_delivery { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        
        .tracking-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .btn-update {
            background: linear-gradient(135deg, purple, #6a11cb);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(128, 0, 128, 0.3);
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="manager_dashboard.php">Tracking Management</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
             <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="manager_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_product_management.php"><i class="fa-solid fa-bag-shopping"></i> Product Management</a></li>
                <li class="nav-item"><a class="nav-link active" href="tracking_management.php"><i class="fa-solid fa-truck"></i> Tracking</a></li>
                <li class="nav-item"><a class="nav-link" href="manager_promotions_management.php"><i class="fa-solid fa-percent"></i> Promotions</a></li>
                <li class="nav-item"><a class="nav-link" href="ordered_products_history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <h1 class="mb-4">Tracking Management</h1>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Tracking status updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="tracking-table">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Order Date</th>
                    <th>Tracking Number</th>
                    <th>Current Status</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($orders) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No orders with tracking information found.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($order['order_id']) ?></strong></td>
                            <td><?= htmlspecialchars($order['customer_username']) ?></td>
                            <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                            <td><code><?= htmlspecialchars($order['tracking_number']) ?></code></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($order['tracking_status']) ?>">
                                    <?= htmlspecialchars($status_options[$order['tracking_status']] ?? ucfirst($order['tracking_status'])) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y g:i A', strtotime($order['tracking_updated_at'])) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-update text-white" 
                                        data-bs-toggle="modal" data-bs-target="#updateModal"
                                        data-order-id="<?= $order['order_id'] ?>"
                                        data-current-status="<?= $order['tracking_status'] ?>">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateModalLabel">Update Tracking Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="modalOrderId">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Add additional details about this status update..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location (Optional)</label>
                        <input type="text" class="form-control" id="location" name="location" 
                               placeholder="Current location of the package...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_tracking" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle modal data
    const updateModal = document.getElementById('updateModal');
    updateModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const orderId = button.getAttribute('data-order-id');
        const currentStatus = button.getAttribute('data-current-status');
        
        const modalOrderId = updateModal.querySelector('#modalOrderId');
        const modalStatus = updateModal.querySelector('#status');
        
        modalOrderId.value = orderId;
        modalStatus.value = currentStatus;
    });
</script>
</body>
</html>
