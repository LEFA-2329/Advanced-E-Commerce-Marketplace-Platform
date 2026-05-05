<?php
session_start();
require_once '../db_connection.php';
require_once 'ai_learning_system.php';

// Redirect to login if not authenticated as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header('Location: ../unified_login.php');
    exit;
}

// Fetch all categories for the filter dropdown
$category_stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_COLUMN);

// Initialize filter variables
$search = '';
$selected_category = '';

// Get customer gender, age, and province for personalized recommendations and filtering
$customer_stmt = $pdo->prepare("SELECT gender, age, province FROM customers WHERE customer_id = ?");
$customer_stmt->execute([$_SESSION['user_id']]);
$customer = $customer_stmt->fetch();
$customer_gender = $customer['gender'] ?? null;
$customer_age = $customer['age'] ?? null;
$customer_province = $customer['province'] ?? null;

if (!function_exists('getAgeGroup')) {
    function getAgeGroup($age) {
        if ($age >= 18 && $age <= 25) return '18-25';
        if ($age >= 26 && $age <= 35) return '26-35';
        if ($age >= 36 && $age <= 45) return '36-45';
        if ($age >= 46 && $age <= 55) return '46-55';
        if ($age >= 56 && $age <= 65) return '56-65';
        if ($age >= 66) return '66+';
        return null;
    }
}

$customer_age_group = $customer_age ? getAgeGroup($customer_age) : null;

// Check if viewing all products (support both parameter names for compatibility)
$view_all = (isset($_GET['view_all']) && $_GET['view_all'] == '1') ||
            (isset($_GET['view']) && $_GET['view'] === 'all');

// Initialize AI Learning System
$aiLearning = null;
if ($customer_province && $customer_age_group) {
    $aiLearning = initializeAILearning($pdo, $_SESSION['user_id'], $customer_province, $customer_age_group);
}

// Function to assign products to provinces and age groups based on category
function assignProductToProvinceAndAgeGroup($province, $age_group) {
    $provinceMapping = [
        'Limpopo' => ['Electronics', 'Clothing', 'Home & Garden'],
        'Mpumalanga' => ['Electronics', 'Sports', 'Beauty'],
        'North West' => ['Electronics', 'Home & Garden'],
        'Gauteng' => ['Electronics', 'Clothing', 'Beauty', 'Sports'],
        'KwaZulu-Natal' => ['Electronics', 'Home & Garden', 'Sports'],
        'Western Cape' => ['Electronics', 'Clothing', 'Home & Garden'],
        'Eastern Cape' => ['Electronics', 'Home & Garden'],
        'Free State' => ['Electronics', 'Sports', 'Beauty'],
        'Northern Cape' => ['Electronics', 'Home & Garden'],
        // Add more provinces as needed
    ];

    $ageGroupMapping = [
        '18-25' => ['Electronics', 'Clothing', 'Sports', 'Beauty'],
        '26-35' => ['Electronics', 'Home & Garden'],
        '36-45' => ['Electronics', 'Home & Garden', 'Sports'],
        '46-55' => ['Electronics', 'Home & Garden'],
        '56+' => ['Electronics', 'Home & Garden'],
    ];

    $allowedCategories = [];

    if ($province && isset($provinceMapping[$province])) {
        $allowedCategories = array_merge($allowedCategories, $provinceMapping[$province]);
    }

    if ($age_group && isset($ageGroupMapping[$age_group])) {
        $allowedCategories = array_merge($allowedCategories, $ageGroupMapping[$age_group]);
    }

    // Remove duplicates
    $allowedCategories = array_unique($allowedCategories);

    return $allowedCategories;
}

$allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);

// Handle search and category filtering
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);

    // Log the search query for the user
    if (!empty($search) && isset($_SESSION['user_id'])) {
        $log_stmt = $pdo->prepare("INSERT INTO user_search_history (user_id, search_query) VALUES (?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], $search]);
    }
}

if (isset($_GET['category']) && $_GET['category'] !== '') {
    $selected_category = $_GET['category'];
}

if ($search === '' && $selected_category === '') {
    // If no search or category filter, fetch 10 random products for browsing
    $query = "SELECT p.*, pr.discount_percent, pr.promotion_type, pr.is_active as promotion_active
              FROM products p
              LEFT JOIN promotions pr ON p.product_id = pr.product_id
              AND pr.is_active = true
              AND pr.start_date <= CURRENT_DATE
              AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
              WHERE p.stock_quantity > 0";

    $params = [];

    // Apply province and age group filtering unless viewing all
    if (!$view_all && $customer_province && $customer_age_group) {
        // Filter based on category mapping since products table doesn't have province/age_group columns
        $allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);
        if (!empty($allowedCategories)) {
            $placeholders = str_repeat('?,', count($allowedCategories) - 1) . '?';
            $query .= " AND p.category IN ($placeholders)";
            $params = array_merge($params, $allowedCategories);
        } else {
            // If no categories match, show no products
            $query .= " AND 1=0";
        }
    }

    $query .= " ORDER BY RANDOM()";
    if (!$view_all) {
        $query .= " LIMIT 100";
    }
} else {
    // Build the query based on filters - include promotion information
    $query = "SELECT p.*, pr.discount_percent, pr.promotion_type, pr.is_active as promotion_active
              FROM products p
              LEFT JOIN promotions pr ON p.product_id = pr.product_id
              AND pr.is_active = true
              AND pr.start_date <= CURRENT_DATE
              AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
              WHERE 1=1";

    $params = [];

    // Apply province and age group filtering unless viewing all or searching
    if (!$view_all && $customer_province && $customer_age_group && $search === '') {
        // Filter based on category mapping since products table doesn't have province/age_group columns
        $allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);
        if (!empty($allowedCategories)) {
            $placeholders = str_repeat('?,', count($allowedCategories) - 1) . '?';
            $query .= " AND p.category IN ($placeholders)";
            $params = array_merge($params, $allowedCategories);
        } else {
            // If no categories match, show no products
            $query .= " AND 1=0";
        }
    }

    if ($search !== '') {
        $query .= " AND p.name ILIKE ?";
        $params[] = '%' . $search . '%';
    }

    if ($selected_category !== '') {
        $query .= " AND p.category = ?";
        $params[] = $selected_category;
    }

    $query .= " ORDER BY p.created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

$promo_query = "
    SELECT p.*, u.profile_image, u.username, pr.promotion_type, pr.discount_percent
    FROM products p
    LEFT JOIN users u ON p.owner_id = u.user_id
    LEFT JOIN promotions pr ON p.product_id = pr.product_id
    WHERE pr.is_active = true AND p.stock_quantity > 0
    AND pr.start_date <= CURRENT_DATE AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
";

$params_promo = [];

// Apply province and age group filtering unless viewing all or searching
if (!$view_all && $customer_province && $customer_age_group && $search === '') {
    // Filter based on category mapping since products table doesn't have province/age_group columns
    $allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);
    if (!empty($allowedCategories)) {
        $placeholders = str_repeat('?,', count($allowedCategories) - 1) . '?';
        $promo_query .= " AND p.category IN ($placeholders)";
        $params_promo = array_merge($params_promo, $allowedCategories);
    } else {
        // If no categories match, show no products
        $promo_query .= " AND 1=0";
    }
}

$promo_query .= " ORDER BY p.created_at DESC";
if (!$view_all) {
    $promo_query .= " LIMIT 100";
}

$promo_stmt = $pdo->prepare($promo_query);
$promo_stmt->execute($params_promo);
$promotional_products = $promo_stmt->fetchAll();

// AI-Powered Product Recommendations with Learning
function getAIRecommendations($pdo, $user_id, $search_query = '', $province = null, $age_group = null, $view_all = false, $aiLearning = null) {
    // First, check if the user has any search history
    $search_history_stmt = $pdo->prepare("SELECT search_query FROM user_search_history WHERE user_id = ? ORDER BY search_timestamp DESC LIMIT 10");
    $search_history_stmt->execute([$user_id]);
    $search_history = $search_history_stmt->fetchAll(PDO::FETCH_COLUMN);

    // If no search history, return empty array (no recommendations)
    if (empty($search_history)) {
        return [];
    }

    // Get categories that the user has searched for
    $searched_categories = [];
    $search_terms = [];

    foreach ($search_history as $query) {
        $terms = explode(' ', strtolower(trim($query)));
        $search_terms = array_merge($search_terms, $terms);

        // Also check if the search query matches any product categories
        $category_check_stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE LOWER(category) LIKE LOWER(?) AND category IS NOT NULL");
        $category_check_stmt->execute(['%' . trim($query) . '%']);
        $matched_categories = $category_check_stmt->fetchAll(PDO::FETCH_COLUMN);
        $searched_categories = array_merge($searched_categories, $matched_categories);
    }

    $search_terms = array_unique(array_filter($search_terms));
    $searched_categories = array_unique(array_filter($searched_categories));

    // If no valid search terms or categories found, return empty array
    if (empty($search_terms) && empty($searched_categories)) {
        return [];
    }

    // Build the WHERE clause for categories that user has searched
    $category_conditions = [];
    $params = ['user_id' => $user_id];

    if (!empty($searched_categories)) {
        foreach ($searched_categories as $index => $category) {
            $param_name = "cat_{$index}";
            $category_conditions[] = "p.category = :{$param_name}";
            $params[$param_name] = $category;
        }
    }

    // Also include products that match search terms in name or category
    $search_conditions = [];
    if (!empty($search_terms)) {
        foreach ($search_terms as $index => $term) {
            if (strlen($term) >= 2) { // Only use terms with 2+ characters
                $param_name = "term_{$index}";
                $search_conditions[] = "(LOWER(p.name) LIKE :{$param_name} OR LOWER(p.category) LIKE :{$param_name})";
                $params[$param_name] = '%' . $term . '%';
            }
        }
    }

    // Combine category and search conditions
    $all_conditions = array_merge($category_conditions, $search_conditions);
    if (empty($all_conditions)) {
        return [];
    }

    $where_clause = implode(' OR ', $all_conditions);

    $sql = "
        WITH user_searched_products AS (
            -- Get products from categories that user has searched OR products that match search terms
            SELECT DISTINCT p.product_id, p.name, p.price, p.image_url, p.category,
                   CASE
                       WHEN p.category IN (" . (empty($searched_categories) ? "''" : "'" . implode("','", $searched_categories) . "'") . ") THEN 2.0
                       ELSE 1.0
                   END as category_match_score
            FROM products p
            WHERE p.stock_quantity > 0
            AND ($where_clause)
        ),
        user_behavior AS (
            -- Get user's purchases and wishlist items for ranking
            SELECT oi.product_id, 'purchase' as action_type, o.order_date as timestamp
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.customer_id = :user_id AND o.order_date > CURRENT_DATE - INTERVAL '30 days'
            UNION ALL
            SELECT w.product_id, 'wishlist' as action_type, w.added_at as timestamp
            FROM wishlist w
            WHERE w.customer_id = :user_id AND w.added_at > CURRENT_DATE - INTERVAL '30 days'
        ),
        product_popularity AS (
            -- Calculate popularity based on user behavior
            SELECT p.product_id,
                   COUNT(CASE WHEN ub.action_type = 'purchase' THEN 1 END) as purchase_count,
                   COUNT(CASE WHEN ub.action_type = 'wishlist' THEN 1 END) as wishlist_count,
                   (COUNT(CASE WHEN ub.action_type = 'purchase' THEN 1 END) * 0.7 +
                    COUNT(CASE WHEN ub.action_type = 'wishlist' THEN 1 END) * 0.3) as behavior_score
            FROM user_searched_products p
            LEFT JOIN user_behavior ub ON p.product_id = ub.product_id
            GROUP BY p.product_id
        )

        -- Final recommendations: only products from searched categories or matching search terms
        SELECT usp.product_id, usp.name, usp.price, usp.image_url, usp.category,
               usp.category_match_score,
               COALESCE(pp.behavior_score, 0) as behavior_score,
               (usp.category_match_score * 2.0 + COALESCE(pp.behavior_score, 0)) as final_score
        FROM user_searched_products usp
        LEFT JOIN product_popularity pp ON usp.product_id = pp.product_id
        ORDER BY final_score DESC, usp.category_match_score DESC
        LIMIT 5
    ";

    $recommendation_stmt = $pdo->prepare($sql);
    $recommendation_stmt->execute($params);
    return $recommendation_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get AI recommendations
$recommended_products = getAIRecommendations($pdo, $_SESSION['user_id'], $search, $customer_province, $customer_age_group, $view_all);

// No fallback recommendations - if no search history, no recommendations will be shown
// This ensures AI only recommends products that the customer has actually searched for

// AI Search Suggestions (for predictive search)
function getSearchSuggestions($pdo, $query, $province = null, $age_group = null, $view_all = false) {
    if (strlen($query) < 2) return [];

    $suggestion_sql = "
        SELECT name, category,
               SIMILARITY(LOWER(name), LOWER(:query)) as similarity_score
        FROM products
        WHERE (LOWER(name) % LOWER(:query) OR SIMILARITY(LOWER(name), LOWER(:query)) > 0.3)
    ";
    $params_suggestion = ['query' => $query];
    // Don't filter search suggestions by province/age group to allow all products to be suggested
    $suggestion_sql .= " ORDER BY similarity_score DESC, name LIMIT 4";

    $suggestion_stmt = $pdo->prepare($suggestion_sql);
    $suggestion_stmt->execute($params_suggestion);
    return $suggestion_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if we should show promotional modal (first visit after login)
$show_promo_modal = false;
if (!isset($_SESSION['promo_shown']) && count($promotional_products) > 0) {
    $show_promo_modal = true;
    $_SESSION['promo_shown'] = true;
}

// Check if we should show AI recommendation modal (after multiple searches and sufficient time after login)
$show_ai_modal = false;
$search_count = 0;

if (isset($_SESSION['user_id'])) {
    $search_count_stmt = $pdo->prepare("SELECT COUNT(*) as search_count FROM user_search_history WHERE user_id = ?");
    $search_count_stmt->execute([$_SESSION['user_id']]);
    $search_count_result = $search_count_stmt->fetch();
    $search_count = $search_count_result['search_count'];

    // Check time since session started to avoid showing modal immediately after login
    if (!isset($_SESSION['login_time'])) {
        $_SESSION['login_time'] = time(); // Set login time if not already set
    }
    $session_start_time = $_SESSION['login_time'];
    $time_since_session_start = time() - $session_start_time;
    $minutes_since_session_start = $time_since_session_start / 60;

    // Debug: Log the values for troubleshooting
    error_log("AI Modal Debug - Search Count: $search_count, Minutes since session: $minutes_since_session_start, Session start time: $session_start_time, Current time: " . time());

    // Show AI modal only if:
    // 1. At least 20 seconds have passed since login
    // 2. Modal hasn't been shown in this session
    // 3. There are recommended products available (based on search history)
    // 4. Customer has search history (ensures recommendations are based on actual searches)
    if ($time_since_session_start >= 20 && !isset($_SESSION['ai_modal_shown']) && count($recommended_products) > 0 && $search_count > 0) {
        $show_ai_modal = true;
        $_SESSION['ai_modal_shown'] = true;
        error_log("AI Modal will be shown after 20 seconds - based on search history");
    } else {
        error_log("AI Modal conditions not met: seconds=$time_since_session_start, shown=" . (isset($_SESSION['ai_modal_shown']) ? 'yes' : 'no') . ", products=" . count($recommended_products) . ", searches=$search_count");
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome to our Store!</title>
    <!-- <link rel="stylesheet" href="customer_styles.css" /> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="ads_slider.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

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
    font-family: var(--font-family);
    background-color: var(--background-color);
    color: var(--font-color);
    margin: 0;
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    padding-top: 120px; /* to avoid overlap with fixed header */
    padding-bottom: 80px; /* to avoid overlap with fixed footer */
}

/* Header */
.main-header {
    background-color: var(--card-bg);
    box-shadow: var(--card-shadow);
    padding: 1rem 2rem;
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

.header-search {
    margin-left: 20px;
}

.header-search form {
    display: flex;
    align-items: center;
}

.header-search input[type="search"] {
    width: 250px;
    padding: 0.5rem 1rem;
    border: 1px solid #ccc;
    border-radius: 25px 0 0 25px;
    font-size: 1rem;
    outline: none;
}

.header-search button {
    padding: 0.5rem 1rem;
    border: none;
    background-color: var(--primary-color);
    color: white;
    border-radius: 0 25px 25px 0;
    cursor: pointer;
    font-size: 1rem;
    transition: background-color 0.3s ease;
}

.header-search button:hover {
    background-color: #0056b3;
}

/* View Toggle Styles */
.view-toggle {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 5px;
}

.btn-toggle {
    padding: 0.5rem 1rem;
    background-color: var(--primary-color);
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    transition: background-color 0.3s ease;
}

.btn-toggle:hover {
    background-color: #0056b3;
}

.view-status {
    font-size: 0.8rem;
    color: var(--secondary-color);
    font-weight: 500;
}

/* Category Tabs Container */
.category-tabs-container {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: sticky;
    top: 95px;
    z-index: 100;
}

/* Category Tabs */
.category-tabs {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0 1rem;
    gap: 0;
    scrollbar-width: thin;
    scrollbar-color: #6c757d #f8f9fa;
    -ms-overflow-style: -ms-autohiding-scrollbar;
    white-space: nowrap;
}

.category-tabs::-webkit-scrollbar {
    height: 4px;
}

.category-tabs::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 2px;
}

.category-tabs::-webkit-scrollbar-thumb {
    background: #6c757d;
    border-radius: 2px;
}

.category-tabs::-webkit-scrollbar-thumb:hover {
    background: #495057;
}

.category-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: #6c757d;
    font-weight: 600;
    font-size: 0.9rem;
    border-bottom: 3px solid transparent;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    min-width: fit-content;
    position: relative;
    background: transparent;
    border-radius: 8px 8px 0 0;
    margin: 0 2px;
}

.category-tab:hover {
    color: var(--primary-color);
    background: rgba(102, 126, 234, 0.08);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.15);
}

.category-tab.active {
    color: var(--primary-color);
    background: rgba(102, 126, 234, 0.1);
    border-bottom-color: var(--primary-color);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    transform: translateY(-1px);
}

.category-tab.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 50%;
    transform: translateX(-50%);
    width: 80%;
    height: 3px;
    background: var(--primary-color);
    border-radius: 2px 2px 0 0;
}

.category-tab i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.category-tab span {
    font-weight: 600;
    letter-spacing: 0.025em;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 2rem;
}

.product-card {
    background: var(--card-bg);
    border-radius: 10px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.product-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.product-info {
    padding: 1.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.product-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.product-price {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0 0 1rem 0;
}

.product-stock {
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin-bottom: 1rem;
}

.product-info form {
    margin-top: auto;
    display: flex;
    align-items: center;
}

.quantity-input {
    width: 60px;
    padding: 0.5rem;
    text-align: center;
    border: 1px solid #ccc;
    border-radius: 5px;
    margin-right: 10px;
}



/* Wishlist Button */
.btn-wishlist {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 50%;
    color: #6c757d;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-wishlist:hover {
    background-color: #ff6b6b;
    color: white;
    border-color: #ff6b6b;
    transform: scale(1.1);
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

.btn-add-to-cart-footer {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: background-color 0.3s ease;
}

.btn-add-to-cart-footer:hover {
    background-color: #0056b3;
}
.add-2{
        display: none;
}

/* Responsive Styles */
@media (max-width: 1024px) {
    .product-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }

    .header-search input[type="search"] {
        width: 200px;
    }

    .header-search select {
        font-size: 0.9rem;
    }

    .header-search button {
        font-size: 0.9rem;
    }

    .category-tabs-container {
        top: 95px;
    }

    .category-tabs {
        padding: 0 0.5rem;
    }

    .category-tab {
        padding: 0.8rem 1rem;
        font-size: 0.85rem;
        margin: 0 1px;
    }

    .category-tab i {
        font-size: 0.9rem;
    }
    .add-2{
        display: none;
}
}

@media (max-width: 768px) {
    .container {
        padding-top: 140px; /* Increased to account for tabs */
        padding-bottom: 100px;
    }

    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
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

    .header-search select {
        width: 120px;
    }

    .category-tabs-container {
        position: relative;
        top: 130px;
        margin-top: 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .category-tabs {
        padding: 0 0.25rem;
        gap: 0;
    }

    .category-tab {
        padding: 0.7rem 0.8rem;
        font-size: 0.75rem;
        margin: 0 1px;
        flex-shrink: 0;
    }

    .category-tab i {
        margin-right: 0.25rem;
        font-size: 0.8rem;
    }

    .category-tab span {
        font-size: 0.75rem;
    }

    .main-footer {
        flex-direction: column;
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .footer-nav {
        justify-content: center;
    }

    .btn-add-to-cart-footer {
        width: 100%;
        text-align: center;
    }
     .add-1{
        display: none;
}
  .add-2{
        display: block;
}
}

@media (max-width: 480px) {
    .container {
        padding-top: 120px; /* Adjusted for fixed tabs */
        padding-bottom: 120px;
    }

    .product-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .product-card {
        padding: 1rem;
    }

    .product-title {
        font-size: 0.9rem;
    }

    .product-price {
        font-size: 1.1rem;
    }

    .header-search input[type="search"] {
        width: 120px;
        font-size: 0.8rem;
    }

    .header-search select {
        width: 100px;
        font-size: 0.8rem;
    }

    .header-search button {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }

    .category-tabs-container {
        position: relative;
        top: 130px;
        margin-top: 15px; /* Smaller margin for very small screens */
    }

    .category-tabs {
        padding: 0 0.125rem;
    }

    .category-tab {
        padding: 0.6rem 0.5rem;
        font-size: 0.7rem;
        margin: 0 0.5px;
        flex-shrink: 0;
        min-width: 0;
    }

    .category-tab i {
        margin-right: 0.2rem;
        font-size: 0.75rem;
    }

    .category-tab span {
        font-size: 0.7rem;
        letter-spacing: 0.01em;
    }

    .footer-nav a {
        font-size: 1rem;
        padding: 0.4rem;
    }

    .btn-add-to-cart-footer {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
    .add-1{
        display: none;
}
 .add-2{
        display: block;
}
}

    </style>
</head>
<body>
    <header class="main-header">
        <div class="logo">My Store</div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="view-toggle">
                <?php if ($view_all): ?>
                    <a href="product_browse.php" class="btn-toggle">All</a>
                    <span class="view-status">Viewing All Products</span>
                <?php else: ?>
                    <a href="product_browse.php?view=all" class="btn-toggle">Reco..</a>
                    <span class="view-status">Viewing Filtered Products </span>
                <?php endif; ?>
            </div>
            <div class="header-search">
                <form method="GET" action="product_browse.php" style="display: flex; align-items: center; gap: 10px;" autocomplete="off">
                    <?php if ($view_all): ?>
                        <input type="hidden" name="view" value="all" />
                    <?php endif; ?>
                    <div style="position: relative; display: flex; align-items: center; flex-direction: column; width: 100%;">
                        <input type="search" name="search" id="searchInput" placeholder="Search for products..." value="<?= htmlspecialchars($search) ?>"
                               style="padding-right: 40px; width: 250px;" autocomplete="off" />
                        <button type="button" id="voiceSearchBtn" class="voice-search-btn" title="Voice Search" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%);">
                            <i class="fas fa-microphone"></i>
                        </button>
                        <div id="voiceSearchStatus" class="voice-status" style="display: none; position: absolute; right: 40px; top: 50%; transform: translateY(-50%);">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </div>
                        <div id="searchSuggestions" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000; display: none; border-radius: 0 0 5px 5px;"></div>
                    </div>
                    <!-- <select name="category" style="padding: 0.5rem; border: 1px solid #ccc; border-radius: 5px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" <?= $selected_category === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Search</button> -->
                </form>
            </div>
        </div>
    </header>

    <!-- Category Tabs (Only show when viewing all products) -->
    <?php if ($view_all): ?>
    <div class="category-tabs-container">
        <div class="category-tabs">
            <a href="product_browse.php<?= $view_all ? '?view=all' : '' ?>" class="category-tab<?= !$selected_category ? ' active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>All</span>
            </a>
            <?php foreach ($categories as $category): ?>
                <a href="product_browse.php?category=<?= urlencode($category) ?><?= $view_all ? '&view=all' : '' ?>" class="category-tab<?= $selected_category === $category ? ' active' : '' ?>">
                    <span><?= htmlspecialchars($category) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <main class="container">
        <div id="ad-slider" style="margin-bottom: 20px;"></div> <!-- Ad slider container -->

        <?php if ($search === '' && $selected_category === ''): ?>
            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border: 1px solid #bbdefb; border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: center;">
                <h3 style="color: var(--primary-color); margin: 0 0 10px 0; font-size: 1.2rem;">
                    <i class="fas fa-search"></i> Discover Our Products
                </h3>
                <p style="margin: 0; color: #555; font-size: 0.95rem;">
                    We're showing you 100 featured products below. Use the search bar above to find specific items or browse by category.
                    <br><strong>Search for anything you need!</strong>
                </p>
            </div>
        <?php endif; ?>

        <form method="POST" action="cart.php" id="productForm">
            <?php if ($view_all): ?>
                <input type="hidden" name="view_all" value="1" />
            <?php endif; ?>
        <section class="product-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card <?= (!empty($product['discount_percent']) && $product['promotion_active']) ? 'sale-item' : '' ?>">
                        <img src="../images/<?= htmlspecialchars(basename($product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image" />
                        <div class="product-info">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <?php if ($product['discount_percent'] > 0): ?>
                                <p class="product-price">
                                    <span style="text-decoration: line-through; color: #999; margin-right: 10px;">
                                        R <?= number_format($product['price'], 2) ?>
                                    </span>
                                    <span style="color: #ff6b35; font-weight: bold;">
                                        R <?= number_format($product['price'] * (1 - $product['discount_percent'] / 100), 2) ?>
                                    </span>
                                    <span style="color: #28a745; font-size: 0.9rem; margin-left: 5px;">
                                        (<?= $product['discount_percent'] ?>% OFF)
                                    </span>
                                </p>
                            <?php else: ?>
                                <p class="product-price">R <?= number_format($product['price'], 2) ?></p>
                            <?php endif; ?>
                            <p class="product-stock">Stock: <?= intval($product['stock_quantity']) ?></p>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <input type="checkbox" name="product_ids[]" value="<?= $product['product_id'] ?>" />
                                <input type="number" name="quantities[<?= $product['product_id'] ?>]" value="1" min="1" max="<?= intval($product['stock_quantity']) ?>" class="quantity-input" style="width: 60px;">
                                <form method="POST" action="cart.php" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>" />
                                    <input type="hidden" name="quantity" value="1" />
                                    <?php if ($view_all): ?>
                                    <input type="hidden" name="view_all" value="1" />
                                    <?php endif; ?>
                                    <!-- Removed the add to cart button that appears on hover as per user request -->
                                </form>
                                <a href="wishlist.php?add_to_wishlist=<?= $product['product_id'] ?>&view_all=<?= $view_all ? '1' : '0' ?>" class="btn-wishlist" title="Add to Wishlist">
                                    <i class="fas fa-heart"></i>
                                </a>
                                <?php if (!empty($product['model_3d_url'])): ?>
                                    <a href="<?= htmlspecialchars($product['model_3d_url']) ?>" target="_blank" title="View 3D Model" style="margin-left: 10px; color: #333;">
                                        <i class="fa fa-eye" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No products found. Please check back later!</p>
            <?php endif; ?>
        </section>
        </form>
    </main>

    <footer class="main-footer">
        <nav class="footer-nav">
            <a href="product_browse.php" class="active"><i class="fas fa-store"></i></a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="wishlist.php"><i class="fas fa-heart"></i></a>
                <a href="orders.php"><i class="fa-solid fa-bag-shopping"></i></a>
                <a href="cart.php"><i class="fas fa-shopping-cart"></i></a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            <?php else: ?>
                <a href="../unified_login.php"><i class="fas fa-sign-in-alt"></i></a>
                <a href="registration.php"><i class="fas fa-user-plus"></i></a>
            <?php endif; ?>
              <button type="submit" name="add_selected" form="productForm" class="btn-add-to-cart-footer add-1"> <i class="fa-solid fa-plus"></i> Add to Cart</button>
        </nav>
        <button type="submit" name="add_selected" form="productForm" class="btn-add-to-cart-footer add-2"> <i class="fa-solid fa-plus"></i> Add to Cart</button>
    </footer>

    <!-- Promotional Products Modal -->
    <?php if ($show_promo_modal): ?>
    <div id="promoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" style="float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2 style="color: #ff6b35; text-align: center; margin-bottom: 20px;">🔥 Special Promotions Just For You! 🔥</h2>
            
            <div class="promo-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <?php foreach ($promotional_products as $product): ?>
                    <div class="promo-card" style="background: white; border-radius: 10px; padding: 15px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                            <img src="../images/<?= htmlspecialchars(basename($product['profile_image'] ?? 'default_profile.png')) ?>" 
                                 alt="Seller" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                            <div>
                                <div style="font-weight: bold;"><?= htmlspecialchars($product['username'] ?? 'Seller') ?></div>
                                <div class="stars" style="color: #ffd700;">
                                    ⭐⭐⭐⭐⭐
                                </div>
                            </div>
                        </div>
                        
                        <img src="../images/<?= htmlspecialchars(basename($product['image_url'])) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 10px;">
                        
                        <h4 style="margin: 0 0 5px 0; color: #333;">
                            <?= htmlspecialchars($product['name']) ?>
                            <?php if (!empty($product['discount_percent'])): ?>
                                <span style="background: #ff6b35; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                                    On Sale
                                </span>
                            <?php endif; ?>
                        </h4>
                        
                        <!-- Promotion Badge -->
                        <?php if (!empty($product['discount_percent'])): ?>
                            <div style="background: #ff6b35; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem; margin-bottom: 5px; display: inline-block;">
                                🔥 <?= intval($product['discount_percent']) ?>% OFF
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 1.2rem; font-weight: bold; color: #ff6b35;">
                                R <?= number_format($product['price'], 2) ?>
                            </span>
                            <span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.8rem;">
                                <?= intval($product['stock_quantity']) ?> in stock
                            </span>
                        </div>
                        
                        <?php if (!empty($product['category'])): ?>
                            <div style="margin-top: 5px; color: #666; font-size: 0.9rem;">
                                Category: <?= htmlspecialchars($product['category']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($product['promotion_type'])): ?>
                            <div style="margin-top: 5px; color: #ff6b35; font-size: 0.9rem; font-weight: bold;">
                                Promotion: <?= htmlspecialchars(ucfirst($product['promotion_type'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="closePromoModal()" style="background: #ff6b35; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold;">
                    Start Shopping!
                </button>
            </div>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            position: relative;
            margin: auto;
            top: 50%;
            transform: translateY(-50%);
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close:hover {
            color: #ff0000;
        }

        /* Enhanced styling and animation for products on sale */
        .product-card.sale-item {
            position: relative;
            overflow: hidden;
            border: 2px solid #ff6b35;
            animation: pulse-glow 2s infinite ease-in-out;
            transform-style: preserve-3d;
        }

        .product-card.sale-item::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.8) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: rotate(45deg);
            animation: shine-sweep 2s infinite;
            z-index: 1;
        }

        .product-card.sale-item::after {
            content: '🔥 SALE';
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #ff6b35, #ff4500);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 0.8rem;
            z-index: 2;
            animation: badge-pulse 1.5s infinite;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.6);
        }

        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3), 0 0 0 rgba(255, 107, 53, 0);
                transform: translateY(0) scale(1);
            }
            50% {
                box-shadow: 0 8px 25px rgba(255, 107, 53, 0.8), 0 0 30px rgba(255, 107, 53, 0.6);
                transform: translateY(-5px) scale(1.02);
            }
        }

        @keyframes shine-sweep {
            0% {
                transform: rotate(45deg) translateX(-100%) translateY(-100%);
            }
            100% {
                transform: rotate(45deg) translateX(100%) translateY(100%);
            }
        }

        @keyframes badge-pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 4px 15px rgba(255, 107, 53, 0.6);
            }
            50% {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(255, 107, 53, 0.8);
            }
        }

        /* Enhanced hover effect for sale items */
        .product-card.sale-item:hover {
            animation: pulse-glow-hover 1s infinite;
            transform: translateY(-8px) scale(1.05);
        }

        @keyframes pulse-glow-hover {
            0%, 100% {
                box-shadow: 0 8px 30px rgba(255, 107, 53, 0.8), 0 0 40px rgba(255, 107, 53, 0.7);
            }
            50% {
                box-shadow: 0 12px 35px rgba(255, 107, 53, 1), 0 0 50px rgba(255, 107, 53, 0.9);
            }
        }
    </style>

    <script>
        function closePromoModal() {
            document.getElementById('promoModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('promoModal');
            if (event.target === modal) {
                closePromoModal();
            }
        }
        
        // Close modal with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePromoModal();
            }
        });
    </script>
    <?php endif; ?>

    <!-- AI Recommendation Modal -->
    <?php if ($show_ai_modal): ?>
    <div id="aiModal" class="modal" style="display: none;">
        <div class="modal-content ai-modal-content">
            <span class="close ai-close" style="float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>

            <div class="ai-modal-header">
                <div class="ai-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="ai-header-text">
                    <h2>🤖 AI-Powered Recommendations</h2>
                    <p>OUR NEW STOCK</p>
                </div>
            </div>

            <div class="ai-recommendations-grid">
                <?php foreach (array_slice($recommended_products, 0, 5) as $index => $product): ?>
                    <div class="ai-recommendation-card" data-product-id="<?= $product['product_id'] ?>">
                        <div class="ai-recommendation-rank">#<?= $index + 1 ?></div>

                        <div class="ai-product-image">
                            <img src="../images/<?= htmlspecialchars(basename($product['image_url'] ?? 'default_product.jpg')) ?>"
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 onerror="this.src='../images/default_product.jpg'">
                        </div>

                        <div class="ai-product-info">
                            <h4 class="ai-product-title"><?= htmlspecialchars($product['name']) ?></h4>
                            <div class="ai-product-price">
                                <span class="price">R <?= number_format($product['price'], 2) ?></span>
                                <?php if (isset($product['category'])): ?>
                                    <span class="category-badge"><?= htmlspecialchars($product['category']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ai-product-meta">
                                <span class="popularity-score">
                                    <i class="fas fa-fire"></i>
                                    Popular
                                </span>
                            </div>
                        </div>

                        <div class="ai-product-actions">
                            <button class="ai-btn-view" onclick="viewProduct('<?= htmlspecialchars($product['name']) ?>')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <!-- <button class="ai-btn-cart" onclick="addToCartFromModal(<?= $product['product_id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>')">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button> -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ai-modal-footer">
                <div class="ai-stats">
                    <span><i class="fas fa-search"></i> <?= $search_count ?> searches analyzed</span>
                    <span><i class="fas fa-brain"></i> AI-powered recommendations</span>
                </div>
                <div class="ai-actions">
                    <button onclick="closeAIModal()" class="ai-btn-secondary">Maybe Later</button>
                    <button onclick="exploreMore()" class="ai-btn-primary">Explore More</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ai-modal-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0;
            border-radius: 20px;
            position: relative;
            margin: auto;
            top: 50%;
            transform: translateY(-50%);
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .ai-modal-header {
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }

        .ai-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ffd700;
        }

        .ai-header-text h2 {
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .ai-header-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .ai-recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            padding: 30px;
        }

        .ai-recommendation-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            color: #333;
        }

        .ai-recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .ai-recommendation-rank {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #ff6b35, #ff4500);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
            z-index: 2;
        }

        .ai-product-image {
            width: 100%;
            height: 150px;
            overflow: hidden;
        }

        .ai-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .ai-recommendation-card:hover .ai-product-image img {
            transform: scale(1.1);
        }

        .ai-product-info {
            padding: 15px;
        }

        .ai-product-title {
            margin: 0 0 10px 0;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
        }

        .ai-product-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .ai-product-price .price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .category-badge {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .ai-product-meta {
            margin-bottom: 15px;
        }

        .popularity-score {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #ff6b35;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .ai-product-actions {
            display: flex;
            gap: 10px;
            padding: 0 15px 15px 15px;
        }

        .ai-btn-view, .ai-btn-cart {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .ai-btn-view {
            background: #6c757d;
            color: white;
        }

        .ai-btn-view:hover {
            background: #5a6268;
        }

        .ai-btn-cart {
            background: var(--primary-color);
            color: white;
        }

        .ai-btn-cart:hover {
            background: #0056b3;
            transform: scale(1.05);
        }

        .ai-modal-footer {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px 30px;
            border-radius: 0 0 20px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-stats {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
        }

        .ai-stats span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .ai-actions {
            display: flex;
            gap: 10px;
        }

        .ai-btn-secondary, .ai-btn-primary {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .ai-btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .ai-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .ai-btn-primary {
            background: #ffd700;
            color: #333;
        }

        .ai-btn-primary:hover {
            background: #ffed4e;
            transform: scale(1.05);
        }

        .ai-close {
            color: white;
            opacity: 0.7;
        }

        .ai-close:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .ai-recommendations-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .ai-modal-footer {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .ai-stats {
                justify-content: center;
            }
        }
    </style>

    <script>
        function closeAIModal() {
            document.getElementById('aiModal').style.display = 'none';
        }

        function viewProduct(productName) {
            document.getElementById('searchInput').value = productName;
            document.querySelector('.header-search form').submit();
        }

        function addToCartFromModal(productId, productName) {
            fetch('cart.php?add_to_cart=' + productId + '&quantity=1', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'include'
            })
            .then(response => response.text())
            .then(() => {
                alert('Added ' + productName + ' to cart!');
                closeAIModal();
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                alert('Error adding product to cart. Please try again.');
            });
        }

        function exploreMore() {
            closeAIModal();
            // Scroll to products section
            document.querySelector('.product-grid').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const aiModal = document.getElementById('aiModal');
            const promoModal = document.getElementById('promoModal');

            if (event.target === aiModal) {
                closeAIModal();
            }
            if (event.target === promoModal) {
                closePromoModal();
            }
        }

        // Close modal with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAIModal();
                if (document.getElementById('promoModal')) {
                    closePromoModal();
                }
            }
        });
    </script>
    <?php endif; ?>

    <!-- AI Customer Support Chat Widget -->
    <div id="ai-chat-widget" style="display: none;">
        <div id="ai-chat-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div id="ai-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div>
                    <div id="ai-name">AI Assistant</div>
                    <div id="ai-status">Online</div>
                </div>
            </div>
            <button id="ai-chat-close" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="ai-chat-messages"></div>
        <div id="ai-chat-quick-replies" style="display: none; padding: 10px 20px; background: #f8f9fa; border-top: 1px solid #eee; max-height: 120px; overflow-y: auto;">
            <div id="quick-replies-container" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
        </div>
        <div id="ai-chat-input-container">
            <input type="text" id="ai-chat-input" placeholder="Ask me anything about our products..." />
            <button id="ai-chat-send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- AI Chat Toggle Button -->
    <button id="ai-chat-toggle" style="display: none;">
        <i class="fas fa-comments"></i>
        <span>Chat with AI</span>
    </button>

    <style>
        /* AI Chat Widget Styles */
        #ai-chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            z-index: 10000;
            overflow: hidden;
            animation: slideUp 0.3s ease-out;
        }

        #ai-chat-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #ai-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        #ai-name {
            font-weight: 600;
            font-size: 16px;
        }

        #ai-status {
            font-size: 12px;
            opacity: 0.8;
        }

        #ai-chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .ai-message {
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 80%;
            animation: fadeIn 0.3s ease-out;
        }

        .ai-message.user {
            background: var(--primary-color);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }

        .ai-message.bot {
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .ai-message.bot::before {
            content: '🤖';
            margin-right: 8px;
            font-size: 14px;
        }

        #ai-chat-input-container {
            display: flex;
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #eee;
        }

        #ai-chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
        }

        #ai-chat-input:focus {
            border-color: var(--primary-color);
        }

        #ai-chat-send {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            margin-left: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        #ai-chat-send:hover {
            background: #0056b3;
            transform: scale(1.1);
        }

        #ai-chat-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 9999;
            transition: all 0.3s ease;
            animation: bounceIn 0.5s ease-out;
        }

        #ai-chat-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(0,0,0,0.3);
        }

        #ai-chat-toggle.hide {
            display: none;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }
            50% {
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            #ai-chat-widget {
                width: 100vw;
                height: 100vh;
                bottom: 0;
                right: 0;
                left: 0;
                top: 0;
                border-radius: 0;
                margin: 0;
            }

            #ai-chat-toggle {
                bottom: 20px;
                right: 20px;
            }
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            max-width: 80%;
        }

        .typing-indicator::before {
            content: '🤖';
            margin-right: 8px;
            font-size: 14px;
        }

        .typing-dots {
            display: flex;
            gap: 3px;
        }

        .typing-dots span {
            width: 6px;
            height: 6px;
            background: #666;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }

        /* Notification animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Enhanced search suggestions styling */
        .search-suggestion-item:hover .quick-add-btn {
            background: #0056b3 !important;
            transform: scale(1.05);
        }

        .search-suggestion-item:hover .quick-view-btn {
            background: #5a6268 !important;
            transform: scale(1.05);
        }

        .quick-add-btn:hover, .quick-view-btn:hover {
            transform: scale(1.05);
            transition: all 0.2s ease;
        }
    </style>

    <script src="ads_slider.js"></script> <!-- Include the ads slider script -->
    <script>
        // Make recommended products available globally for AI enhancements
        window.recommendedProducts = <?= json_encode($recommended_products) ?>;
    </script>
    <script src="ai_enhancements.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    function fetchNotifications() {
        fetch('fetch_notifications.php', {
            credentials: 'include'
        })
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    let messages = data.notifications.map(n => n.message);
                    alert(messages.join("\\n\\n"));
                    // Mark notifications as read
                    let ids = data.notifications.map(n => n.notification_id);
                    fetch('mark_notifications_read.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        credentials: 'include',
                        body: JSON.stringify({notification_ids: ids})
                    });
                }
            })
            .catch(console.error);
    }
    // Poll every 30 seconds
    setInterval(fetchNotifications, 30000);
    // Initial fetch
    fetchNotifications();

    // Real-time Search Suggestions
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    let searchTimeout;

    // Function to fetch and display search suggestions
    function fetchSearchSuggestions(query) {
        if (query.length < 1) { // Changed from 2 to 1
            searchSuggestions.style.display = 'none';
            return;
        }

        fetch('get_search_suggestions.php?q=' + encodeURIComponent(query), {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            displaySearchSuggestions(data);
        })
        .catch(error => {
            console.error('Error fetching search suggestions:', error);
            searchSuggestions.style.display = 'none';
        });
    }

    // Function to fetch trending/popular products
    function fetchTrendingProducts() {
        fetch('get_search_suggestions.php?trending=1', {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                displayTrendingProducts(data);
            } else {
                searchSuggestions.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching trending products:', error);
            searchSuggestions.style.display = 'none';
        });
    }

    // Function to fetch and display recent search history
    function fetchRecentSearches() {
        fetch('get_search_suggestions.php?recent=1', {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                displayRecentSearches(data);
            } else {
                searchSuggestions.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching recent searches:', error);
            searchSuggestions.style.display = 'none';
        });
    }

    // Global variables for keyboard navigation
    let currentSuggestionIndex = -1;
    let suggestionItems = [];

    // Function to display search suggestions
    function displaySearchSuggestions(suggestions) {
        if (!suggestions || suggestions.length === 0) {
            searchSuggestions.style.display = 'none';
            return;
        }

        searchSuggestions.innerHTML = '';
        suggestionItems = [];
        currentSuggestionIndex = -1;

        suggestions.forEach((suggestion, index) => {
            const suggestionDiv = document.createElement('div');
            suggestionDiv.className = 'search-suggestion-item';
            suggestionDiv.dataset.index = index;
            suggestionDiv.style.cssText = `
                padding: 12px 15px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
                gap: 12px;
                transition: all 0.2s ease;
                position: relative;
            `;

            // Add stock indicator
            const stockIndicator = suggestion.stock_quantity > 0 ?
                '<span style="color: #28a745; font-size: 0.8rem;"><i class="fas fa-check-circle"></i> In Stock</span>' :
                '<span style="color: #dc3545; font-size: 0.8rem;"><i class="fas fa-times-circle"></i> Out of Stock</span>';

            suggestionDiv.innerHTML = `
                <img src="../images/${suggestion.image_url ? suggestion.image_url.split('/').pop() : 'default_product.jpg'}"
                     alt="${suggestion.name}"
                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #eee;"
                     onerror="this.src='../images/default_product.jpg'">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: #333; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${suggestion.name}</div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="font-size: 0.95rem; color: var(--primary-color); font-weight: 600;">R ${parseFloat(suggestion.price).toFixed(2)}</span>
                        ${stockIndicator}
                    </div>
                    <div style="font-size: 0.85rem; color: #666;">${suggestion.category || 'General'}</div>
                    ${suggestion.similarity_score ? `<div style="font-size: 0.75rem; color: #999; margin-top: 2px;">Match: ${(suggestion.similarity_score * 100).toFixed(0)}%</div>` : ''}
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                    <button class="quick-add-btn" data-product-id="${suggestion.product_id}" style="background: var(--primary-color); color: white; border: none; border-radius: 4px; padding: 6px 10px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i class="fas fa-cart-plus"></i>
                        <span>Add</span>
                    </button>
                    <button class="quick-view-btn" data-product-name="${suggestion.name}" style="background: #6c757d; color: white; border: none; border-radius: 4px; padding: 4px 8px; font-size: 0.75rem; cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            `;

            // Add hover effects
            suggestionDiv.addEventListener('mouseenter', () => {
                updateSuggestionHighlight(index);
            });

            // Handle main suggestion click (search for product)
            suggestionDiv.addEventListener('click', (e) => {
                // Don't trigger if clicking on buttons
                if (e.target.closest('.quick-add-btn') || e.target.closest('.quick-view-btn')) {
                    return;
                }
                searchInput.value = suggestion.name;
                searchSuggestions.style.display = 'none';
                document.querySelector('.header-search form').submit();
            });

            // Handle quick add to cart
            const quickAddBtn = suggestionDiv.querySelector('.quick-add-btn');
            quickAddBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                addToCartFromSuggestion(suggestion.product_id, suggestion.name);
            });

            // Handle quick view
            const quickViewBtn = suggestionDiv.querySelector('.quick-view-btn');
            quickViewBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.value = suggestion.name;
                searchSuggestions.style.display = 'none';
                document.querySelector('.header-search form').submit();
            });

            suggestionItems.push(suggestionDiv);
            searchSuggestions.appendChild(suggestionDiv);
        });

        searchSuggestions.style.display = 'block';
    }

    // Function to display trending products
    function displayTrendingProducts(products) {
        if (!products || products.length === 0) {
            searchSuggestions.style.display = 'none';
            return;
        }

        searchSuggestions.innerHTML = '';
        suggestionItems = [];
        currentSuggestionIndex = -1;

        // Add header for trending products
        const headerDiv = document.createElement('div');
        headerDiv.style.cssText = `
            padding: 10px 15px;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        headerDiv.innerHTML = '<i class="fas fa-fire"></i> Trending Products';
        searchSuggestions.appendChild(headerDiv);

        products.slice(0, 6).forEach((product, index) => {
            const productDiv = document.createElement('div');
            productDiv.className = 'search-suggestion-item trending-item';
            productDiv.dataset.index = index;
            productDiv.style.cssText = `
                padding: 12px 15px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
                gap: 12px;
                transition: all 0.2s ease;
                position: relative;
            `;

            // Add trending indicator
            const trendingBadge = '<span style="position: absolute; top: 8px; right: 8px; background: #ff6b35; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; font-weight: bold;">🔥 HOT</span>';

            productDiv.innerHTML = `
                ${trendingBadge}
                <img src="../images/${product.image_url ? product.image_url.split('/').pop() : 'default_product.jpg'}"
                     alt="${product.name}"
                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #eee;"
                     onerror="this.src='../images/default_product.jpg'">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: #333; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${product.name}</div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="font-size: 0.95rem; color: var(--primary-color); font-weight: 600;">R ${parseFloat(product.price).toFixed(2)}</span>
                        <span style="color: #ff6b35; font-size: 0.8rem;"><i class="fas fa-star"></i> Trending</span>
                    </div>
                    <div style="font-size: 0.85rem; color: #666;">${product.category || 'General'}</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                    <button class="quick-add-btn" data-product-id="${product.product_id}" style="background: var(--primary-color); color: white; border: none; border-radius: 4px; padding: 6px 10px; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i class="fas fa-cart-plus"></i>
                        <span>Add</span>
                    </button>
                    <button class="quick-view-btn" data-product-name="${product.name}" style="background: #6c757d; color: white; border: none; border-radius: 4px; padding: 4px 8px; font-size: 0.75rem; cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            `;

            // Add hover effects
            productDiv.addEventListener('mouseenter', () => {
                updateSuggestionHighlight(index);
            });

            // Handle main product click (search for product)
            productDiv.addEventListener('click', (e) => {
                if (e.target.closest('.quick-add-btn') || e.target.closest('.quick-view-btn')) {
                    return;
                }
                searchInput.value = product.name;
                searchSuggestions.style.display = 'none';
                document.querySelector('.header-search form').submit();
            });

            // Handle quick add to cart
            const quickAddBtn = productDiv.querySelector('.quick-add-btn');
            quickAddBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                addToCartFromSuggestion(product.product_id, product.name);
            });

            // Handle quick view
            const quickViewBtn = productDiv.querySelector('.quick-view-btn');
            quickViewBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.value = product.name;
                searchSuggestions.style.display = 'none';
                document.querySelector('.header-search form').submit();
            });

            suggestionItems.push(productDiv);
            searchSuggestions.appendChild(productDiv);
        });

        searchSuggestions.style.display = 'block';
    }

    // Function to update suggestion highlight for keyboard navigation
    function updateSuggestionHighlight(index) {
        // Remove previous highlight
        suggestionItems.forEach((item, i) => {
            if (i === index) {
                item.style.backgroundColor = '#e3f2fd';
                item.style.transform = 'translateX(2px)';
                item.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            } else {
                item.style.backgroundColor = 'transparent';
                item.style.transform = 'translateX(0)';
                item.style.boxShadow = 'none';
            }
        });
        currentSuggestionIndex = index;
    }

    // Function to add product to cart from suggestion
    function addToCartFromSuggestion(productId, productName) {
        fetch('cart.php?add_to_cart=' + productId + '&quantity=1', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'include'
        })
        .then(response => response.text())
        .then(() => {
            // Show success feedback
            showNotification('Added ' + productName + ' to cart!', 'success');
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            showNotification('Error adding product to cart', 'error');
        });
    }

    // Function to show notifications
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            font-weight: 500;
            animation: slideInRight 0.3s ease-out;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Function to display recent search history
    function displayRecentSearches(recentSearches) {
        if (!recentSearches || recentSearches.length === 0) {
            searchSuggestions.style.display = 'none';
            return;
        }

        searchSuggestions.innerHTML = '';

        // Add header for recent searches
        const headerDiv = document.createElement('div');
        headerDiv.style.cssText = `
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        `;
        headerDiv.innerHTML = '<i class="fas fa-history"></i> Recent Searches';
        searchSuggestions.appendChild(headerDiv);

        recentSearches.forEach(search => {
            const searchDiv = document.createElement('div');
            searchDiv.className = 'recent-search-item';
            searchDiv.style.cssText = `
                padding: 12px 15px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: background-color 0.2s ease;
            `;

            searchDiv.innerHTML = `
                <i class="fas fa-search" style="color: #6c757d; font-size: 0.9rem;"></i>
                <div style="flex: 1; font-weight: 500; color: #333;">${search.search_query}</div>
                <i class="fas fa-arrow-right" style="color: #6c757d; font-size: 0.8rem;"></i>
            `;

            searchDiv.addEventListener('click', () => {
                searchInput.value = search.search_query;
                searchSuggestions.style.display = 'none';
                // Auto-submit the form
                document.querySelector('.header-search form').submit();
            });

            searchDiv.addEventListener('mouseenter', () => {
                searchDiv.style.backgroundColor = '#f8f9fa';
            });

            searchDiv.addEventListener('mouseleave', () => {
                searchDiv.style.backgroundColor = 'transparent';
            });

            searchSuggestions.appendChild(searchDiv);
        });

        searchSuggestions.style.display = 'block';
    }

    // Add input event listener for real-time suggestions
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);

        if (query.length >= 1) { // Changed from 2 to 1 for more immediate response
            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(query);
            }, 150); // Reduced debounce from 300ms to 150ms for faster response
        } else {
            // Show trending/popular products when input is empty
            fetchTrendingProducts();
        }
    });

    // Show recent search history when input is focused
    searchInput.addEventListener('focus', function() {
        const query = this.value.trim();
        if (query.length === 0) {
            // Small delay to prevent showing recent searches when user is about to type
            setTimeout(() => {
                if (searchInput.value.trim().length === 0) {
                    fetchRecentSearches();
                }
            }, 100);
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.style.display = 'none';
        }
    });

    // Hide suggestions on escape key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchSuggestions.style.display = 'none';
        }
    });

    // Voice Search Implementation
    const voiceSearchBtn = document.getElementById('voiceSearchBtn');
    const voiceSearchStatus = document.getElementById('voiceSearchStatus');

    // Check for browser support
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';

        voiceSearchBtn.addEventListener('click', () => {
            try {
                voiceSearchStatus.style.display = 'block';
                voiceSearchStatus.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Listening...';
                recognition.start();
            } catch (error) {
                voiceSearchStatus.innerHTML = 'Error: ' + error.message;
                setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 3000);
            }
        });

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            searchInput.value = transcript;
            voiceSearchStatus.innerHTML = '<i class="fas fa-check" style="color: green;"></i>';
            setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 2000);
            
            // Auto-submit the form after voice input
            setTimeout(() => {
                document.querySelector('.header-search form').submit();
            }, 500);
        };

        recognition.onerror = (event) => {
            let errorMessage = 'Error: ';
            switch(event.error) {
                case 'no-speech':
                    errorMessage = 'No speech was detected.';
                    break;
                case 'audio-capture':
                    errorMessage = 'No microphone was found.';
                    break;
                case 'not-allowed':
                    errorMessage = 'Microphone permission denied.';
                    break;
                default:
                    errorMessage = 'Error occurred: ' + event.error;
            }
            voiceSearchStatus.innerHTML = errorMessage;
            setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 3000);
        };

        recognition.onend = () => {
            // Clean up if not already handled
            if (voiceSearchStatus.style.display !== 'none') {
                setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 1000);
            }
        };

    } else {
        voiceSearchBtn.style.display = 'none';
        console.log('Speech recognition not supported in this browser.');
    }

    // Voice command recognition for shopping phrases
    const voiceCommands = {
        'show me': (query) => {
            searchInput.value = query.replace('show me', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'find': (query) => {
            searchInput.value = query.replace('find', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'search for': (query) => {
            searchInput.value = query.replace('search for', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'I want to buy': (query) => {
            searchInput.value = query.replace('I want to buy', '').trim();
            document.querySelector('.header-search form').submit();
        }
    };

    // Process voice commands
    function processVoiceCommand(transcript) {
        const lowerTranscript = transcript.toLowerCase();
        for (const [command, handler] of Object.entries(voiceCommands)) {
            if (lowerTranscript.includes(command)) {
                handler(transcript);
                return true;
            }
        }
        return false;
    }

    // Function to show recommended products popup
    function showRecommendedProducts() {
        const recommendedProducts = <?= json_encode($recommended_products) ?>;
        if (recommendedProducts.length > 0) {
            const popup = document.createElement('div');
            popup.className = 'recommended-popup';
            popup.style.position = 'fixed';
            popup.style.top = '20px';
            popup.style.right = '20px';
            popup.style.backgroundColor = 'white';
            popup.style.border = '1px solid #ccc';
            popup.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            popup.style.padding = '15px';
            popup.style.borderRadius = '8px';
            popup.style.zIndex = '1000';
            popup.style.maxWidth = '300px';

            const title = document.createElement('h4');
            title.innerText = 'Recommended Products';
            title.style.margin = '0 0 10px 0';
            title.style.color = 'var(--primary-color)';
            popup.appendChild(title);

            recommendedProducts.forEach(product => {
                const productDiv = document.createElement('div');
                productDiv.style.display = 'flex';
                productDiv.style.alignItems = 'center';
                productDiv.style.marginBottom = '10px';
                productDiv.style.padding = '10px';
                productDiv.style.borderBottom = '1px solid #eee';
                productDiv.style.cursor = 'pointer';
                productDiv.style.borderRadius = '5px';
                productDiv.style.transition = 'background-color 0.2s ease';

                // Hover effect
                productDiv.onmouseover = () => {
                    productDiv.style.backgroundColor = '#f8f9fa';
                };
                productDiv.onmouseout = () => {
                    productDiv.style.backgroundColor = 'transparent';
                };

                // Click to view product (navigate to product page or search for it)
                productDiv.onclick = () => {
                    // Set the search input to the product name and submit the form
                    document.getElementById('searchInput').value = product.name;
                    document.querySelector('.header-search form').submit();
                };

                const img = document.createElement('img');
                // Ensure we only use the filename part of the image URL
                const imageFilename = product.image_url ? product.image_url.split('/').pop() : 'default_product.jpg';
                img.src = '../images/' + imageFilename;
                img.alt = product.name;
                img.style.width = '50px';
                img.style.height = '50px';
                img.style.objectFit = 'cover';
                img.style.marginRight = '10px';
                img.style.borderRadius = '4px';
                // Add error handling for broken images
                img.onerror = function() {
                    this.src = '../images/default_product.jpg';
                };

                const productInfo = document.createElement('div');
                productInfo.style.flex = '1';
                productInfo.innerHTML = `
                    <strong style="font-size: 0.9rem;">${product.name}</strong><br>
                    <span style="color: var(--primary-color); font-weight: bold;">R ${parseFloat(product.price).toFixed(2)}</span>
                `;
                productInfo.style.fontSize = '0.8rem';

                // Add to cart button
                const addToCartBtn = document.createElement('button');
                addToCartBtn.innerHTML = '<i class="fas fa-cart-plus"></i>';
                addToCartBtn.style.background = 'var(--primary-color)';
                addToCartBtn.style.color = 'white';
                addToCartBtn.style.border = 'none';
                addToCartBtn.style.borderRadius = '4px';
                addToCartBtn.style.padding = '5px 8px';
                addToCartBtn.style.cursor = 'pointer';
                addToCartBtn.style.marginLeft = '10px';
                addToCartBtn.title = 'Add to Cart';
                
                // Prevent event bubbling to avoid triggering the productDiv click
                addToCartBtn.onclick = (e) => {
                    e.stopPropagation();
                    // Add product to cart via AJAX
                    fetch('cart.php?add_to_cart=' + product.product_id + '&quantity=1', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        credentials: 'include'
                    })
                    .then(response => response.text())
                    .then(() => {
                        alert('Added ' + product.name + ' to cart!');
                    })
                    .catch(error => {
                        console.error('Error adding to cart:', error);
                    });
                };

                productDiv.appendChild(img);
                productDiv.appendChild(productInfo);
                productDiv.appendChild(addToCartBtn);
                popup.appendChild(productDiv);
            });

            const closeBtn = document.createElement('button');
            closeBtn.innerText = 'Close';
            closeBtn.style.marginTop = '10px';
            closeBtn.style.padding = '5px 10px';
            closeBtn.style.backgroundColor = 'var(--primary-color)';
            closeBtn.style.color = 'white';
            closeBtn.style.border = 'none';
            closeBtn.style.borderRadius = '4px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.onclick = () => popup.remove();
            popup.appendChild(closeBtn);

            document.body.appendChild(popup);

            // Automatically close the popup after 10 seconds
            setTimeout(() => {
                if (document.body.contains(popup)) {
                    popup.remove();
                }
            }, 10000);
        }
    }

    // Show recommendations every 2 minutes
    setInterval(showRecommendedProducts, 120000);
    
    // Show immediately on page load
    setTimeout(showRecommendedProducts, 90000);
    
    // Show promotional modal after 90 seconds if there are promotional products
    <?php if ($show_promo_modal): ?>
    setTimeout(function() {
        document.getElementById('promoModal').style.display = 'block';
    }, 90000); // 90 seconds
    <?php endif; ?>

    // Show AI recommendation modal after 60 seconds if user has made multiple searches
    <?php if ($show_ai_modal): ?>
    setTimeout(function() {
        document.getElementById('aiModal').style.display = 'block';
    }, 20000); // 20 seconds
    <?php endif; ?>

    // AI Customer Support Chat Functionality
    let chatWidget = document.getElementById('ai-chat-widget');
    let chatToggle = document.getElementById('ai-chat-toggle');
    let chatMessages = document.getElementById('ai-chat-messages');
    let chatInput = document.getElementById('ai-chat-input');
    let chatSend = document.getElementById('ai-chat-send');
    let chatClose = document.getElementById('ai-chat-close');

    // Show chat toggle button immediately for testing
    chatToggle.style.display = 'flex';

    // Toggle chat widget
    chatToggle.addEventListener('click', () => {
        if (chatWidget.style.display === 'none' || chatWidget.style.display === '') {
            chatWidget.style.display = 'flex';
            chatToggle.classList.add('hide');
            // Send welcome message if this is the first time opening
            if (chatMessages.children.length === 0) {
                addMessage("Hello! I'm your AI shopping assistant. I'm here to help you find the perfect products, answer questions about our sales, and provide personalized recommendations. What can I help you with today?", 'bot');
                // Fetch initial quick replies
                setTimeout(() => fetchQuickReplies(''), 1000);
            }
        }
    });

    // Close chat widget
    chatClose.addEventListener('click', () => {
        chatWidget.style.display = 'none';
        chatToggle.classList.remove('hide');
    });

    // Send message function
    function sendMessage(message = null) {
        const msg = message || chatInput.value.trim();
        if (msg === '') return;

        if (!message) { // Only add user message if not from quick reply
            addMessage(msg, 'user');
            chatInput.value = '';
        }

        // Hide quick replies after sending
        hideQuickReplies();

        // Show typing indicator
        showTypingIndicator();

        // Send message to AI backend
        fetch('ai_customer_support.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({ message: msg })
        })
        .then(response => response.json())
        .then(data => {
            hideTypingIndicator();
            if (data.error) {
                addMessage("I'm sorry, I encountered an error. Please try again.", 'bot');
            } else {
                addMessage(data.response, 'bot');
                // Fetch quick replies based on the bot's response
                fetchQuickReplies(data.response);
            }
        })
        .catch(error => {
            hideTypingIndicator();
            console.error('Error:', error);
            addMessage("I'm sorry, I'm having trouble connecting right now. Please try again in a moment.", 'bot');
        });
    }

    // Send message on button click
    chatSend.addEventListener('click', sendMessage);

    // Send message on Enter key
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    // Add message to chat
    function addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-message ${type}`;
        messageDiv.innerHTML = text; // Changed from textContent to innerHTML to render HTML tags
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Show typing indicator
    function showTypingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'typing-indicator';
        indicator.id = 'typing-indicator';
        indicator.innerHTML = `
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        chatMessages.appendChild(indicator);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Hide typing indicator
    function hideTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    // Auto-scroll to bottom when new messages are added
    const observer = new MutationObserver(() => {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
    observer.observe(chatMessages, { childList: true });

    // Quick Replies Functions
    function fetchQuickReplies(userInput = '') {
        const input = userInput || chatInput.value.trim();

        fetch('chat_quick_replies.php?input=' + encodeURIComponent(input), {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.quick_replies && data.quick_replies.length > 0) {
                displayQuickReplies(data.quick_replies);
            } else {
                hideQuickReplies();
            }
        })
        .catch(error => {
            console.error('Error fetching quick replies:', error);
            hideQuickReplies();
        });
    }

    function displayQuickReplies(replies) {
        const container = document.getElementById('quick-replies-container');
        container.innerHTML = '';

        replies.slice(0, 6).forEach(reply => { // Limit to 6 replies
            const button = document.createElement('button');
            button.className = 'quick-reply-btn';
            button.textContent = reply.text;
            button.style.cssText = `
                background: #e9ecef;
                border: 1px solid #dee2e6;
                border-radius: 15px;
                padding: 6px 12px;
                margin: 2px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
                white-space: nowrap;
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
            `;

            button.onmouseover = () => {
                button.style.background = 'var(--primary-color)';
                button.style.color = 'white';
                button.style.borderColor = 'var(--primary-color)';
            };

            button.onmouseout = () => {
                button.style.background = '#e9ecef';
                button.style.color = 'black';
                button.style.borderColor = '#dee2e6';
            };

            button.onclick = () => {
                // Track usage
                fetch('chat_quick_replies.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ reply_id: reply.id, used: true })
                }).catch(console.error);

                // Send the quick reply
                addMessage(reply.text, 'user');
                sendMessage(reply.text);
            };

            container.appendChild(button);
        });

        document.getElementById('ai-chat-quick-replies').style.display = 'block';
    }

    function hideQuickReplies() {
        document.getElementById('ai-chat-quick-replies').style.display = 'none';
        document.getElementById('quick-replies-container').innerHTML = '';
    }

    // Fetch quick replies when user types
    let quickReplyTimeout;
    chatInput.addEventListener('input', () => {
        clearTimeout(quickReplyTimeout);
        const input = chatInput.value.trim();
        if (input.length > 2) {
            quickReplyTimeout = setTimeout(() => {
                fetchQuickReplies(input);
            }, 500);
        } else {
            hideQuickReplies();
        }
    });

    // Hide quick replies when input is focused
    chatInput.addEventListener('focus', () => {
        if (chatInput.value.trim().length <= 2) {
            hideQuickReplies();
        }
    });
});
</script>
</body>
</html>
