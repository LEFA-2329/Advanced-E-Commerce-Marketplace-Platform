<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is Owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header("Location: unified_login.php");
    exit;
}

$owner_id = $_SESSION['user_id'];

// Fetch sales data aggregated by month for the owner's products
$query = "
    SELECT 
        TO_CHAR(s.sale_date, 'YYYY-MM') AS sale_month,
        SUM(s.total_revenue) AS total_revenue,
        SUM(s.quantity_sold) AS total_quantity
    FROM sales_data s
    JOIN products p ON s.product_id = p.product_id
    WHERE p.owner_id = ?
    GROUP BY sale_month
    ORDER BY sale_month
";
$stmt = $pdo->prepare($query);
$stmt->execute([$owner_id]);
$sales_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$months = [];
$revenues = [];
$quantities = [];
foreach ($sales_by_month as $row) {
    $months[] = $row['sale_month'];
    $revenues[] = (float)$row['total_revenue'];
    $quantities[] = (int)$row['total_quantity'];
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
    <title>Owner Analytics - Store System</title>
    <link rel="stylesheet" href="dashboard.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body{
            overflow-y:hidden;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="user-info" style="padding: 15px; text-align: center; border-bottom: 1px solid #ddd;">
            <img class="profile-image" src="images/<?= htmlspecialchars($profile_image) ?>" />
            <div class="profile-name"><?= htmlspecialchars($_SESSION['username']) ?><small style="color:rgb(145, 255, 0);font-weight:bold;font-size:2rem;text-shadow:none;margin-left:10px;">.</small></div>
        </div>
        <div class="logo">My Store</div>
        <nav>
            <a href="owner_dashboard.php"><i class="fa-solid fa-house"></i>Home</a>
            <a href="product_management.php"><i class="fas fa-box-open"></i>Products</a>
            <a href="promotions_management.php"><i class="fas fa-tags"></i>Promotions</a>
            <a href="analytics.php" class="active"><i class="fas fa-chart-pie"></i>Analytics</a>
            <a href="AI_business_intelligence.php"><i class="fa-solid fa-robot"></i>Business Intel</a>
           
            <a href="logout.php" class="logout"><i class="fa-solid fa-power-off"></i></a>
            <a href="settings.php" class="settings"><i class="fa-solid fa-gear" style="cursor:pointer"></i></a>
        </nav>
    </div>

    <div class="main-content">
        <h2>Sales Analytics</h2>

        <div class="dashboard-cards" style="display: flex; justify-content: space-around; margin-bottom: 30px;">
            <div class="dashboard-card" style="flex: 1; margin: 0 10px; padding: 15px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                <h5>Total Customers</h5>
                <p id="totalCustomers" style="font-size: 1.8rem; font-weight: bold;">0</p>
            </div>
            <div class="dashboard-card" style="flex: 1; margin: 0 10px; padding: 15px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                <h5>Total Orders</h5>
                <p id="totalOrders" style="font-size: 1.8rem; font-weight: bold;">0</p>
            </div>
            <div class="dashboard-card" style="flex: 1; margin: 0 10px; padding: 15px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                <h5>Customers Ordered Today</h5>
                <p id="customersOrderedToday" style="font-size: 1.8rem; font-weight: bold;">0</p>
            </div>
        </div>

        <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div class="chart-container" style="width: 100%; max-width: 400px; margin: auto;">
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-container" style="width: 100%; max-width: 400px; margin: auto;">
                <canvas id="quantityChart"></canvas>
            </div>
            <div class="chart-container" style="width: 100%; max-width: 400px; margin: auto;">
                <canvas id="promotionPieChart"></canvas>
            </div>
            <div class="chart-container" style="width: 100%; max-width: 400px; margin: auto;">
                <canvas id="topProductsDoughnutChart"></canvas>
            </div>
            <div class="chart-container" style="width: 100%; max-width: 400px; margin: auto;">
                <canvas id="categoryRadarChart"></canvas>
            </div>
        </div>
    </div>

    <script>
<?php
// Additional queries for new charts and cards

// Total customers count
$totalCustomersStmt = $pdo->prepare("SELECT COUNT(*) as total_customers FROM customers");
$totalCustomersStmt->execute();
$totalCustomersRow = $totalCustomersStmt->fetch();
$totalCustomers = $totalCustomersRow ? (int)$totalCustomersRow['total_customers'] : 0;

// Total orders count
$totalOrdersStmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders");
$totalOrdersStmt->execute();
$totalOrdersRow = $totalOrdersStmt->fetch();
$totalOrders = $totalOrdersRow ? (int)$totalOrdersRow['total_orders'] : 0;

// Customers ordered today count
$today = date('Y-m-d');
$customersOrderedTodayStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT customer_id) as customers_ordered_today 
    FROM orders 
    WHERE order_date::date = ?
");
$customersOrderedTodayStmt->execute([$today]);
$customersOrderedTodayRow = $customersOrderedTodayStmt->fetch();
$customersOrderedToday = $customersOrderedTodayRow ? (int)$customersOrderedTodayRow['customers_ordered_today'] : 0;

// Pie chart data: % of promotions, % of products bought on promotions, most bought product on promotion
// Total products count
$totalProductsStmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products WHERE owner_id = ?");
$totalProductsStmt->execute([$owner_id]);
$totalProductsRow = $totalProductsStmt->fetch();
$totalProducts = $totalProductsRow ? (int)$totalProductsRow['total_products'] : 0;

// Total promotions count
$totalPromotionsStmt = $pdo->prepare("SELECT COUNT(*) as total_promotions FROM promotions WHERE is_active = TRUE");
$totalPromotionsStmt->execute();
$totalPromotionsRow = $totalPromotionsStmt->fetch();
$totalPromotions = $totalPromotionsRow ? (int)$totalPromotionsRow['total_promotions'] : 0;

// Products bought on promotions count
$productsBoughtOnPromotionsStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.product_id) as products_bought_on_promotions
    FROM order_items oi
    JOIN promotions p ON oi.product_id = p.product_id
    WHERE p.is_active = TRUE
");
$productsBoughtOnPromotionsStmt->execute();
$productsBoughtOnPromotionsRow = $productsBoughtOnPromotionsStmt->fetch();
$productsBoughtOnPromotions = $productsBoughtOnPromotionsRow ? (int)$productsBoughtOnPromotionsRow['products_bought_on_promotions'] : 0;

// Most bought product on promotion
$mostBoughtProductOnPromotionStmt = $pdo->prepare("
    SELECT p.name, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN promotions pr ON oi.product_id = pr.product_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE pr.is_active = TRUE
    GROUP BY p.name
    ORDER BY total_quantity DESC
    LIMIT 1
");
$mostBoughtProductOnPromotionStmt->execute();
$mostBoughtProductOnPromotionRow = $mostBoughtProductOnPromotionStmt->fetch();
$mostBoughtProductOnPromotionName = $mostBoughtProductOnPromotionRow ? $mostBoughtProductOnPromotionRow['name'] : 'N/A';
$mostBoughtProductOnPromotionQuantity = $mostBoughtProductOnPromotionRow ? (int)$mostBoughtProductOnPromotionRow['total_quantity'] : 0;

// Pie chart data arrays
$pieLabels = ['Active Promotions', 'Products Bought on Promotions', 'Most Bought Product on Promotion'];
$pieData = [$totalPromotions, $productsBoughtOnPromotions, $mostBoughtProductOnPromotionQuantity];

// Doughnut chart data: top 5 mostly bought products
$topProductsStmt = $pdo->prepare("
    SELECT p.name, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    GROUP BY p.name
    ORDER BY total_quantity DESC
    LIMIT 5
");
$topProductsStmt->execute();
$topProductsRows = $topProductsStmt->fetchAll();
$topProductNames = [];
$topProductQuantities = [];
foreach ($topProductsRows as $row) {
    $topProductNames[] = $row['name'];
    $topProductQuantities[] = (int)$row['total_quantity'];
}

// Additional chart: Radar chart for product categories sales
$categorySalesStmt = $pdo->prepare("
    SELECT p.category, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    GROUP BY p.category
");
$categorySalesStmt->execute();
$categorySalesRows = $categorySalesStmt->fetchAll();
$categoryNames = [];
$categoryQuantities = [];
foreach ($categorySalesRows as $row) {
    $categoryNames[] = $row['category'] ?: 'Uncategorized';
    $categoryQuantities[] = (int)$row['total_quantity'];
}
?>

        const months = <?= json_encode($months) ?>;
        const revenues = <?= json_encode($revenues) ?>;
        const quantities = <?= json_encode($quantities) ?>;

        const totalCustomers = <?= json_encode($totalCustomers) ?>;
        const totalOrders = <?= json_encode($totalOrders) ?>;
        const customersOrderedToday = <?= json_encode($customersOrderedToday) ?>;

        const pieLabels = <?= json_encode($pieLabels) ?>;
        const pieData = <?= json_encode($pieData) ?>;

        const topProductNames = <?= json_encode($topProductNames) ?>;
        const topProductQuantities = <?= json_encode($topProductQuantities) ?>;

        const categoryNames = <?= json_encode($categoryNames) ?>;
        const categoryQuantities = <?= json_encode($categoryQuantities) ?>;

        // Update cards
        document.getElementById('totalCustomers').textContent = totalCustomers;
        document.getElementById('totalOrders').textContent = totalOrders;
        document.getElementById('customersOrderedToday').textContent = customersOrderedToday;

        // Revenue line chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Total Revenue',
                    data: revenues,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Sales Revenue'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Revenue(ZAR)'
                        },
                        beginAtZero: true
                    }
                }
            }
        });

        // Quantity bar chart
        const quantityCtx = document.getElementById('quantityChart').getContext('2d');
        const quantityChart = new Chart(quantityCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Quantity Sold',
                    data: quantities,
                    backgroundColor: 'midnightblue',
                    borderColor: 'purple',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Quantity Sold'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Quantity'
                        },
                        beginAtZero: true
                    }
                }
            }
        });

        // Promotion pie chart
        const promotionPieCtx = document.getElementById('promotionPieChart').getContext('2d');
        const promotionPieChart = new Chart(promotionPieCtx, {
            type: 'pie',
            data: {
                labels: pieLabels,
                datasets: [{
                    label: 'Promotion Stats',
                    data: pieData,
                    backgroundColor: [
                        'orangered',
                        'darkred',
                        'gold',
                    ],
                    borderColor: [
                        'white',
                        'white',
                        'white'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Promotions Overview'
                    }
                }
            }
        });

        // Top products doughnut chart
        const topProductsDoughnutCtx = document.getElementById('topProductsDoughnutChart').getContext('2d');
        const topProductsDoughnutChart = new Chart(topProductsDoughnutCtx, {
            type: 'doughnut',
            data: {
                labels: topProductNames,
                datasets: [{
                    label: 'Top 5 Products',
                    data: topProductQuantities,
                    backgroundColor: [
                        'purple',
                        'midnightblue',
                        'orangered',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                      'white',
                       'white',
                        'white',
                         'white',
                          'white'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Top 5 Mostly Bought Products'
                    }
                }
            }
        });

        // Category radar chart
        const categoryRadarCtx = document.getElementById('categoryRadarChart').getContext('2d');
        const categoryRadarChart = new Chart(categoryRadarCtx, {
            type: 'radar',
            data: {
                labels: categoryNames,
                datasets: [{
                    label: 'Sales by Category',
                    data: categoryQuantities,
                    fill: true,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgb(255, 99, 132)',
                    pointBackgroundColor: 'rgb(255, 99, 132)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgb(255, 99, 132)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Sales by Product Category'
                    }
                },
                scales: {
                    r: {
                        angleLines: {
                            display: true
                        },
                        suggestedMin: 0
                    }
                }
            }
        });
    </script>
</body>
</html>
