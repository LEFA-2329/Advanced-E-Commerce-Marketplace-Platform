<?php
require_once 'db_connection.php';

echo "=== AI Learning System Data Population ===\n\n";

try {
    // 1. Insert sample user behavior tracking data
    echo "1. Inserting user behavior tracking data...\n";

    $behaviorData = [
        // Customer interactions for learning patterns
        ['user_id' => 1, 'product_id' => 1, 'interaction_type' => 'purchase', 'province' => 'Gauteng', 'age_group' => '26-35', 'timestamp' => '2024-01-15 10:30:00'],
        ['user_id' => 1, 'product_id' => 2, 'interaction_type' => 'search', 'province' => 'Gauteng', 'age_group' => '26-35', 'timestamp' => '2024-01-15 10:35:00'],
        ['user_id' => 1, 'product_id' => 3, 'interaction_type' => 'wishlist', 'province' => 'Gauteng', 'age_group' => '26-35', 'timestamp' => '2024-01-15 10:40:00'],
        ['user_id' => 2, 'product_id' => 4, 'interaction_type' => 'purchase', 'province' => 'Western Cape', 'age_group' => '36-45', 'timestamp' => '2024-01-16 14:20:00'],
        ['user_id' => 2, 'product_id' => 5, 'interaction_type' => 'view', 'province' => 'Western Cape', 'age_group' => '36-45', 'timestamp' => '2024-01-16 14:25:00'],
        ['user_id' => 3, 'product_id' => 6, 'interaction_type' => 'purchase', 'province' => 'KwaZulu-Natal', 'age_group' => '18-25', 'timestamp' => '2024-01-17 09:15:00'],
        ['user_id' => 3, 'product_id' => 7, 'interaction_type' => 'search', 'province' => 'KwaZulu-Natal', 'age_group' => '18-25', 'timestamp' => '2024-01-17 09:20:00'],
        ['user_id' => 4, 'product_id' => 8, 'interaction_type' => 'wishlist', 'province' => 'Gauteng', 'age_group' => '46-55', 'timestamp' => '2024-01-18 16:45:00'],
        ['user_id' => 4, 'product_id' => 9, 'interaction_type' => 'purchase', 'province' => 'Gauteng', 'age_group' => '46-55', 'timestamp' => '2024-01-18 16:50:00'],
        ['user_id' => 5, 'product_id' => 10, 'interaction_type' => 'view', 'province' => 'Western Cape', 'age_group' => '26-35', 'timestamp' => '2024-01-19 11:30:00'],
        ['user_id' => 5, 'product_id' => 11, 'interaction_type' => 'purchase', 'province' => 'Western Cape', 'age_group' => '26-35', 'timestamp' => '2024-01-19 11:35:00'],
        ['user_id' => 6, 'product_id' => 12, 'interaction_type' => 'search', 'province' => 'Gauteng', 'age_group' => '36-45', 'timestamp' => '2024-01-20 13:10:00'],
        ['user_id' => 6, 'product_id' => 13, 'interaction_type' => 'wishlist', 'province' => 'Gauteng', 'age_group' => '36-45', 'timestamp' => '2024-01-20 13:15:00'],
        ['user_id' => 7, 'product_id' => 14, 'interaction_type' => 'purchase', 'province' => 'KwaZulu-Natal', 'age_group' => '46-55', 'timestamp' => '2024-01-21 15:25:00'],
        ['user_id' => 7, 'product_id' => 15, 'interaction_type' => 'view', 'province' => 'KwaZulu-Natal', 'age_group' => '46-55', 'timestamp' => '2024-01-21 15:30:00'],
        ['user_id' => 8, 'product_id' => 16, 'interaction_type' => 'search', 'province' => 'Western Cape', 'age_group' => '18-25', 'timestamp' => '2024-01-22 08:45:00'],
        ['user_id' => 8, 'product_id' => 17, 'interaction_type' => 'purchase', 'province' => 'Western Cape', 'age_group' => '18-25', 'timestamp' => '2024-01-22 08:50:00'],
        ['user_id' => 9, 'product_id' => 18, 'interaction_type' => 'wishlist', 'province' => 'Gauteng', 'age_group' => '26-35', 'timestamp' => '2024-01-23 12:15:00'],
        ['user_id' => 9, 'product_id' => 19, 'interaction_type' => 'view', 'province' => 'Gauteng', 'age_group' => '26-35', 'timestamp' => '2024-01-23 12:20:00'],
        ['user_id' => 10, 'product_id' => 20, 'interaction_type' => 'purchase', 'province' => 'Western Cape', 'age_group' => '36-45', 'timestamp' => '2024-01-24 17:30:00'],
    ];

    $behaviorStmt = $pdo->prepare("
        INSERT INTO user_behavior_tracking (user_id, product_id, interaction_type, province, age_group, timestamp)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT DO NOTHING
    ");

    foreach ($behaviorData as $behavior) {
        $behaviorStmt->execute([
            $behavior['user_id'],
            $behavior['product_id'],
            $behavior['interaction_type'],
            $behavior['province'],
            $behavior['age_group'],
            $behavior['timestamp']
        ]);
    }

    echo "✓ User behavior tracking data inserted successfully!\n";

    // 2. Insert learned patterns data
    echo "\n2. Inserting learned patterns data...\n";

    $patternsData = [
        ['province' => 'Gauteng', 'age_group' => '26-35', 'preferred_categories' => 'Electronics,Sports,Fashion', 'confidence_score' => 0.85],
        ['province' => 'Western Cape', 'age_group' => '36-45', 'preferred_categories' => 'Home & Garden,Beauty,Food', 'confidence_score' => 0.78],
        ['province' => 'KwaZulu-Natal', 'age_group' => '18-25', 'preferred_categories' => 'Fashion,Electronics,Sports', 'confidence_score' => 0.72],
        ['province' => 'Gauteng', 'age_group' => '46-55', 'preferred_categories' => 'Home & Garden,Electronics,Beauty', 'confidence_score' => 0.81],
        ['province' => 'Western Cape', 'age_group' => '26-35', 'preferred_categories' => 'Sports,Fashion,Electronics', 'confidence_score' => 0.76],
        ['province' => 'Gauteng', 'age_group' => '36-45', 'preferred_categories' => 'Electronics,Home & Garden,Sports', 'confidence_score' => 0.79],
        ['province' => 'KwaZulu-Natal', 'age_group' => '46-55', 'preferred_categories' => 'Home & Garden,Beauty,Food', 'confidence_score' => 0.74],
        ['province' => 'Western Cape', 'age_group' => '18-25', 'preferred_categories' => 'Fashion,Electronics,Sports', 'confidence_score' => 0.69],
        ['province' => 'Gauteng', 'age_group' => '18-25', 'preferred_categories' => 'Electronics,Fashion,Sports', 'confidence_score' => 0.71],
        ['province' => 'Western Cape', 'age_group' => '46-55', 'preferred_categories' => 'Home & Garden,Beauty,Electronics', 'confidence_score' => 0.75],
    ];

    $patternsStmt = $pdo->prepare("
        INSERT INTO learned_patterns (province, age_group, preferred_categories, confidence_score)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (province, age_group) DO UPDATE SET
            preferred_categories = EXCLUDED.preferred_categories,
            confidence_score = EXCLUDED.confidence_score
    ");

    foreach ($patternsData as $pattern) {
        $patternsStmt->execute([
            $pattern['province'],
            $pattern['age_group'],
            $pattern['preferred_categories'],
            $pattern['confidence_score']
        ]);
    }

    echo "✓ Learned patterns data inserted successfully!\n";

    // 3. Insert recommendation weights data
    echo "\n3. Inserting recommendation weights data...\n";

    $weightsData = [
        ['category' => 'Electronics', 'interaction_type' => 'purchase', 'weight' => 5.0, 'province' => 'Gauteng', 'age_group' => '26-35'],
        ['category' => 'Electronics', 'interaction_type' => 'search', 'weight' => 3.0, 'province' => 'Gauteng', 'age_group' => '26-35'],
        ['category' => 'Electronics', 'interaction_type' => 'wishlist', 'weight' => 4.0, 'province' => 'Gauteng', 'age_group' => '26-35'],
        ['category' => 'Electronics', 'interaction_type' => 'view', 'weight' => 1.0, 'province' => 'Gauteng', 'age_group' => '26-35'],
        ['category' => 'Sports', 'interaction_type' => 'purchase', 'weight' => 4.5, 'province' => 'Gauteng', 'age_group' => '26-35'],
        ['category' => 'Sports', 'interaction_type' => 'search', 'weight' => 2.8, 'province' => 'Gauteng', 'age_group' => '26-35'],
        ['category' => 'Fashion', 'interaction_type' => 'purchase', 'weight' => 4.2, 'province' => 'Western Cape', 'age_group' => '18-25'],
        ['category' => 'Fashion', 'interaction_type' => 'search', 'weight' => 3.1, 'province' => 'Western Cape', 'age_group' => '18-25'],
        ['category' => 'Home & Garden', 'interaction_type' => 'purchase', 'weight' => 4.8, 'province' => 'Western Cape', 'age_group' => '36-45'],
        ['category' => 'Home & Garden', 'interaction_type' => 'wishlist', 'weight' => 4.1, 'province' => 'Western Cape', 'age_group' => '36-45'],
        ['category' => 'Beauty', 'interaction_type' => 'purchase', 'weight' => 4.3, 'province' => 'Western Cape', 'age_group' => '36-45'],
        ['category' => 'Beauty', 'interaction_type' => 'view', 'weight' => 1.2, 'province' => 'Western Cape', 'age_group' => '36-45'],
        ['category' => 'Food', 'interaction_type' => 'purchase', 'weight' => 4.0, 'province' => 'KwaZulu-Natal', 'age_group' => '18-25'],
        ['category' => 'Food', 'interaction_type' => 'search', 'weight' => 2.9, 'province' => 'KwaZulu-Natal', 'age_group' => '18-25'],
        ['category' => 'Electronics', 'interaction_type' => 'purchase', 'weight' => 4.7, 'province' => 'Gauteng', 'age_group' => '46-55'],
        ['category' => 'Electronics', 'interaction_type' => 'wishlist', 'weight' => 3.9, 'province' => 'Gauteng', 'age_group' => '46-55'],
        ['category' => 'Home & Garden', 'interaction_type' => 'purchase', 'weight' => 4.6, 'province' => 'Gauteng', 'age_group' => '46-55'],
        ['category' => 'Home & Garden', 'interaction_type' => 'view', 'weight' => 1.1, 'province' => 'Gauteng', 'age_group' => '46-55'],
        ['category' => 'Sports', 'interaction_type' => 'purchase', 'weight' => 4.4, 'province' => 'Western Cape', 'age_group' => '26-35'],
        ['category' => 'Sports', 'interaction_type' => 'search', 'weight' => 3.0, 'province' => 'Western Cape', 'age_group' => '26-35'],
    ];

    $weightsStmt = $pdo->prepare("
        INSERT INTO recommendation_weights (category, interaction_type, weight, province, age_group)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (category, interaction_type, province, age_group) DO UPDATE SET
            weight = EXCLUDED.weight
    ");

    foreach ($weightsData as $weight) {
        $weightsStmt->execute([
            $weight['category'],
            $weight['interaction_type'],
            $weight['weight'],
            $weight['province'],
            $weight['age_group']
        ]);
    }

    echo "✓ Recommendation weights data inserted successfully!\n";

    // 4. Insert user category preferences data
    echo "\n4. Inserting user category preferences data...\n";

    $preferencesData = [
        ['user_id' => 1, 'category' => 'Electronics', 'preference_score' => 0.85, 'last_updated' => '2024-01-15 10:40:00'],
        ['user_id' => 1, 'category' => 'Sports', 'preference_score' => 0.72, 'last_updated' => '2024-01-15 10:40:00'],
        ['user_id' => 2, 'category' => 'Home & Garden', 'preference_score' => 0.78, 'last_updated' => '2024-01-16 14:25:00'],
        ['user_id' => 2, 'category' => 'Beauty', 'preference_score' => 0.65, 'last_updated' => '2024-01-16 14:25:00'],
        ['user_id' => 3, 'category' => 'Fashion', 'preference_score' => 0.81, 'last_updated' => '2024-01-17 09:20:00'],
        ['user_id' => 3, 'category' => 'Electronics', 'preference_score' => 0.69, 'last_updated' => '2024-01-17 09:20:00'],
        ['user_id' => 4, 'category' => 'Home & Garden', 'preference_score' => 0.83, 'last_updated' => '2024-01-18 16:50:00'],
        ['user_id' => 4, 'category' => 'Electronics', 'preference_score' => 0.76, 'last_updated' => '2024-01-18 16:50:00'],
        ['user_id' => 5, 'category' => 'Sports', 'preference_score' => 0.79, 'last_updated' => '2024-01-19 11:35:00'],
        ['user_id' => 5, 'category' => 'Fashion', 'preference_score' => 0.67, 'last_updated' => '2024-01-19 11:35:00'],
    ];

    $preferencesStmt = $pdo->prepare("
        INSERT INTO user_category_preferences (user_id, category, preference_score, last_updated)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (user_id, category) DO UPDATE SET
            preference_score = EXCLUDED.preference_score,
            last_updated = EXCLUDED.last_updated
    ");

    foreach ($preferencesData as $preference) {
        $preferencesStmt->execute([
            $preference['user_id'],
            $preference['category'],
            $preference['preference_score'],
            $preference['last_updated']
        ]);
    }

    echo "✓ User category preferences data inserted successfully!\n";

    echo "\n=== AI Learning System Data Population Complete ===\n";
    echo "\nThe AI system now has sample data to learn from and can:\n";
    echo "- Track user behavior patterns by province and age group\n";
    echo "- Generate personalized product recommendations\n";
    echo "- Validate business registrations against approved entities\n";
    echo "- Prevent invalid owner registrations\n";
    echo "\nTo test the system:\n";
    echo "1. Try registering as an owner with valid business data from approved_business_entities\n";
    echo "2. Try registering with invalid business data (should be rejected)\n";
    echo "3. Test the recommendation system with customer browsing\n";

} catch (Exception $e) {
    echo "✗ Error populating AI learning data: " . $e->getMessage() . "\n";
}
?>
