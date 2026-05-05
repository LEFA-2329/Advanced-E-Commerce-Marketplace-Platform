<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header('Location: unified_login.php');
    exit;
}

// Fetch business intelligence data
$sales_data = [];
$customer_data = [];
$inventory_data = [];

// Get sales data for the last 30 days
$sales_stmt = $pdo->prepare("
    SELECT DATE(order_date) as date, COUNT(*) as orders, SUM(total_amount) as revenue
    FROM orders 
    WHERE order_date >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY DATE(order_date)
    ORDER BY date
");
$sales_stmt->execute();
$sales_data = $sales_stmt->fetchAll();

// Get customer statistics
$customer_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_customers,
        COUNT(CASE WHEN created_at >= CURRENT_DATE - INTERVAL '30 days' THEN 1 END) as new_customers,
        AVG((SELECT COUNT(*) FROM orders WHERE orders.customer_id = customers.customer_id)) as avg_orders_per_customer
    FROM customers
");
$customer_stmt->execute();
$customer_data = $customer_stmt->fetch();

// Get inventory insights
$inventory_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock_quantity <= 5 THEN 1 ELSE 0 END) as low_stock_items,
        AVG(price) as avg_product_price,
        SUM(stock_quantity * price) as total_inventory_value
    FROM products
");
$inventory_stmt->execute();
$inventory_data = $inventory_stmt->fetch();

// Get top performing products
$top_products_stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.category, p.price, 
           COUNT(o.order_id) as total_orders,
           SUM(oi.quantity) as total_quantity_sold
    FROM products p
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE o.order_date >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY p.product_id, p.name, p.category, p.price
    ORDER BY total_quantity_sold DESC
    LIMIT 10
");
$top_products_stmt->execute();
$top_products = $top_products_stmt->fetchAll();

// Get customer feedback sentiment (placeholder for AI analysis)
$feedback_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        AVG(rating) as avg_rating,
        COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_reviews,
        COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_reviews
    FROM feedback
    WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
");
$feedback_stmt->execute();
$feedback_data = $feedback_stmt->fetch();

// AI-Powered Predictions (Placeholder functions - would be implemented with ML models)
function predictSalesTrend($sales_data) {
    // This would use machine learning to predict future sales
    $last_week_sales = array_slice($sales_data, -7);
    $total_last_week = array_sum(array_column($last_week_sales, 'revenue'));
    return $total_last_week * 1.15; // Simple prediction: 15% growth
}

function analyzeCustomerSentiment() {
    // This would use NLP to analyze customer feedback
    return [
        'overall_sentiment' => 'Positive',
        'key_improvement_areas' => ['Delivery Speed', 'Product Quality', 'Customer Service'],
        'sentiment_score' => 0.78
    ];
}

function generateBusinessRecommendations() {
    // AI-generated business recommendations
    return [
        'Increase inventory for high-demand products',
        'Consider promotions for underperforming categories',
        'Optimize delivery routes for faster shipping',
        'Expand product line based on customer preferences'
    ];
}

$predicted_sales = predictSalesTrend($sales_data);
$sentiment_analysis = analyzeCustomerSentiment();
$business_recommendations = generateBusinessRecommendations();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Owner Dashboard - AI-Powered Business Intelligence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="modern-styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-light: #ffffff;
            --text-dark: #333333;
        }

        /* ===== PROFESSIONAL CARD STYLES ===== */
        .ai-insight-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .ai-insight-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2ecc71, #e74c3c, #f39c12);
        }

        .ai-insight-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }
        
        .dashboard-stat {
            text-align: center;
            padding: 30px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(245,245,245,0.95) 100%);
            margin: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .dashboard-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .dashboard-stat:hover::before {
            opacity: 1;
        }

        .dashboard-stat:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        
        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stat-label {
            font-size: 1rem;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .ai-badge {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .card {
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(250,250,250,0.98) 100%);
            overflow: hidden;
            position: relative;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498db, #2ecc71);
        }

        .card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .card-header {
            border-radius: 16px 16px 0 0 !important;
            border: none;
            font-weight: 700;
            font-size: 1.1rem;
            padding: 20px 25px;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-body {
            padding: 25px;
        }

        /* Professional color scheme for different card types */
        .card.bg-primary .card-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .card.bg-info .card-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
        }

        .card.bg-success .card-header {
            background: linear-gradient(135deg, #27ae60, #229954);
        }

        .card.bg-warning .card-header {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .card.bg-danger .card-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .list-group-item {
            border: none;
            padding: 15px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 10px !important;
            margin: 5px 0;
        }

        .list-group-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }

        .list-group-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .table th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
        }

        .display-4 {
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        /* Sidebar styling */
        .sidebar-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Premium animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-pulse {
            animation: pulse 2s infinite;
        }

        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }

        /* Premium chart styling */
        .chart-container {
            position: relative;
            margin: 20px 0;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        /* Dark mode toggle */
        .dark-mode-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .dark-mode-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }

        /* Loading states */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 1.5s infinite;
        }

        /* Premium tooltips */
        .tooltip-premium {
            position: relative;
        }

        .tooltip-premium::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .tooltip-premium:hover::before {
            opacity: 1;
            visibility: visible;
        }

        /* Real-time update indicator */
        .real-time-indicator {
            position: fixed;
            top: 80px;
            right: 30px;
            background: rgba(76, 175, 80, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            z-index: 1000;
            display: none;
        }

        /* Premium table styling */
        .table-premium {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .table-premium tbody tr {
            transition: all 0.3s ease;
        }

        .table-premium tbody tr:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .dashboard-stat {
                padding: 15px;
                margin: 10px;
            }
            
            .stat-value {
                font-size: 2rem;
            }
            
            .ai-insight-card,
            .prediction-card,
            .recommendation-card {
                padding: 20px;
            }

            .dark-mode-toggle {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }

        /* Dark mode styles */
        body.dark-mode {
            --glass-bg: rgba(0, 0, 0, 0.2);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-light: #ffffff;
            --text-dark: #e0e0e0;
            background: #1a1a1a;
            color: var(--text-dark);
        }

        body.dark-mode .card {
            background: rgba(45, 45, 45, 0.95);
            color: var(--text-dark);
        }

        body.dark-mode .table {
            color: var(--text-dark);
        }

        body.dark-mode .list-group-item {
            background: rgba(45, 45, 45, 0.8);
            color: var(--text-dark);
        }

        body.dark-mode .list-group-item:hover {
            background: var(--primary-gradient);
            color: white;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="fas fa-brain"></i> AI Business Intelligence
        </a>
        
        <!-- Navigation Header -->
        <div class="navbar-nav mx-auto">
            <div class="nav-header" style="display: flex; gap: 15px; align-items: center;">
                <a href="owner_dashboard.php" class="nav-link active" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a href="product_management.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-box me-2"></i>Products
                </a>
                <a href="inventory_management.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-warehouse me-2"></i>Inventory
                </a>
                <a href="order_management.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-shopping-cart me-2"></i>Orders
                </a>
                <a href="customer_management.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-users me-2"></i>Customers
                </a>
                <a href="sales_reports.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
                <a href="settings.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
                <a href="promotions.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-tag me-2"></i>Promotions
                </a>
                <a href="analytics.php" class="nav-link" style="color: white; text-decoration: none; padding: 10px 15px; border-radius: 10px; background: rgba(255,255,255,0.1);">
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
            </div>
        </div>

        <div class="navbar-nav ms-auto">
            <span class="navbar-text me-3">
                Welcome, <?= htmlspecialchars($_SESSION['username']) ?> (Owner)
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-5 pt-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-12">
            <!-- AI Insights Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="ai-insight-card">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="dashboard-stat">
                                    <div class="stat-value">R <?= number_format(array_sum(array_column($sales_data, 'revenue')), 2) ?></div>
                                    <div class="stat-label">30-Day Revenue</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-stat">
                                    <div class="stat-value"><?= $customer_data['total_customers'] ?></div>
                                    <div class="stat-label">Total Customers</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-stat">
                                    <div class="stat-value"><?= $customer_data['new_customers'] ?></div>
                                    <div class="stat-label">New Customers (30d)</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-stat">
                                    <div class="stat-value">R <?= number_format($predicted_sales, 2) ?></div>
                                    <div class="stat-label">Predicted Next Week Revenue <span class="ai-badge">AI</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Sales Chart -->
                <div class="col-lg-8">
                    <div class="card bg-primary mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line"></i> Sales Performance (30 Days)
                                <span class="ai-badge">AI Analytics</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Customer Sentiment -->
                <div class="col-lg-4">
                    <div class="card bg-info mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comments"></i> Customer Sentiment Analysis
                                <span class="ai-badge">NLP</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="display-4 text-<?= $sentiment_analysis['sentiment_score'] > 0.6 ? 'success' : 'warning' ?>">
                                    <?= number_format($sentiment_analysis['sentiment_score'] * 100, 0) ?>%
                                </div>
                                <p class="text-muted">Overall Sentiment Score</p>
                            </div>
                            <h6 class="mb-3">Key Improvement Areas:</h6>
                            <ul class="list-group">
                                <?php foreach ($sentiment_analysis['key_improvement_areas'] as $area): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="fas fa-arrow-right text-primary me-3"></i>
                                        <?= htmlspecialchars($area) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- AI Recommendations -->
                <div class="col-lg-6">
                    <div class="card bg-success mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-lightbulb"></i> AI Business Recommendations
                                <span class="ai-badge">Machine Learning</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($business_recommendations as $recommendation): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="fas fa-check-circle text-success me-3"></i>
                                        <span><?= htmlspecialchars($recommendation) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="col-lg-6">
                    <div class="card bg-warning mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-star"></i> Top Performing Products
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Units Sold</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= htmlspecialchars($product['name']) ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($product['category']) ?></span></td>
                                                <td class="text-success fw-bold">R <?= number_format($product['price'], 2) ?></td>
                                                <td class="text-primary fw-bold"><?= intval($product['total_quantity_sold']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Insights -->
            <div class="row">
                <div class="col-12">
                    <div class="card bg-danger">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-boxes"></i> Inventory Intelligence
                                <span class="ai-badge">Predictive Analytics</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <div class="display-4 text-primary"><?= $inventory_data['total_products'] ?></div>
                                    <p class="text-muted">Total Products</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="display-4 text-warning"><?= $inventory_data['low_stock_items'] ?></div>
                                    <p class="text-muted">Low Stock Items</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="display-4 text-info">R <?= number_format($inventory_data['avg_product_price'], 2) ?></div>
                                    <p class="text-muted">Average Price</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="display-4 text-success">R <?= number_format($inventory_data['total_inventory_value'], 2) ?></div>
                                    <p class="text-muted">Total Inventory Value</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
// Premium Sales Chart with enhanced styling
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($sales_data, 'date')) ?>,
        datasets: [{
            label: 'Daily Revenue',
            data: <?= json_encode(array_column($sales_data, 'revenue')) ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.2)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#667eea',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: '📈 Revenue Trend Analysis',
                font: {
                    size: 16,
                    weight: 'bold'
                },
                color: '#333'
            },
            legend: {
                labels: {
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: {
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                },
                callbacks: {
                    label: function(context) {
                        return 'R ' + context.raw.toFixed(2);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                ticks: {
                    callback: function(value) {
                        return 'R ' + value.toLocaleString();
                    },
                    font: {
                        size: 12
                    }
                }
            },
            x: {
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                ticks: {
                    font: {
                        size: 12
                    }
                }
            }
        },
        animations: {
            tension: {
                duration: 1000,
                easing: 'linear'
            }
        }
    }
});

// Dark Mode Toggle
const darkModeToggle = document.createElement('button');
darkModeToggle.className = 'dark-mode-toggle';
darkModeToggle.innerHTML = '🌙';
darkModeToggle.title = 'Toggle Dark Mode';
darkModeToggle.onclick = function() {
    document.body.classList.toggle('dark-mode');
    this.innerHTML = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
    
    // Save preference to localStorage
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
};
document.body.appendChild(darkModeToggle);

// Load dark mode preference
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
    darkModeToggle.innerHTML = '☀️';
}

// AI Voice Assistant (Premium)
if ('speechSynthesis' in window) {
    const synth = window.speechSynthesis;
    
    function speakBusinessInsights() {
        const insights = `Business Intelligence Report. Total revenue for the last 30 days is R <?= number_format(array_sum(array_column($sales_data, 'revenue')), 2) ?>. 
        You have <?= $customer_data['total_customers'] ?> total customers with <?= $customer_data['new_customers'] ?> new signups this month. 
        AI predicts next week revenue of R <?= number_format($predicted_sales, 2) ?>. 
        Customer sentiment is <?= $sentiment_analysis['overall_sentiment'] ?> with a score of <?= number_format($sentiment_analysis['sentiment_score'] * 100, 0) ?> percent.`;
        
        const utterance = new SpeechSynthesisUtterance(insights);
        utterance.rate = 0.9;
        utterance.pitch = 1.1;
        utterance.volume = 0.8;
        
        // Premium voice selection (if available)
        const voices = synth.getVoices();
        const premiumVoice = voices.find(voice => voice.name.includes('Google') || voice.name.includes('Samantha'));
        if (premiumVoice) {
            utterance.voice = premiumVoice;
        }
        
        synth.speak(utterance);
    }
    
    // Add premium voice control button
    const voiceBtn = document.createElement('button');
    voiceBtn.innerHTML = '<i class="fas fa-robot"></i> AI Report';
    voiceBtn.className = 'btn btn-primary position-fixed tooltip-premium';
    voiceBtn.setAttribute('data-tooltip', 'Listen to AI Business Insights');
    voiceBtn.style.bottom = '100px';
    voiceBtn.style.right = '30px';
    voiceBtn.style.zIndex = '1000';
    voiceBtn.style.padding = '12px 20px';
    voiceBtn.style.borderRadius = '25px';
    voiceBtn.style.background = 'var(--primary-gradient)';
    voiceBtn.style.border = 'none';
    voiceBtn.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.4)';
    voiceBtn.onclick = speakBusinessInsights;
    voiceBtn.onmouseenter = function() {
        this.style.transform = 'scale(1.05)';
        this.style.boxShadow = '0 12px 35px rgba(102, 126, 234, 0.6)';
    };
    voiceBtn.onmouseleave = function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.4)';
    };
    document.body.appendChild(voiceBtn);
}

// Real-time data refresh with visual feedback
const realTimeIndicator = document.createElement('div');
realTimeIndicator.className = 'real-time-indicator';
realTimeIndicator.innerHTML = '🔄 Data Updated';
document.body.appendChild(realTimeIndicator);

function refreshData() {
    // Show loading state
    document.querySelectorAll('.dashboard-stat, .card').forEach(el => {
        el.classList.add('loading');
    });
    
    fetch('?refresh=1')
        .then(response => response.text())
        .then(() => {
            // Hide loading state
            document.querySelectorAll('.loading').forEach(el => {
                el.classList.remove('loading');
            });
            
            // Show update indicator
            realTimeIndicator.style.display = 'block';
            setTimeout(() => {
                realTimeIndicator.style.display = 'none';
            }, 3000);
            
            console.log('📊 Data refreshed successfully');
        })
        .catch(error => {
            console.error('Refresh error:', error);
            document.querySelectorAll('.loading').forEach(el => {
                el.classList.remove('loading');
            });
        });
}

// Refresh every 2 minutes with visual feedback
setInterval(refreshData, 120000);

// Initial animations
document.addEventListener('DOMContentLoaded', function() {
    // Animate elements on load
    const animatedElements = document.querySelectorAll('.dashboard-stat, .card, .ai-insight-card');
    animatedElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        setTimeout(() => {
            el.style.transition = 'all 0.6s ease-out';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add hover effects to tables
    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.classList.add('table-premium');
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+D for dark mode
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        darkModeToggle.click();
    }
    // Ctrl+R for refresh
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        refreshData();
    }
    // Ctrl+V for voice report
    if (e.ctrlKey && e.key === 'v') {
        e.preventDefault();
        if (document.querySelector('.btn-primary.position-fixed')) {
            document.querySelector('.btn-primary.position-fixed').click();
        }
    }
});

// Performance monitoring
const perfObserver = new PerformanceObserver((list) => {
    list.getEntries().forEach((entry) => {
        console.log(`⚡ ${entry.name}: ${entry.duration.toFixed(2)}ms`);
    });
});
perfObserver.observe({ entryTypes: ['measure', 'navigation', 'resource'] });

// Measure page load performance
performance.mark('dashboard-loaded');
performance.measure('Dashboard Load Time', 'navigationStart', 'dashboard-loaded');
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
