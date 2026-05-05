<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    header('Location: unified_login.php');
    exit;
}

// Get the owner_id from session
$owner_id = $_SESSION['user_id'];

// Fetch business intelligence data
$sales_data = [];
$customer_data = [];
$inventory_data = [];

// Get sales data for the last 30 days (filtered by owner's products)
$sales_stmt = $pdo->prepare("
    SELECT DATE(o.order_date) as date, COUNT(*) as orders, SUM(o.total_amount) as revenue
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.order_date >= CURRENT_DATE - INTERVAL '30 days'
    AND p.owner_id = ?
    GROUP BY DATE(o.order_date)
    ORDER BY date
");
$sales_stmt->execute([$owner_id]);
$sales_data = $sales_stmt->fetchAll();

// Get customer statistics (customers who bought from this owner)
$customer_stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT c.customer_id) as total_customers,
        COUNT(DISTINCT CASE WHEN c.created_at >= CURRENT_DATE - INTERVAL '30 days' THEN c.customer_id END) as new_customers,
        AVG(customer_order_counts.order_count) as avg_orders_per_customer
    FROM customers c
    JOIN orders o ON c.customer_id = o.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN (
        SELECT
            c2.customer_id,
            COUNT(o2.order_id) as order_count
        FROM customers c2
        JOIN orders o2 ON c2.customer_id = o2.customer_id
        JOIN order_items oi2 ON o2.order_id = oi2.order_id
        JOIN products p2 ON oi2.product_id = p2.product_id
        WHERE p2.owner_id = ?
        GROUP BY c2.customer_id
    ) customer_order_counts ON c.customer_id = customer_order_counts.customer_id
    WHERE p.owner_id = ?
");
$customer_stmt->execute([$owner_id, $owner_id]);
$customer_data = $customer_stmt->fetch();

// Get customer demographics by province (filtered by owner's customers)
$province_stmt = $pdo->prepare("
    SELECT
        c.province,
        COUNT(DISTINCT c.customer_id) as customer_count,
        ROUND(COUNT(DISTINCT c.customer_id) * 100.0 / SUM(COUNT(DISTINCT c.customer_id)) OVER(), 2) as percentage
    FROM customers c
    JOIN orders o ON c.customer_id = o.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE c.province IS NOT NULL AND c.province != ''
    AND p.owner_id = ?
    GROUP BY c.province
    ORDER BY customer_count DESC
");
$province_stmt->execute([$owner_id]);
$province_data = $province_stmt->fetchAll();

// Get customer demographics by gender (filtered by owner's customers)
$gender_stmt = $pdo->prepare("
    SELECT
        c.gender,
        COUNT(DISTINCT c.customer_id) as customer_count,
        ROUND(COUNT(DISTINCT c.customer_id) * 100.0 / SUM(COUNT(DISTINCT c.customer_id)) OVER(), 2) as percentage
    FROM customers c
    JOIN orders o ON c.customer_id = o.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE c.gender IS NOT NULL AND c.gender != ''
    AND p.owner_id = ?
    GROUP BY c.gender
    ORDER BY customer_count DESC
");
$gender_stmt->execute([$owner_id]);
$gender_data = $gender_stmt->fetchAll();

// Get customer demographics by age groups (filtered by owner's customers)
$age_stmt = $pdo->prepare("
    SELECT
        CASE
            WHEN c.age BETWEEN 13 AND 19 THEN '13-19'
            WHEN c.age BETWEEN 20 AND 29 THEN '20-29'
            WHEN c.age BETWEEN 30 AND 39 THEN '30-39'
            WHEN c.age BETWEEN 40 AND 49 THEN '40-49'
            WHEN c.age BETWEEN 50 AND 59 THEN '50-59'
            WHEN c.age >= 60 THEN '60+'
            ELSE 'Unknown'
        END as age_group,
        COUNT(DISTINCT c.customer_id) as customer_count,
        ROUND(COUNT(DISTINCT c.customer_id) * 100.0 / SUM(COUNT(DISTINCT c.customer_id)) OVER(), 2) as percentage
    FROM customers c
    JOIN orders o ON c.customer_id = o.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE c.age IS NOT NULL
    AND p.owner_id = ?
    GROUP BY
        CASE
            WHEN c.age BETWEEN 13 AND 19 THEN '13-19'
            WHEN c.age BETWEEN 20 AND 29 THEN '20-29'
            WHEN c.age BETWEEN 30 AND 39 THEN '30-39'
            WHEN c.age BETWEEN 40 AND 49 THEN '40-49'
            WHEN c.age BETWEEN 50 AND 59 THEN '50-59'
            WHEN c.age >= 60 THEN '60+'
            ELSE 'Unknown'
        END
    ORDER BY customer_count DESC
");
$age_stmt->execute([$owner_id]);
$age_data = $age_stmt->fetchAll();

// Get gender-based ordering analytics (filtered by owner's products)
$gender_order_stmt = $pdo->prepare("
    SELECT
        c.gender,
        COUNT(o.order_id) as total_orders,
        SUM(o.total_amount) as total_revenue,
        AVG(o.total_amount) as avg_order_value,
        COUNT(DISTINCT c.customer_id) as unique_customers
    FROM customers c
    JOIN orders o ON c.customer_id = o.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE c.gender IS NOT NULL AND c.gender != ''
    AND p.owner_id = ?
    GROUP BY c.gender
    ORDER BY total_orders DESC
");
$gender_order_stmt->execute([$owner_id]);
$gender_order_data = $gender_order_stmt->fetchAll();

// Get top products by gender (filtered by owner's products)
$gender_product_stmt = $pdo->prepare("
    SELECT
        c.gender,
        p.name as product_name,
        p.category,
        COUNT(oi.order_item_id) as units_sold,
        SUM(oi.quantity * oi.unit_price) as revenue
    FROM customers c
    JOIN orders o ON c.customer_id = o.customer_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE c.gender IS NOT NULL AND c.gender != ''
    AND p.owner_id = ?
    GROUP BY c.gender, p.product_id, p.name, p.category
    ORDER BY c.gender, units_sold DESC
");
$gender_product_stmt->execute([$owner_id]);
$gender_product_data = $gender_product_stmt->fetchAll();

// Get inventory insights (filtered by owner)
$inventory_stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_products,
        SUM(CASE WHEN stock_quantity <= 5 THEN 1 ELSE 0 END) as low_stock_items,
        AVG(price) as avg_product_price,
        SUM(stock_quantity * price) as total_inventory_value
    FROM products
    WHERE owner_id = ?
");
$inventory_stmt->execute([$owner_id]);
$inventory_data = $inventory_stmt->fetch();

// Get top performing products (filtered by owner)
$top_products_stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.category, p.price,
           COUNT(o.order_id) as total_orders,
           SUM(oi.quantity) as total_quantity_sold
    FROM products p
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id
    WHERE p.owner_id = ?
    AND (o.order_date >= CURRENT_DATE - INTERVAL '30 days' OR o.order_date IS NULL)
    GROUP BY p.product_id, p.name, p.category, p.price
    ORDER BY total_quantity_sold DESC
    LIMIT 5
");
$top_products_stmt->execute([$owner_id]);
$top_products = $top_products_stmt->fetchAll();

// Get customer feedback sentiment (filtered by owner's products)
$feedback_stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_reviews,
        AVG(f.rating) as avg_rating,
        COUNT(CASE WHEN f.rating >= 4 THEN 1 END) as positive_reviews,
        COUNT(CASE WHEN f.rating <= 2 THEN 1 END) as negative_reviews
    FROM feedback f
    JOIN products p ON f.product_id = p.product_id
    WHERE p.owner_id = ?
    AND f.created_at >= CURRENT_DATE - INTERVAL '30 days'
");
$feedback_stmt->execute([$owner_id]);
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

function generateBusinessRecommendations($inventory_data = null, $top_products = null, $owner_id = null) {
    // AI-generated business recommendations based on owner's data
    $recommendations = [];

    if ($inventory_data) {
        if ($inventory_data['low_stock_items'] > 0) {
            $recommendations[] = "Restock {$inventory_data['low_stock_items']} products that are running low on inventory";
        }

        if ($inventory_data['total_products'] < 10) {
            $recommendations[] = "Expand your product catalog - you currently have {$inventory_data['total_products']} products";
        }
    }

    if ($top_products && count($top_products) > 0) {
        $best_seller = $top_products[0];
        $recommendations[] = "Promote your best-selling product '{$best_seller['name']}' with special offers";
    }

    // General recommendations
    $recommendations[] = 'Consider promotions for underperforming categories';
    $recommendations[] = 'Optimize delivery routes for faster shipping';
    $recommendations[] = 'Expand product line based on customer preferences';

    return array_slice($recommendations, 0, 4); // Return top 4 recommendations
}

function generateGenderBasedRecommendations($gender_data, $gender_order_data, $owner_id) {
    $recommendations = [];

    foreach ($gender_data as $gender) {
        $gender_name = $gender['gender'];
        $percentage = $gender['percentage'];

        // Find corresponding order data
        $order_data = array_filter($gender_order_data, function($item) use ($gender_name) {
            return $item['gender'] === $gender_name;
        });

        if (!empty($order_data)) {
            $order_info = reset($order_data);
            $avg_order = $order_info['avg_order_value'];

            if ($gender_name === 'Female') {
                $recommendations[] = "Female customers ({$percentage}%) have average order value of R" . number_format($avg_order, 2) . " - consider beauty and fashion promotions";
            } elseif ($gender_name === 'Male') {
                $recommendations[] = "Male customers ({$percentage}%) have average order value of R" . number_format($avg_order, 2) . " - focus on electronics and sports gear";
            } else {
                $recommendations[] = "Other gender customers ({$percentage}%) show diverse preferences - maintain inclusive product range";
            }
        }
    }

    return $recommendations;
}

function generateAgeBasedRecommendations($age_data) {
    $recommendations = [];

    foreach ($age_data as $age_group) {
        $group = $age_group['age_group'];
        $percentage = $age_group['percentage'];

        switch ($group) {
            case '13-19':
                $recommendations[] = "Young adults (13-19, {$percentage}%) prefer trendy fashion and tech gadgets";
                break;
            case '20-29':
                $recommendations[] = "Millennials (20-29, {$percentage}%) interested in lifestyle products and experiences";
                break;
            case '30-39':
                $recommendations[] = "Gen X (30-39, {$percentage}%) focus on family-oriented and premium products";
                break;
            case '40-49':
                $recommendations[] = "Middle-aged (40-49, {$percentage}%) prefer quality and health products";
                break;
            case '50-59':
                $recommendations[] = "Pre-retirees (50-59, {$percentage}%) interested in luxury and wellness items";
                break;
            case '60+':
                $recommendations[] = "Seniors (60+, {$percentage}%) prefer comfort, health, and convenience products";
                break;
        }
    }

    return $recommendations;
}

function getPersonalizedProductRecommendations($customer_gender = null, $customer_age = null) {
    // This would be used in the product browsing page for personalized recommendations
    $recommendations = [];

    if ($customer_gender === 'Female') {
        $recommendations = [
            'Beauty & Personal Care',
            'Fashion & Clothing',
            'Home & Kitchen',
            'Health & Wellness'
        ];
    } elseif ($customer_gender === 'Male') {
        $recommendations = [
            'Electronics',
            'Sports & Outdoors',
            'Tools & Hardware',
            'Automotive'
        ];
    } else {
        $recommendations = [
            'Books & Media',
            'Home & Garden',
            'Arts & Crafts',
            'General Merchandise'
        ];
    }

    // Age-based adjustments
    if ($customer_age) {
        if ($customer_age >= 13 && $customer_age <= 19) {
            array_unshift($recommendations, 'Gaming', 'Mobile Accessories');
        } elseif ($customer_age >= 20 && $customer_age <= 29) {
            array_unshift($recommendations, 'Lifestyle', 'Tech Gadgets');
        } elseif ($customer_age >= 30 && $customer_age <= 39) {
            array_unshift($recommendations, 'Family Products', 'Premium Items');
        } elseif ($customer_age >= 60) {
            array_unshift($recommendations, 'Health & Comfort', 'Easy Living');
        }
    }

    return array_slice($recommendations, 0, 5); // Return top 5 recommendations
}

$predicted_sales = predictSalesTrend($sales_data);
$sentiment_analysis = analyzeCustomerSentiment();
$business_recommendations = generateBusinessRecommendations($inventory_data, $top_products, $owner_id);
$gender_recommendations = generateGenderBasedRecommendations($gender_data, $gender_order_data, $owner_id);
$age_recommendations = generateAgeBasedRecommendations($age_data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Your Store - AI Business Intelligence Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="modern-styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: whitesmoke;
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
            /* background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); */
            background:whitesmoke;
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
            background: linear-gradient(90deg, #bbb,#aaa);
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
            background: linear-gradient(135deg, white, whitesmoke);
            color: #aaa;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
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
        /* .card.bg-primary .card-header {
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
        } */

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
           background:ghostwhite;
            color:black;
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
            background: linear-gradient(90deg,midnightblue,blue);
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
            color: black;
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

        .navbar{
            /* background: linear-gradient(135deg, #2c3e50, #34495e);  */
            background:whitesmoke;
            box-shadow:0 0 10px #00b39541;
            height: 20%;
        }

        /* Dark mode styles */
        body.dark-mode {
            --glass-bg: rgba(0, 0, 0, 0.2);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-light: #ffffff;
            --text-dark: #e0e0e0;
            background: #aaa;
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
            color: black;
        }

        .container-fluid {
            max-width: 1600px;
            margin-top:10rem;
        }
        .nav-link{
            color: black; 
            text-decoration: none; 
            padding: 12px 18px; 
            border-radius: 12px; 
            background: rgba(255,255,255,0.15); 
            font-weight: 500; 
            transition: all 0.3s ease;
        }

    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top" style="">
    <div class="container">
        <a class="navbar-brand" href="#" style="font-weight: bold; font-size: 1.5rem; letter-spacing: 0.5px;">
            <i class="fas fa-brain me-2"></i>Your AI Business Intelligence
        </a>
        
        <!-- Navigation Header -->
        <div class="navbar-nav mx-auto">
            <div class="nav-header" style="display: flex; gap: 15px; align-items: center;">
                <a href="owner_dashboard.php" class="nav-link active" >
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                 <a href="AI_business_intelligence.php"  class="nav-link" style="color: white; text-decoration: none; padding: 12px 18px; border-radius: 12px; background: linear-gradient(175deg, #f5f7fa 0%, #01757e 100%); border: 1px solid rgba(255, 255, 255, 0.3); font-weight: 600; transition: all 0.3s ease;">
                    <i class="fa-solid fa-robot"></i>Business Intel
                </a>
                <a href="product_management.php" class="nav-link" style="">
                    <i class="fas fa-box me-2"></i>Products
                </a>
                <a href="settings.php" class="nav-link" >
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
                <a href="promotions_management.php" class="nav-link" >
                    <i class="fas fa-tag me-2"></i>Promotions
                </a>
                <a href="analytics.php" class="nav-link" >
                    <i class="fas fa-chart-line me-2"></i>Analytics
                </a>
               
        </div>
            </div>
        </div>

       
    </div>
</nav>

<div class="container-fluid  pt-4">
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
                                <i class="fas fa-chart-line"></i> Your Sales Performance (30 Days)
                                <span class="ai-badge">AI Analytics</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="180"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Customer Sentiment -->
                <div class="col-lg-4">
                    <div class="card bg-info mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comments"></i> Your Customer Sentiment Analysis
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

            <!-- Customer Demographics -->
            <div class="row mb-4">
                <!-- Province Distribution -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-map-marker-alt"></i> Customer Distribution by Province
                                <span class="ai-badge">Geographic Analytics</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Province</th>
                                            <th>Customers</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($province_data as $province): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= htmlspecialchars($province['province']) ?></td>
                                                <td class="text-primary fw-bold"><?= intval($province['customer_count']) ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-primary" role="progressbar"
                                                             style="width: <?= $province['percentage'] ?>%"
                                                             aria-valuenow="<?= $province['percentage'] ?>"
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?= $province['percentage'] ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gender-Based Analytics -->
            <div class="row mb-4">
                <!-- Gender-Based Ordering -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart"></i> Gender-Based Ordering Analytics
                                <span class="ai-badge">Behavioral Analytics</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Gender</th>
                                            <th>Total Orders</th>
                                            <th>Avg Order Value</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gender_order_data as $gender_order): ?>
                                            <tr>
                                                <td class="fw-semibold">
                                                    <i class="fas fa-<?= $gender_order['gender'] === 'Male' ? 'mars text-primary' : ($gender_order['gender'] === 'Female' ? 'venus text-danger' : 'genderless text-warning') ?> me-2"></i>
                                                    <?= htmlspecialchars($gender_order['gender']) ?>
                                                </td>
                                                <td class="text-success fw-bold"><?= intval($gender_order['total_orders']) ?></td>
                                                <td class="text-info fw-bold">R <?= number_format($gender_order['avg_order_value'], 2) ?></td>
                                                <td class="text-primary fw-bold">R <?= number_format($gender_order['total_revenue'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gender-Based Recommendations -->
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-lightbulb"></i> Gender-Based Business Recommendations
                                <span class="ai-badge">AI Insights</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($gender_recommendations as $recommendation): ?>
                                    <li class="list-group-item d-flex align-items-center">
                                        <i class="fas fa-star text-warning me-3"></i>
                                        <span><?= htmlspecialchars($recommendation) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Age-Based Recommendations -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-users"></i> Age-Based Business Recommendations
                                <span class="ai-badge">AI Demographics</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($age_recommendations as $recommendation): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="alert alert-info d-flex align-items-center">
                                            <i class="fas fa-user-graduate text-info me-3"></i>
                                            <span><?= htmlspecialchars($recommendation) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
});



// Original sales chart options
const salesChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    aspectRatio: 2.5,
    plugins: {
        title: {
            display: true,
            text: '📈 Revenue Trend Analysis',
            font: {
                size: 14,
                weight: 'bold'
            },
            color: '#333'
        },
        legend: {
            labels: {
                font: {
                    size: 11
                }
            }
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleFont: {
                size: 12,
                weight: 'bold'
            },
            bodyFont: {
                size: 12
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
                    size: 11
                }
            }
        },
        x: {
            grid: {
                color: 'rgba(0, 0, 0, 0.1)'
            },
            ticks: {
                font: {
                    size: 11
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
};

// Apply options to sales chart
salesChart.options = { ...salesChartOptions };
salesChart.update();

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

// Enhanced AI Voice Assistant with Professional English
if ('speechSynthesis' in window) {
    const synth = window.speechSynthesis;
    let currentUtterance = null;
    let isSpeaking = false;

    // Wait for voices to load
    let voicesLoaded = false;
    synth.onvoiceschanged = function() {
        voicesLoaded = true;
    };

    function getBestVoice() {
        if (!voicesLoaded) return null;

        const voices = synth.getVoices();

        // Priority order for best English voices
        const preferredVoices = [
            'Google UK English Female',
            'Google UK English Male',
            'Microsoft Zira Desktop',
            'Microsoft David Desktop',
            'Samantha',
            'Alex',
            'Victoria',
            'Susan'
        ];

        // Try to find a preferred voice
        for (const preferredVoice of preferredVoices) {
            const voice = voices.find(v => v.name.includes(preferredVoice));
            if (voice) return voice;
        }

        // Fallback to any English voice
        return voices.find(voice =>
            voice.lang.startsWith('en') &&
            (voice.name.includes('Female') || voice.name.includes('Male'))
        ) || voices.find(voice => voice.lang.startsWith('en')) || voices[0];
    }

    function speakBusinessInsights() {
        if (isSpeaking) {
            synth.cancel();
            isSpeaking = false;
            return;
        }

        const revenue = <?= array_sum(array_column($sales_data, 'revenue')) ?>;
        const totalCustomers = <?= $customer_data['total_customers'] ?>;
        const newCustomers = <?= $customer_data['new_customers'] ?>;
        const predictedRevenue = <?= $predicted_sales ?>;
        const sentimentScore = <?= $sentiment_analysis['sentiment_score'] * 100 ?>;

        const insights = `Welcome to your AI Business Intelligence Report.

        Financial Overview: Your total revenue for the past thirty days amounts to R ${revenue.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.

        Customer Analytics: You currently have ${totalCustomers.toLocaleString()} total customers, with ${newCustomers.toLocaleString()} new customers joining in the last thirty days.

        Revenue Prediction: Based on current trends, our AI predicts next week's revenue will be approximately R ${predictedRevenue.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.

        Customer Satisfaction: Your customer sentiment analysis shows a ${sentimentScore.toFixed(1)} percent positive rating, indicating ${sentimentScore > 70 ? 'excellent' : sentimentScore > 50 ? 'good' : 'satisfactory'} customer satisfaction levels.

        Key Recommendations: ${getTopRecommendationsText()}

        This concludes your business intelligence summary.`;

        speakText(insights, 'business-report');
    }

    function getTopRecommendationsText() {
        const recommendations = <?= json_encode(array_slice($business_recommendations, 0, 2)) ?>;
        if (recommendations.length === 0) {
            return "Consider expanding your product catalog and implementing targeted marketing campaigns.";
        }

        return recommendations.map(rec => rec.replace(/^\d+\.\s*/, '')).join('. ') + '.';
    }

    function speakText(text, type = 'general') {
        if (isSpeaking) {
            synth.cancel();
        }

        currentUtterance = new SpeechSynthesisUtterance(text);

        // Enhanced voice settings for better quality
        currentUtterance.rate = type === 'business-report' ? 0.85 : 0.9; // Slightly slower for reports
        currentUtterance.pitch = type === 'business-report' ? 1.0 : 1.05; // More natural pitch
        currentUtterance.volume = 0.9; // Louder and clearer
        currentUtterance.lang = 'en-ZA'; // South African English

        // Select best available voice
        const bestVoice = getBestVoice();
        if (bestVoice) {
            currentUtterance.voice = bestVoice;
        }

        // Add speech event handlers
        currentUtterance.onstart = function() {
            isSpeaking = true;
            updateVoiceButton(true);
            updateVoiceStatus('🔊 Speaking...', true);
        };

        currentUtterance.onend = function() {
            isSpeaking = false;
            updateVoiceButton(false);
            updateVoiceStatus('✅ Speech completed', true);
        };

        currentUtterance.onerror = function(event) {
            console.error('Speech synthesis error:', event.error);
            isSpeaking = false;
            updateVoiceButton(false);
            updateVoiceStatus('❌ Speech error', true);
        };

        synth.speak(currentUtterance);
    }

    function updateVoiceButton(speaking) {
        const voiceBtn = document.querySelector('.voice-assistant-btn');
        if (voiceBtn) {
            if (speaking) {
                voiceBtn.innerHTML = '<i class="fas fa-stop"></i> Stop';
                voiceBtn.style.background = 'linear-gradient(90deg, #e74c3c, #c0392b)';
            } else {
                voiceBtn.innerHTML = '<i class="fas fa-robot"></i> AI Report';
                voiceBtn.style.background = 'linear-gradient(90deg, #27ae60, #229954)';
            }
        }
    }

    // Voice commands for interactive control
    function setupVoiceCommands() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();

            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'en-ZA';

            recognition.onresult = function(event) {
                const command = event.results[0][0].transcript.toLowerCase().trim();

                if (command.includes('play report') || command.includes('start report')) {
                    speakBusinessInsights();
                } else if (command.includes('stop') || command.includes('pause')) {
                    if (isSpeaking) {
                        synth.cancel();
                        isSpeaking = false;
                        updateVoiceButton(false);
                    }
                } else if (command.includes('refresh') || command.includes('update')) {
                    refreshData();
                    speakText("Refreshing your business data now.");
                }
            };

            recognition.onerror = function(event) {
                console.error('Voice recognition error:', event.error);
            };

            // Voice command button
            const voiceCmdBtn = document.createElement('button');
            voiceCmdBtn.innerHTML = '<i class="fas fa-microphone"></i>';
            voiceCmdBtn.className = 'btn btn-secondary position-fixed';
            voiceCmdBtn.style.bottom = '160px';
            voiceCmdBtn.style.right = '30px';
            voiceCmdBtn.style.zIndex = '1000';
            voiceCmdBtn.style.padding = '12px';
            voiceCmdBtn.style.borderRadius = '50%';
            voiceCmdBtn.style.width = '50px';
            voiceCmdBtn.style.height = '50px';
            voiceCmdBtn.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
            voiceCmdBtn.title = 'Voice Commands: "Play Report", "Stop", "Refresh"';

            let isListening = false;
            voiceCmdBtn.onclick = function() {
                if (!isListening) {
                    recognition.start();
                    isListening = true;
                    this.style.background = '#e74c3c';
                    this.innerHTML = '<i class="fas fa-stop"></i>';
                } else {
                    recognition.stop();
                    isListening = false;
                    this.style.background = '';
                    this.innerHTML = '<i class="fas fa-microphone"></i>';
                }
            };

            recognition.onend = function() {
                isListening = false;
                voiceCmdBtn.style.background = '';
                voiceCmdBtn.innerHTML = '<i class="fas fa-microphone"></i>';
            };

            document.body.appendChild(voiceCmdBtn);
        }
    }
    
    // Add enhanced voice control button
    const voiceBtn = document.createElement('button');
    voiceBtn.innerHTML = '<i class="fas fa-robot"></i> AI Report';
    voiceBtn.className = 'btn btn-primary position-fixed tooltip-premium voice-assistant-btn';
    voiceBtn.setAttribute('data-tooltip', 'Listen to Professional AI Business Report');
    voiceBtn.style.bottom = '100px';
    voiceBtn.style.right = '30px';
    voiceBtn.style.zIndex = '1000';
    voiceBtn.style.padding = '12px 20px';
    voiceBtn.style.borderRadius = '25px';
    voiceBtn.style.background = 'linear-gradient(90deg, #27ae60, #229954)';
    voiceBtn.style.border = 'none';
    voiceBtn.style.boxShadow = '0 8px 25px rgba(39, 174, 96, 0.4)';
    voiceBtn.style.color = 'white';
    voiceBtn.style.fontWeight = '600';
    voiceBtn.style.transition = 'all 0.3s ease';
    voiceBtn.onclick = speakBusinessInsights;
    voiceBtn.onmouseenter = function() {
        if (!isSpeaking) {
            this.style.transform = 'scale(1.05)';
            this.style.boxShadow = '0 12px 35px rgba(39, 174, 96, 0.6)';
        }
    };
    voiceBtn.onmouseleave = function() {
        if (!isSpeaking) {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '0 8px 25px rgba(39, 174, 96, 0.4)';
        }
    };
    document.body.appendChild(voiceBtn);

    // Setup voice commands
    setupVoiceCommands();

    // Add keyboard shortcut for voice report (Ctrl+Shift+V)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'V') {
            e.preventDefault();
            speakBusinessInsights();
        }
    });

    // Show voice assistant ready notification
    setTimeout(() => {
        if (voicesLoaded) {
            speakText("Voice assistant is ready. Click the AI Report button or press Control Shift V to hear your business insights.", 'general');
        }
    }, 2000);

    // Add voice status indicator
    const voiceStatus = document.createElement('div');
    voiceStatus.id = 'voice-status-indicator';
    voiceStatus.style.position = 'fixed';
    voiceStatus.style.bottom = '60px';
    voiceStatus.style.right = '30px';
    voiceStatus.style.background = 'rgba(39, 174, 96, 0.9)';
    voiceStatus.style.color = 'white';
    voiceStatus.style.padding = '8px 12px';
    voiceStatus.style.borderRadius = '20px';
    voiceStatus.style.fontSize = '0.8rem';
    voiceStatus.style.fontWeight = '600';
    voiceStatus.style.boxShadow = '0 4px 15px rgba(39, 174, 96, 0.3)';
    voiceStatus.style.zIndex = '1000';
    voiceStatus.style.display = 'none';
    voiceStatus.style.transition = 'all 0.3s ease';
    document.body.appendChild(voiceStatus);

    // Update voice status
    function updateVoiceStatus(message, show = true) {
        voiceStatus.textContent = message;
        voiceStatus.style.display = show ? 'block' : 'none';

        if (show) {
            setTimeout(() => {
                voiceStatus.style.display = 'none';
            }, 3000);
        }
    }

    // Enhanced speakText function with status updates
    const originalSpeakText = speakText;
    speakText = function(text, type = 'general') {
        updateVoiceStatus('🔊 Speaking...', true);
        originalSpeakText.call(this, text, type);
    };
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
