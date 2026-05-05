<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header("Location: unified_login.php");
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Fetch profile image filename
$stmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$profile_image = $user && $user['profile_image'] ? $user['profile_image'] : 'default_profile.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Owner Dashboard - Store System</title>
    <link rel="stylesheet" href="dashboard.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        body{
            overflow-y:hidden;
        }
        
    </style>
</head>
<body>
   
<div class="sidebar">
        <div class="user-info" style="padding: 15px; text-align: center; border-bottom: 1px solid #ddd;">
            <img class="profile-image" src="images/<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" /> 
            <div class="profile-name"><?= htmlspecialchars($username) ?><small style="color:rgb(145, 255, 0);font-weight:bold;font-size:2rem;text-shadow:none;margin-left:10px;">.</small></div>
        </div>
        <div class="logo">My Store</div>
        <nav>
            <a href="owner_dashboard.php" class="active"><i class="fa-solid fa-house"></i>Home</a>
            <a href="product_management.php"><i class="fas fa-box-open"></i>Products</a>
            <a href="promotions_management.php"><i class="fas fa-tags"></i>Promotions</a>
            <a href="analytics.php"><i class="fas fa-chart-pie"></i>Analytics</a>
            <a href="AI_business_intelligence.php"><i class="fa-solid fa-robot"></i>Business Intel</a>
           
            <a href="logout.php" class="logout"><i class="fa-solid fa-power-off"></i></a>
            <a href="settings.php" class="settings"><i class="fa-solid fa-gear"  style="cursor:pointer"></i></a>
        </nav>
    </div>
    
   

    <div class="main-content">
        <h2> Dashboard</h2>
        <!-- Removed search bar as per user request -->

        <?php
        // Fetch total number of products owned by the logged-in owner
        $owner_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE owner_id = ?");
        $stmt->execute([$owner_id]);
        $result = $stmt->fetch();
        $total_products = $result ? $result['total'] : 0;
        ?>

        <!-- Removed product display from dashboard as per user request -->
        <!-- Products will be displayed only in product_management.php -->

        <div class="dashboard-grid" style="margin-top: 20px;">
            <div class="dashboard-card">
                <h5>Product Management</h5>
                <p>Total Products: <?= $total_products ?></p>
                <a href="product_management.php" class="btn btn-primary">Manage Products</a>
            </div>
            <div class="dashboard-card">
                <h5>Promotions Management</h5>
                <p>Manage pricing and promotional offers.</p>
                <a href="promotions_management.php" class="btn btn-primary">Manage Promotions</a>
            </div>
            <div class="dashboard-card">
                <h5>Analytics</h5>
                <p>View sales trends, customer insights, and forecasts.</p>
                <a href="analytics.php" class="btn btn-primary">View<br> Analytics</a>
            </div>
            <div class="dashboard-card">
                <h5>Compliance Reports</h5>
                <p>Check compliance status and reports.</p>
                <a href="compliance_reports.php" class="btn btn-primary">View Compliance</a>
            </div>
            <div class="dashboard-card">
                <h5>Financial Analytics</h5>
                <p>Review financial performance and profitability.</p>
                <a href="financial_analytics.php" class="btn btn-primary">View Financials</a>
            </div>
        </div>

        <!-- Removed search bar and related script as per user request -->
    </div>
</body>
</html>
</create_file>
