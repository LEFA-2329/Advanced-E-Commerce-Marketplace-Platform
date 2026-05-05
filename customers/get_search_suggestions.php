<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

// Get customer province and age for filtering
$customer_stmt = $pdo->prepare("SELECT gender, age, province FROM customers WHERE customer_id = ?");
$customer_stmt->execute([$_SESSION['user_id']]);
$customer = $customer_stmt->fetch();
$customer_province = $customer['province'] ?? null;
$customer_age = $customer['age'] ?? null;

// Define age group
function getAgeGroup($age) {
    if ($age === null) return null;
    if ($age >= 18 && $age <= 25) return '18-25';
    if ($age >= 26 && $age <= 35) return '26-35';
    if ($age >= 36 && $age <= 45) return '36-45';
    if ($age >= 46 && $age <= 55) return '46-55';
    if ($age >= 56) return '56+';
    return null;
}
$customer_age_group = getAgeGroup($customer_age);

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

// Function to analyze search query for better understanding
function analyzeSearchQuery($query) {
    $query = strtolower(trim($query));
    $analysis = [
        'is_partial' => strlen($query) < 3,
        'starts_with_number' => is_numeric(substr($query, 0, 1)),
        'contains_spaces' => strpos($query, ' ') !== false,
        'word_count' => str_word_count($query),
        'likely_category' => false,
        'likely_brand' => false
    ];

    // Check if it might be a category
    $categories = ['electronics', 'clothing', 'sports', 'beauty', 'home', 'garden', 'phone', 'laptop', 'computer'];
    foreach ($categories as $category) {
        if (strpos($query, $category) !== false) {
            $analysis['likely_category'] = $category;
            break;
        }
    }

    return $analysis;
}

// Function to get user's search history for personalized suggestions
function getUserSearchHistory($pdo, $userId, $currentQuery) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT search_query, COUNT(*) as frequency
            FROM user_search_history
            WHERE user_id = ?
            AND search_timestamp > CURRENT_DATE - INTERVAL '30 days'
            AND LOWER(search_query) LIKE LOWER(?)
            GROUP BY search_query
            ORDER BY frequency DESC, search_timestamp DESC
            LIMIT 3
        ");
        $stmt->execute([$userId, $currentQuery . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Check if viewing all
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

// Check if requesting recent searches
$recent = isset($_GET['recent']) && $_GET['recent'] == '1';

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (!$recent && strlen($query) < 1) {
    exit(json_encode([]));
}

// Handle recent searches request
if ($recent) {
    try {
        $historyStmt = $pdo->prepare("
            SELECT DISTINCT search_query, search_timestamp
            FROM user_search_history
            WHERE user_id = :user_id
            AND search_timestamp > CURRENT_DATE - INTERVAL '30 days'
            ORDER BY search_timestamp DESC
            LIMIT 8
        ");
        $historyStmt->execute(['user_id' => $_SESSION['user_id']]);
        $recentSearches = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($recentSearches);
        exit;
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database error']);
        exit;
    }
}

// Enhanced AI-Powered Search Suggestions with Learning
try {
    // First, analyze the query to understand what the user might be looking for
    $queryAnalysis = analyzeSearchQuery($query);

    // Get user's search history for personalized suggestions
    $userSearchHistory = getUserSearchHistory($pdo, $_SESSION['user_id'], $query);

    // Check if PostgreSQL similarity extension is available
    $checkExtension = $pdo->query("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm')");
    $hasSimilarity = $checkExtension->fetchColumn();

    $suggestions = [];

    if ($hasSimilarity) {
        // Enhanced PostgreSQL similarity search with learning
        $sql = "
            SELECT DISTINCT p.name, p.category, p.price, p.image_url, p.product_id,
                   SIMILARITY(LOWER(p.name), LOWER(:query)) as similarity_score,
                   CASE
                       WHEN LOWER(p.name) LIKE LOWER(:exact_match) THEN 3.0
                       WHEN LOWER(p.name) LIKE LOWER(:starts_with) THEN 2.5
                       WHEN LOWER(p.category) LIKE LOWER(:category_match) THEN 2.0
                       ELSE SIMILARITY(LOWER(p.name), LOWER(:query))
                   END as enhanced_score,
                   p.stock_quantity
            FROM products p
            WHERE (
                LOWER(p.name) % LOWER(:query)
                OR SIMILARITY(LOWER(p.name), LOWER(:query)) > 0.2
                OR LOWER(p.name) LIKE LOWER(:starts_with)
                OR LOWER(p.category) LIKE LOWER(:category_match)
            )
            AND p.stock_quantity > 0
        ";

        $params = [
            'query' => $query,
            'exact_match' => $query . '%',
            'starts_with' => $query . '%',
            'category_match' => '%' . $query . '%'
        ];

        if (!$view_all && $customer_province && $customer_age_group) {
            // Use the corrected category mapping
            $allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);
            if (!empty($allowedCategories)) {
                $placeholders = str_repeat('?,', count($allowedCategories) - 1) . '?';
                $sql .= " AND p.category IN ($placeholders)";
                $params = array_merge($params, $allowedCategories);
            }
        }

        $sql .= " ORDER BY enhanced_score DESC, similarity_score DESC, p.name LIMIT 15";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Enhanced fallback search with learning
        $sql = "
            SELECT DISTINCT p.name, p.category, p.price, p.image_url, p.product_id,
                   CASE
                       WHEN LOWER(p.name) = LOWER(:exact_query) THEN 3.0
                       WHEN LOWER(p.name) LIKE LOWER(:starts_with) THEN 2.5
                       WHEN LOWER(p.category) LIKE LOWER(:category_like) THEN 2.0
                       ELSE 1.0
                   END as enhanced_score,
                   p.stock_quantity
            FROM products p
            WHERE (
                LOWER(p.name) LIKE LOWER(:name_like)
                OR LOWER(p.category) LIKE LOWER(:category_like)
            )
            AND p.stock_quantity > 0
        ";

        $params = [
            'exact_query' => $query,
            'starts_with' => $query . '%',
            'name_like' => '%' . $query . '%',
            'category_like' => '%' . $query . '%'
        ];

        if (!$view_all && $customer_province && $customer_age_group) {
            // Use the corrected category mapping
            $allowedCategories = assignProductToProvinceAndAgeGroup($customer_province, $customer_age_group);
            if (!empty($allowedCategories)) {
                $placeholders = str_repeat('?,', count($allowedCategories) - 1) . '?';
                $sql .= " AND p.category IN ($placeholders)";
                $params = array_merge($params, $allowedCategories);
            }
        }

        $sql .= " ORDER BY enhanced_score DESC, p.name LIMIT 15";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If we have few results, add some popular products
    if (count($suggestions) < 5) {
        $popular_sql = "
            SELECT p.name, p.category, 0.8 as similarity_score
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            GROUP BY p.product_id, p.name, p.category
        ";
        $params_popular = [];
        if (!$view_all && $customer_province && $customer_age_group) {
            $popular_sql .= " HAVING p.province = ? AND p.age_group = ?";
            $params_popular[] = $customer_province;
            $params_popular[] = $customer_age_group;
        }
        $popular_sql .= " ORDER BY COUNT(oi.order_item_id) DESC LIMIT 5";
        $popularStmt = $pdo->prepare($popular_sql);
        $popularStmt->execute($params_popular);
        $popularProducts = $popularStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge and deduplicate
        $allSuggestions = array_merge($suggestions, $popularProducts);
        $uniqueSuggestions = [];
        $seenNames = [];
        
        foreach ($allSuggestions as $suggestion) {
            if (!in_array($suggestion['name'], $seenNames)) {
                $uniqueSuggestions[] = $suggestion;
                $seenNames[] = $suggestion['name'];
            }
        }
        
        $suggestions = array_slice($uniqueSuggestions, 0, 10);
    }
    
    // Add AI-generated alternative suggestions based on semantic meaning
    $semanticSuggestions = generateSemanticSuggestions($query);
    if (!empty($semanticSuggestions)) {
        $suggestions = array_merge($suggestions, $semanticSuggestions);
        $suggestions = array_slice($suggestions, 0, 15); // Limit to 15 total
    }
    
    // Track user search behavior
    analyzeSearchPattern($pdo, $_SESSION['user_id'], $query);
    
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error']);
}

// AI function to generate semantic suggestions
function generateSemanticSuggestions($query) {
    $query = strtolower($query);
    $semanticMap = [
        'phone' => ['smartphone', 'mobile', 'cellphone', 'android', 'iphone'],
        'laptop' => ['notebook', 'computer', 'macbook', 'windows', 'chromebook'],
        'shirt' => ['t-shirt', 'blouse', 'top', 'tee', 'polo'],
        'shoe' => ['sneaker', 'boot', 'sandals', 'footwear', 'loafer'],
        'book' => ['novel', 'textbook', 'reading', 'literature', 'ebook'],
        'watch' => ['timepiece', 'wristwatch', 'chronometer', 'smartwatch'],
        'bag' => ['backpack', 'purse', 'handbag', 'tote', 'satchel'],
        'camera' => ['dslr', 'mirrorless', 'digital camera', 'photography'],
        'headphone' => ['earphone', 'earbud', 'headset', 'audio', 'wireless'],
        'game' => ['video game', 'console', 'playstation', 'xbox', 'nintendo']
    ];
    
    $suggestions = [];
    foreach ($semanticMap as $key => $related) {
        if (strpos($query, $key) !== false) {
            foreach ($related as $term) {
                $suggestions[] = [
                    'name' => ucfirst($term),
                    'category' => 'Related Search',
                    'similarity_score' => 0.7
                ];
            }
            break;
        }
    }
    
    return $suggestions;
}

// Function to analyze search patterns and provide intelligent suggestions
function analyzeSearchPattern($pdo, $userId, $query) {
    // Track user search behavior for future AI improvements
    $trackStmt = $pdo->prepare("
        INSERT INTO user_search_history (user_id, search_query, search_timestamp)
        VALUES (:user_id, :query, NOW())
    ");
    $trackStmt->execute(['user_id' => $userId, 'query' => $query]);
            }
    
    // Get user's previous successful searches
    $historyStmt = $pdo->prepare("
        SELECT DISTINCT search_query 
        FROM user_search_history 
        WHERE user_id = :user_id 
        AND search_timestamp > CURRENT_DATE - INTERVAL '30 days'
        ORDER BY search_timestamp DESC 
        LIMIT 5
    ");
    $historyStmt->execute(['user_id' => $userId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $history;


?>
