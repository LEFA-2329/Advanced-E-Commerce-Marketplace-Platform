<?php
// Database connection
require_once 'db_connection.php';

try {
    // Create chat_sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_sessions (
            session_id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES customers(customer_id) ON DELETE CASCADE,
            user_agent TEXT,
            ip_address VARCHAR(45),
            session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        );
    ");

    // Create chat_messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            message_id SERIAL PRIMARY KEY,
            session_id INT NOT NULL REFERENCES chat_sessions(session_id) ON DELETE CASCADE,
            user_id INT NOT NULL REFERENCES customers(customer_id) ON DELETE CASCADE,
            message_type VARCHAR(10) NOT NULL CHECK (message_type IN ('user', 'bot')),
            message_text TEXT NOT NULL,
            message_metadata JSONB,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Create chat_feedback table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_feedback (
            feedback_id SERIAL PRIMARY KEY,
            message_id INT NOT NULL REFERENCES chat_messages(message_id) ON DELETE CASCADE,
            user_id INT NOT NULL REFERENCES customers(customer_id) ON DELETE CASCADE,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            feedback_text TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Create chat_quick_replies table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_quick_replies (
            reply_id SERIAL PRIMARY KEY,
            trigger_keywords JSONB,
            reply_text TEXT NOT NULL,
            reply_category VARCHAR(100),
            usage_count INT DEFAULT 0,
            success_rate DECIMAL(3,2) DEFAULT 0.00,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Insert some default quick replies
    $defaultReplies = [
        [
            'keywords' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon'],
            'text' => 'Hello! How can I help you find the perfect products today?',
            'category' => 'greeting'
        ],
        [
            'keywords' => ['price', 'cost', 'how much'],
            'text' => 'I can help you find products within your budget. What price range are you looking for?',
            'category' => 'pricing'
        ],
        [
            'keywords' => ['sale', 'discount', 'promotion', 'deal'],
            'text' => 'Great! Let me show you our current promotions and discounted items.',
            'category' => 'sales'
        ],
        [
            'keywords' => ['recommend', 'suggest', 'what should I buy'],
            'text' => 'I\'d love to recommend products based on your preferences! What type of products are you interested in?',
            'category' => 'recommendations'
        ],
        [
            'keywords' => ['shipping', 'delivery', 'when will it arrive'],
            'text' => 'I can help you with shipping information. Which product are you interested in?',
            'category' => 'shipping'
        ],
        [
            'keywords' => ['return', 'refund', 'exchange'],
            'text' => 'Let me explain our return policy and help you with any returns or exchanges.',
            'category' => 'returns'
        ],
        [
            'keywords' => ['stock', 'available', 'in stock'],
            'text' => 'I can check the availability of any product for you. What are you looking for?',
            'category' => 'availability'
        ],
        [
            'keywords' => ['thank you', 'thanks', 'appreciate'],
            'text' => 'You\'re very welcome! Is there anything else I can help you with?',
            'category' => 'gratitude'
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO chat_quick_replies (trigger_keywords, reply_text, reply_category)
        VALUES (?, ?, ?)
        ON CONFLICT DO NOTHING
    ");

    foreach ($defaultReplies as $reply) {
        $stmt->execute([
            json_encode($reply['keywords']),
            $reply['text'],
            $reply['category']
        ]);
    }

    // Create indexes for better performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_sessions_user_id ON chat_sessions(user_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_sessions_active ON chat_sessions(is_active) WHERE is_active = true;");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_session_id ON chat_messages(session_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_messages_created_at ON chat_messages(created_at);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_feedback_message_id ON chat_feedback(message_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_quick_replies_active ON chat_quick_replies(is_active) WHERE is_active = true;");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_quick_replies_usage ON chat_quick_replies(usage_count DESC);");

    echo "Chat tables created successfully with default quick replies!\n";

} catch (Exception $e) {
    echo "Error creating chat tables: " . $e->getMessage() . "\n";
}
?>
