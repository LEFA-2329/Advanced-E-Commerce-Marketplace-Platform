<?php
require_once '../db_connection.php';
require_once 'ai_learning_system.php';

// Setup AI Learning Database Tables
try {
    createAILearningTables($pdo);
    echo "AI Learning database tables created successfully!\n";

    // Initialize with some sample learning data
    initializeSampleLearningData($pdo);

    echo "AI Learning system setup complete!\n";
} catch (Exception $e) {
    echo "Error setting up AI Learning system: " . $e->getMessage() . "\n";
}

function initializeSampleLearningData($pdo) {
    // Sample learning data for different provinces and age groups
    $sampleData = [
        [
            'province' => 'Gauteng',
            'age_group' => '18-25',
            'patterns' => [
                ['type' => 'purchase', 'category' => 'Electronics', 'data' => ['purchase_count' => 45, 'avg_quantity' => 1.2]],
                ['type' => 'purchase', 'category' => 'Clothing', 'data' => ['purchase_count' => 32, 'avg_quantity' => 2.1]],
                ['type' => 'search', 'category' => 'Electronics', 'data' => ['search_count' => 28]],
                ['type' => 'browsing', 'category' => 'Beauty', 'data' => ['wishlist_count' => 15]]
            ]
        ],
        [
            'province' => 'Western Cape',
            'age_group' => '26-35',
            'patterns' => [
                ['type' => 'purchase', 'category' => 'Books', 'data' => ['purchase_count' => 38, 'avg_quantity' => 1.8]],
                ['type' => 'purchase', 'category' => 'Home & Garden', 'data' => ['purchase_count' => 29, 'avg_quantity' => 1.5]],
                ['type' => 'search', 'category' => 'Books', 'data' => ['search_count' => 22]],
                ['type' => 'browsing', 'category' => 'Sports', 'data' => ['wishlist_count' => 18]]
            ]
        ],
        [
            'province' => 'KwaZulu-Natal',
            'age_group' => '36-45',
            'patterns' => [
                ['type' => 'purchase', 'category' => 'Food', 'data' => ['purchase_count' => 52, 'avg_quantity' => 3.2]],
                ['type' => 'purchase', 'category' => 'Health', 'data' => ['purchase_count' => 31, 'avg_quantity' => 1.7]],
                ['type' => 'search', 'category' => 'Food', 'data' => ['search_count' => 35]],
                ['type' => 'browsing', 'category' => 'Automotive', 'data' => ['wishlist_count' => 12]]
            ]
        ]
    ];

    foreach ($sampleData as $demographic) {
        foreach ($demographic['patterns'] as $pattern) {
            $stmt = $pdo->prepare("
                INSERT INTO learned_patterns
                (province, age_group, pattern_type, category, pattern_data, learned_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT (province, age_group, pattern_type, category)
                DO UPDATE SET pattern_data = EXCLUDED.pattern_data
            ");

            $stmt->execute([
                $demographic['province'],
                $demographic['age_group'],
                $pattern['type'],
                $pattern['category'],
                json_encode($pattern['data'])
            ]);
        }
    }

    // Initialize recommendation weights based on sample data
    $weightData = [
        ['Gauteng', '18-25', 'Electronics', 2.5],
        ['Gauteng', '18-25', 'Clothing', 2.2],
        ['Gauteng', '18-25', 'Beauty', 1.8],
        ['Western Cape', '26-35', 'Books', 2.8],
        ['Western Cape', '26-35', 'Home & Garden', 2.3],
        ['Western Cape', '26-35', 'Sports', 1.9],
        ['KwaZulu-Natal', '36-45', 'Food', 3.1],
        ['KwaZulu-Natal', '36-45', 'Health', 2.4],
        ['KwaZulu-Natal', '36-45', 'Automotive', 1.6]
    ];

    foreach ($weightData as $weight) {
        $stmt = $pdo->prepare("
            INSERT INTO recommendation_weights
            (province, age_group, category, weight, updated_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (province, age_group, category)
            DO UPDATE SET weight = EXCLUDED.weight
        ");

        $stmt->execute($weight);
    }

    echo "Sample learning data initialized!\n";
}
?>
