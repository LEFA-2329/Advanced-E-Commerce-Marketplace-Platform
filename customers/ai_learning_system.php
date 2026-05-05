<?php
require_once '../db_connection.php';

// AI Learning System for Individual User Category Preferences
class AILearningSystem {

    private $pdo;
    private $user_id;
    private $province;
    private $age_group;

    public function __construct($pdo, $user_id, $province, $age_group) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->province = $province;
        $this->age_group = $age_group;
    }

    // Track user interactions for learning
    public function trackInteraction($action_type, $product_id, $category, $search_term = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_behavior_tracking
                (user_id, action_type, product_id, category, search_term, province, age_group, timestamp)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $this->user_id,
                $action_type,
                $product_id,
                $category,
                $search_term,
                $this->province,
                $this->age_group
            ]);

            // Update user's category preferences
            $this->updateUserCategoryPreference($category, $action_type);
        } catch (Exception $e) {
            error_log("Error tracking interaction: " . $e->getMessage());
        }
    }

    // Update user's category preference based on interaction
    private function updateUserCategoryPreference($category, $action_type) {
        $weight = $this->getActionWeight($action_type);

        $stmt = $this->pdo->prepare("
            INSERT INTO user_category_preferences
            (user_id, category, preference_score, last_updated)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (user_id, category)
            DO UPDATE SET
                preference_score = user_category_preferences.preference_score + ?,
                last_updated = CURRENT_TIMESTAMP
        ");

        $stmt->execute([$this->user_id, $category, $weight, $weight]);
    }

    private function getActionWeight($action_type) {
        switch ($action_type) {
            case 'purchase': return 5;
            case 'search': return 3;
            case 'wishlist': return 2;
            case 'view': return 1;
            default: return 1;
        }
    }

    // Learn from user behavior patterns
    public function learnFromBehavior() {
        // Analyze purchase patterns by province and age group
        $this->analyzePurchasePatterns();

        // Analyze search patterns
        $this->analyzeSearchPatterns();

        // Analyze browsing patterns
        $this->analyzeBrowsingPatterns();

        // Update recommendation weights
        $this->updateRecommendationWeights();
    }

    private function analyzePurchasePatterns() {
        // Get purchase patterns for this province and age group
        $stmt = $this->pdo->prepare("
            SELECT
                p.category,
                COUNT(*) as purchase_count,
                AVG(oi.quantity) as avg_quantity,
                AVG(oi.unit_price) as avg_price
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            JOIN customers c ON o.customer_id = c.customer_id
            WHERE c.province = ? AND c.age BETWEEN ? AND ?
            AND o.order_date > CURRENT_DATE - INTERVAL '90 days'
            GROUP BY p.category
            ORDER BY purchase_count DESC
        ");

        $age_range = $this->getAgeRange($this->age_group);
        $stmt->execute([$this->province, $age_range['min'], $age_range['max']]);
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Store learned patterns
        foreach ($patterns as $pattern) {
            $this->storeLearnedPattern('purchase', $pattern['category'], $pattern);
        }
    }

    private function analyzeSearchPatterns() {
        // Get search patterns for this demographic
        $stmt = $this->pdo->prepare("
            SELECT
                ush.search_query,
                COUNT(*) as search_count,
                p.category
            FROM user_search_history ush
            LEFT JOIN products p ON LOWER(p.name) LIKE LOWER('%' || ush.search_query || '%')
            JOIN customers c ON ush.user_id = c.customer_id
            WHERE c.province = ? AND c.age BETWEEN ? AND ?
            AND ush.search_timestamp > CURRENT_DATE - INTERVAL '30 days'
            GROUP BY ush.search_query, p.category
            ORDER BY search_count DESC
            LIMIT 20
        ");

        $age_range = $this->getAgeRange($this->age_group);
        $stmt->execute([$this->province, $age_range['min'], $age_range['max']]);
        $search_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($search_patterns as $pattern) {
            $this->storeLearnedPattern('search', $pattern['category'], $pattern);
        }
    }

    private function analyzeBrowsingPatterns() {
        // Get browsing patterns (wishlist additions, product views)
        $stmt = $this->pdo->prepare("
            SELECT
                p.category,
                COUNT(w.wishlist_id) as wishlist_count,
                COUNT(DISTINCT w.customer_id) as unique_users
            FROM wishlist w
            JOIN products p ON w.product_id = p.product_id
            JOIN customers c ON w.customer_id = c.customer_id
            WHERE c.province = ? AND c.age BETWEEN ? AND ?
            AND w.added_at > CURRENT_DATE - INTERVAL '30 days'
            GROUP BY p.category
            ORDER BY wishlist_count DESC
        ");

        $age_range = $this->getAgeRange($this->age_group);
        $stmt->execute([$this->province, $age_range['min'], $age_range['max']]);
        $browsing_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($browsing_patterns as $pattern) {
            $this->storeLearnedPattern('browsing', $pattern['category'], $pattern);
        }
    }

    private function storeLearnedPattern($pattern_type, $category, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO learned_patterns
            (province, age_group, pattern_type, category, pattern_data, learned_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (province, age_group, pattern_type, category)
            DO UPDATE SET
                pattern_data = EXCLUDED.pattern_data,
                learned_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            $this->province,
            $this->age_group,
            $pattern_type,
            $category,
            json_encode($data)
        ]);
    }

    private function updateRecommendationWeights() {
        // Calculate recommendation weights based on learned patterns
        $stmt = $this->pdo->prepare("
            SELECT
                category,
                pattern_type,
                pattern_data
            FROM learned_patterns
            WHERE province = ? AND age_group = ?
            ORDER BY learned_at DESC
        ");

        $stmt->execute([$this->province, $this->age_group]);
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $weights = [];
        foreach ($patterns as $pattern) {
            $data = json_decode($pattern['pattern_data'], true);
            $weight = $this->calculateWeight($pattern['pattern_type'], $data);
            $weights[$pattern['category']] = ($weights[$pattern['category']] ?? 0) + $weight;
        }

        // Store updated weights
        foreach ($weights as $category => $weight) {
            $this->storeRecommendationWeight($category, $weight);
        }
    }

    private function calculateWeight($pattern_type, $data) {
        switch ($pattern_type) {
            case 'purchase':
                return ($data['purchase_count'] ?? 0) * 3 + ($data['avg_quantity'] ?? 0) * 2;
            case 'search':
                return ($data['search_count'] ?? 0) * 1.5;
            case 'browsing':
                return ($data['wishlist_count'] ?? 0) * 2;
            default:
                return 1;
        }
    }

    private function storeRecommendationWeight($category, $weight) {
        $stmt = $this->pdo->prepare("
            INSERT INTO recommendation_weights
            (province, age_group, category, weight, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (province, age_group, category)
            DO UPDATE SET
                weight = EXCLUDED.weight,
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([$this->province, $this->age_group, $category, $weight]);
    }

    // Get personalized recommendations based on learned patterns
    public function getPersonalizedRecommendations($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                COALESCE(rw.weight, 1) as recommendation_weight,
                CASE
                    WHEN lp.pattern_type = 'purchase' THEN 2.0
                    WHEN lp.pattern_type = 'search' THEN 1.5
                    WHEN lp.pattern_type = 'browsing' THEN 1.8
                    ELSE 1.0
                END as pattern_boost
            FROM products p
            LEFT JOIN recommendation_weights rw ON p.category = rw.category
                AND rw.province = ? AND rw.age_group = ?
            LEFT JOIN learned_patterns lp ON p.category = lp.category
                AND lp.province = ? AND lp.age_group = ?
            WHERE p.stock_quantity > 0
            ORDER BY (COALESCE(rw.weight, 1) * COALESCE(lp.pattern_boost, 1)) DESC
            LIMIT ?
        ");

        $stmt->execute([
            $this->province,
            $this->age_group,
            $this->province,
            $this->age_group,
            $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get trending products for this demographic
    public function getTrendingProducts($limit = 8) {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                COUNT(oi.order_item_id) as recent_sales,
                AVG(oi.unit_price) as avg_sale_price,
                rw.weight as demographic_weight
            FROM products p
            LEFT JOIN order_items oi ON p.product_id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.order_id
            LEFT JOIN customers c ON o.customer_id = c.customer_id
            LEFT JOIN recommendation_weights rw ON p.category = rw.category
                AND rw.province = ? AND rw.age_group = ?
            WHERE p.stock_quantity > 0
            AND o.order_date > CURRENT_DATE - INTERVAL '7 days'
            AND c.province = ? AND c.age BETWEEN ? AND ?
            GROUP BY p.product_id, p.name, p.price, p.image_url, p.category, rw.weight
            ORDER BY (COUNT(oi.order_item_id) * COALESCE(rw.weight, 1)) DESC
            LIMIT ?
        ");

        $age_range = $this->getAgeRange($this->age_group);
        $stmt->execute([
            $this->province,
            $this->age_group,
            $this->province,
            $age_range['min'],
            $age_range['max'],
            $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Predict user preferences based on learned patterns
    public function predictPreferences($product_category) {
        $stmt = $this->pdo->prepare("
            SELECT
                pattern_data,
                pattern_type
            FROM learned_patterns
            WHERE province = ? AND age_group = ? AND category = ?
            ORDER BY learned_at DESC
            LIMIT 5
        ");

        $stmt->execute([$this->province, $this->age_group, $product_category]);
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $prediction_score = 0;
        foreach ($patterns as $pattern) {
            $data = json_decode($pattern['pattern_data'], true);
            $prediction_score += $this->calculateWeight($pattern['pattern_type'], $data);
        }

        return $prediction_score;
    }

    private function getAgeRange($age_group) {
        switch ($age_group) {
            case '18-25': return ['min' => 18, 'max' => 25];
            case '26-35': return ['min' => 26, 'max' => 35];
            case '36-45': return ['min' => 36, 'max' => 45];
            case '46-55': return ['min' => 46, 'max' => 55];
            case '56+': return ['min' => 56, 'max' => 120];
            default: return ['min' => 18, 'max' => 120];
        }
    }

    // Adaptive filtering based on learned patterns
    public function getAdaptiveFilters() {
        $stmt = $this->pdo->prepare("
            SELECT
                category,
                AVG(CASE WHEN pattern_type = 'purchase' THEN
                    CAST(pattern_data->>'purchase_count' AS INTEGER) ELSE 0 END) as avg_purchases,
                AVG(CASE WHEN pattern_type = 'search' THEN
                    CAST(pattern_data->>'search_count' AS INTEGER) ELSE 0 END) as avg_searches
            FROM learned_patterns
            WHERE province = ? AND age_group = ?
            GROUP BY category
            HAVING AVG(CASE WHEN pattern_type = 'purchase' THEN
                CAST(pattern_data->>'purchase_count' AS INTEGER) ELSE 0 END) > 0
            ORDER BY avg_purchases DESC
            LIMIT 5
        ");

        $stmt->execute([$this->province, $this->age_group]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user's learned category preferences
    public function getUserCategoryPreferences() {
        $stmt = $this->pdo->prepare("
            SELECT category, preference_score
            FROM user_category_preferences
            WHERE user_id = ?
            ORDER BY preference_score DESC, last_updated DESC
            LIMIT 5
        ");

        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get combined categories (user preferences + province/age group)
    public function getCombinedCategories() {
        $userPrefs = $this->getUserCategoryPreferences();
        $provinceCategories = $this->assignProductToProvinceAndAgeGroup($this->province, $this->age_group);

        $combinedCategories = [];

        // Add user preferred categories with higher priority
        foreach ($userPrefs as $pref) {
            $combinedCategories[] = $pref['category'];
        }

        // Add province/age group categories
        foreach ($provinceCategories as $category) {
            if (!in_array($category, $combinedCategories)) {
                $combinedCategories[] = $category;
            }
        }

        return $combinedCategories;
    }

    // Helper function to assign products to provinces and age groups
    private function assignProductToProvinceAndAgeGroup($province, $age_group) {
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
}

// Initialize AI Learning System
function initializeAILearning($pdo, $user_id, $province, $age_group) {
    return new AILearningSystem($pdo, $user_id, $province, $age_group);
}

// Create necessary database tables for AI learning
function createAILearningTables($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS user_behavior_tracking (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            product_id INTEGER,
            category VARCHAR(100),
            search_term TEXT,
            province VARCHAR(100),
            age_group VARCHAR(20),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "CREATE TABLE IF NOT EXISTS user_category_preferences (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            category VARCHAR(100) NOT NULL,
            preference_score DECIMAL(10,2) DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, category)
        )",

        "CREATE TABLE IF NOT EXISTS learned_patterns (
            id SERIAL PRIMARY KEY,
            province VARCHAR(100) NOT NULL,
            age_group VARCHAR(20) NOT NULL,
            pattern_type VARCHAR(50) NOT NULL,
            category VARCHAR(100) NOT NULL,
            pattern_data JSONB,
            learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(province, age_group, pattern_type, category)
        )",

        "CREATE TABLE IF NOT EXISTS recommendation_weights (
            id SERIAL PRIMARY KEY,
            province VARCHAR(100) NOT NULL,
            age_group VARCHAR(20) NOT NULL,
            category VARCHAR(100) NOT NULL,
            weight DECIMAL(10,2) DEFAULT 1.0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(province, age_group, category)
        )",

        "CREATE INDEX IF NOT EXISTS idx_user_behavior_user ON user_behavior_tracking(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_behavior_province_age ON user_behavior_tracking(province, age_group)",
        "CREATE INDEX IF NOT EXISTS idx_user_category_prefs ON user_category_preferences(user_id, preference_score DESC)",
        "CREATE INDEX IF NOT EXISTS idx_learned_patterns_lookup ON learned_patterns(province, age_group, category)",
        "CREATE INDEX IF NOT EXISTS idx_recommendation_weights_lookup ON recommendation_weights(province, age_group, category)"
    ];

    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Error creating AI learning table: " . $e->getMessage());
        }
    }
}
?>
