<?php
session_start();
require_once '../db_connection.php';

// Set CORS headers to allow session cookies
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    http_response_code(403);
    echo json_encode(['error' => 'Please log in to use AI customer support']);
    exit;
}

// Initialize or get chat session
$chat_session_id = initializeChatSession($pdo, $_SESSION['user_id']);

// Handle GET request for chat history
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $history = getChatHistory($pdo, $chat_session_id);
    echo json_encode(['history' => $history]);
    exit;
}

// Handle POST requests for chat messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');

    if (empty($message)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }

    // Save user message to database
    saveChatMessage($pdo, $chat_session_id, $_SESSION['user_id'], 'user', $message);

    // Process the AI response with product image and ordering capabilities
    $response = processAIResponse($pdo, $message, $_SESSION['user_id']);

    // Save bot response to database
    saveChatMessage($pdo, $chat_session_id, $_SESSION['user_id'], 'bot', $response, ['response_type' => 'ai_generated']);

    echo json_encode([
        'response' => $response,
        'timestamp' => date('H:i'),
        'session_id' => $chat_session_id
    ]);
    exit;
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'feedback') {
    $message_id = intval($_POST['message_id']);
    $rating = intval($_POST['rating']);
    $feedback_text = trim($_POST['feedback_text'] ?? '');

    if ($rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Invalid rating']);
        exit;
    }

    saveChatFeedback($pdo, $message_id, $_SESSION['user_id'], $rating, $feedback_text);
    echo json_encode(['success' => true]);
    exit;
}

function initializeChatSession($pdo, $user_id) {
    // Check if there's an active session for this user
    $stmt = $pdo->prepare("SELECT session_id FROM chat_sessions WHERE user_id = ? AND is_active = true ORDER BY session_start DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $existing_session = $stmt->fetch();

    if ($existing_session) {
        return $existing_session['session_id'];
    }

    // Create new session
    $stmt = $pdo->prepare("INSERT INTO chat_sessions (user_id, user_agent, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([
        $user_id,
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    return $pdo->lastInsertId();
}

function saveChatMessage($pdo, $session_id, $user_id, $message_type, $message_text, $metadata = null) {
    $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, user_id, message_type, message_text, message_metadata) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $session_id,
        $user_id,
        $message_type,
        $message_text,
        $metadata ? json_encode($metadata) : null
    ]);
}

function getChatHistory($pdo, $session_id) {
    $stmt = $pdo->prepare("
        SELECT message_type, message_text, created_at
        FROM chat_messages
        WHERE session_id = ?
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$session_id]);

    $history = [];
    while ($row = $stmt->fetch()) {
        $history[] = [
            'type' => $row['message_type'],
            'message' => $row['message_text'],
            'timestamp' => date('H:i', strtotime($row['created_at']))
        ];
    }

    return $history;
}

function saveChatFeedback($pdo, $message_id, $user_id, $rating, $feedback_text) {
    $stmt = $pdo->prepare("INSERT INTO chat_feedback (message_id, user_id, rating, feedback_text) VALUES (?, ?, ?, ?)");
    $stmt->execute([$message_id, $user_id, $rating, $feedback_text]);
}


function processAIResponse($pdo, $message, $user_id) {
    $message_lower = strtolower($message);

    // Check for confirmation responses first (like "yes", "add it", "sure")
    $confirmation_keywords = ['yes', 'yeah', 'sure', 'okay', 'ok', 'add it', 'add to cart', 'please', 'go ahead'];
    $is_confirmation = false;

    foreach ($confirmation_keywords as $keyword) {
        if (strpos($message_lower, $keyword) !== false) {
            $is_confirmation = true;
            break;
        }
    }

    // If this is a confirmation and we have a pending product, handle it
    if ($is_confirmation && isset($_SESSION['pending_product'])) {
        return handleProductConfirmation($pdo, $user_id);
    }

    // Enhanced greeting responses - behave like a real human
    $greetings = [
        'hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening',
        'how are you', 'how is your day', 'how did you sleep', 'what\'s up',
        'how do you do', 'nice to meet you', 'greetings', 'salutations',
        'how\'s it going', 'how are things', 'how have you been'
    ];

    foreach ($greetings as $greeting) {
        if (strpos($message_lower, $greeting) !== false) {
            return handleGreeting($pdo, $message, $user_id);
        }
    }

    // Handle stock-related queries first (most specific)
    if (strpos($message_lower, 'stock') !== false || strpos($message_lower, 'available') !== false ||
        strpos($message_lower, 'in stock') !== false || strpos($message_lower, 'out of stock') !== false ||
        strpos($message_lower, 'quantity') !== false || strpos($message_lower, 'how many') !== false) {
        return handleStockQuery($pdo, $message);
    }

    // Handle product image requests
    if (strpos($message_lower, 'picture') !== false || strpos($message_lower, 'image') !== false ||
        strpos($message_lower, 'photo') !== false || strpos($message_lower, 'show me') !== false) {
        return handleImageQuery($pdo, $message);
    }

    // Handle order placement requests
    if (strpos($message_lower, 'order') !== false || strpos($message_lower, 'buy') !== false ||
        strpos($message_lower, 'purchase') !== false || strpos($message_lower, 'add to cart') !== false) {
        return handleOrderQuery($pdo, $message, $user_id);
    }

    // Price queries
    if (strpos($message_lower, 'price') !== false || strpos($message_lower, 'cost') !== false ||
        strpos($message_lower, 'how much') !== false || strpos($message_lower, 'worth') !== false) {
        return handlePriceQuery($pdo, $message);
    }

    // Enhanced Sale/Promotion queries - show products with pictures and ask to order
    if (strpos($message_lower, 'sale') !== false || strpos($message_lower, 'discount') !== false ||
        strpos($message_lower, 'promotion') !== false || strpos($message_lower, 'deal') !== false ||
        strpos($message_lower, 'offer') !== false || strpos($message_lower, 'on promotion') !== false ||
        strpos($message_lower, 'on sale') !== false || strpos($message_lower, 'what\'s on sale') !== false ||
        strpos($message_lower, 'which products') !== false && (strpos($message_lower, 'promotion') !== false || strpos($message_lower, 'sale') !== false)) {
        return handleSalesQuery($pdo);
    }

    // Product recommendations
    if (strpos($message_lower, 'recommend') !== false || strpos($message_lower, 'suggest') !== false ||
        strpos($message_lower, 'what should i') !== false || strpos($message_lower, 'best') !== false) {
        return handleRecommendationQuery($pdo, $user_id);
    }

    // Order status queries
    if (strpos($message_lower, 'order status') !== false || strpos($message_lower, 'tracking') !== false ||
        strpos($message_lower, 'delivery') !== false || strpos($message_lower, 'where is my') !== false ||
        strpos($message_lower, 'when will') !== false) {
        return handleOrderStatusQuery($pdo, $user_id);
    }

    // Chat history
    if (strpos($message_lower, 'history') !== false || strpos($message_lower, 'previous') !== false ||
        strpos($message_lower, 'before') !== false || strpos($message_lower, 'what did we') !== false) {
        return handleChatHistory($pdo, $user_id);
    }

    // Thank you responses
    if (strpos($message_lower, 'thank') !== false || strpos($message_lower, 'thanks') !== false ||
        strpos($message_lower, 'appreciate') !== false) {
        return handleThankYou($pdo, $user_id);
    }

    // Handle general questions and inquiries
    if (strpos($message_lower, 'what') !== false || strpos($message_lower, 'how') !== false ||
        strpos($message_lower, 'when') !== false || strpos($message_lower, 'where') !== false ||
        strpos($message_lower, 'why') !== false || strpos($message_lower, 'can you') !== false ||
        strpos($message_lower, 'do you') !== false || strpos($message_lower, 'tell me') !== false ||
        strpos($message_lower, 'explain') !== false || strpos($message_lower, 'help') !== false) {
        return handleGeneralQuestion($pdo, $message, $user_id);
    }

    // Handle general product queries (if no specific keywords match)
    $product_name = extractProductName($message);
    if (!empty($product_name)) {
        return handleGeneralProductQuery($pdo, $product_name, $user_id);
    }

    // Handle category queries
    $category = extractCategoryFromMessage($message);
    if (!empty($category)) {
        return handleCategoryQuery($pdo, $category);
    }

    // Default response - more human-like
    $default_responses = [
        "I'm here to help you find the perfect products! What are you looking for today?",
        "I'd love to help you with your shopping. What can I assist you with?",
        "Feel free to ask me anything about our products, prices, or orders. How can I help?",
        "I'm your shopping assistant! What would you like to know about our store?"
    ];
    return $default_responses[array_rand($default_responses)];
}

// New function to handle stock queries
function handleStockQuery($pdo, $message) {
    $product_name = extractProductName($message);
    if (empty($product_name)) {
        return "Please specify the product name you want to check the stock for.";
    }

    try {
        $stmt = $pdo->prepare("SELECT name, stock_quantity FROM products WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            if ($product['stock_quantity'] > 0) {
                return "Yes, we have " . $product['stock_quantity'] . " units of '" . $product['name'] . "' in stock.";
            } else {
                return "Unfortunately, '" . $product['name'] . "' is currently out of stock.";
            }
        } else {
            return "I couldn't find any product matching '" . $product_name . "'. Could you please check the name and try again?";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble checking the stock right now. Please try again later.";
    }
}

// Enhanced function to handle all types of general questions and instructions
function handleGeneralQuestion($pdo, $message, $user_id) {
    $message_lower = strtolower($message);

    // Handle meta questions about asking questions
    if (strpos($message_lower, 'can i ask') !== false || strpos($message_lower, 'may i ask') !== false ||
        strpos($message_lower, 'could i ask') !== false || strpos($message_lower, 'is it okay to ask') !== false) {
        return "Of course! I'm here to help with any questions you have. Feel free to ask me anything about our products, services, or anything else!";
    }

    // Handle questions about the chatbot itself
    if (strpos($message_lower, 'who are you') !== false || strpos($message_lower, 'what are you') !== false ||
        strpos($message_lower, 'what is your name') !== false || strpos($message_lower, 'introduce yourself') !== false) {
        return "I'm your friendly AI shopping assistant! I'm here 24/7 to help you find products, answer questions, and make your shopping experience amazing. What can I help you with today?";
    }

    // Handle questions about capabilities
    if (strpos($message_lower, 'what can you do') !== false || strpos($message_lower, 'what can you help') !== false ||
        strpos($message_lower, 'how can you help') !== false || strpos($message_lower, 'your capabilities') !== false) {
        return "I can help you with:\n• Finding products and checking stock availability\n• Answering questions about pricing, shipping, and returns\n• Providing product recommendations\n• Checking your order status\n• Answering general questions about our store\n• And much more! What would you like help with?";
    }

    // Handle questions about the store
    if (strpos($message_lower, 'what do you sell') !== false || strpos($message_lower, 'what products') !== false ||
        strpos($message_lower, 'what is this store') !== false || strpos($message_lower, 'what kind of') !== false) {
        return "We sell a wide variety of products including electronics, clothing, home goods, and more! You can browse our categories or ask me about specific products.";
    }

    // Handle questions about hours
    if (strpos($message_lower, 'hours') !== false || strpos($message_lower, 'open') !== false ||
        strpos($message_lower, 'close') !== false || strpos($message_lower, 'when are you') !== false) {
        return "Our store is open 24/7 online! You can shop anytime from anywhere.";
    }

    // Handle questions about shipping/delivery
    if (strpos($message_lower, 'shipping') !== false || strpos($message_lower, 'delivery time') !== false ||
        strpos($message_lower, 'how long') !== false || strpos($message_lower, 'when will i receive') !== false) {
        return "We offer fast shipping with most orders delivered within 3-5 business days. Express shipping is available for urgent orders!";
    }

    // Handle questions about returns
    if (strpos($message_lower, 'return') !== false || strpos($message_lower, 'refund') !== false ||
        strpos($message_lower, 'exchange') !== false) {
        return "We have a 30-day return policy for most items. Items must be in original condition with tags attached.";
    }

    // Handle questions about payment
    if (strpos($message_lower, 'payment') !== false || strpos($message_lower, 'pay') !== false ||
        strpos($message_lower, 'credit card') !== false || strpos($message_lower, 'how to pay') !== false) {
        return "We accept all major credit cards, PayPal, and other secure payment methods. All transactions are encrypted and secure.";
    }

    // Handle questions about contact
    if (strpos($message_lower, 'contact') !== false || strpos($message_lower, 'phone') !== false ||
        strpos($message_lower, 'email') !== false || strpos($message_lower, 'reach you') !== false) {
        return "You can contact us through this chat, or reach our customer service team at support@store.com or call 1-800-STORE.";
    }

    // Handle questions about policies
    if (strpos($message_lower, 'policy') !== false || strpos($message_lower, 'terms') !== false ||
        strpos($message_lower, 'privacy') !== false) {
        return "You can find our full policies including terms of service, privacy policy, and return policy on our website under the 'Policies' section.";
    }

    // Handle questions about account
    if (strpos($message_lower, 'account') !== false || strpos($message_lower, 'profile') !== false ||
        strpos($message_lower, 'login') !== false || strpos($message_lower, 'password') !== false) {
        return "You can manage your account settings, view order history, and update your information in the 'My Account' section of our website.";
    }

    // Handle questions about help/support
    if (strpos($message_lower, 'help me') !== false || strpos($message_lower, 'i need help') !== false ||
        strpos($message_lower, 'assist me') !== false || strpos($message_lower, 'support') !== false) {
        return "I'm here to help! Whether you need product recommendations, have questions about orders, or want to know about our services, I'm ready to assist. What can I help you with?";
    }

    // Handle conversational fillers
    if (strpos($message_lower, 'just wondering') !== false || strpos($message_lower, 'curious') !== false ||
        strpos($message_lower, 'by the way') !== false || strpos($message_lower, 'actually') !== false) {
        return "I'm all ears! What's on your mind? Feel free to ask me anything.";
    }

    // Handle questions about location/store location
    if (strpos($message_lower, 'where are you located') !== false || strpos($message_lower, 'store location') !== false ||
        strpos($message_lower, 'physical store') !== false || strpos($message_lower, 'address') !== false) {
        return "We're primarily an online store, but you can shop with us from anywhere! We don't have a physical location, but our online store is always open.";
    }

    // Handle questions about guarantees/warranties
    if (strpos($message_lower, 'guarantee') !== false || strpos($message_lower, 'warranty') !== false ||
        strpos($message_lower, 'quality') !== false || strpos($message_lower, 'satisfaction') !== false) {
        return "We stand behind our products with quality guarantees. Most items come with manufacturer warranties, and we're committed to your satisfaction. If you're not happy, we'll make it right!";
    }

    // Handle questions about size/fit
    if (strpos($message_lower, 'size') !== false || strpos($message_lower, 'fit') !== false ||
        strpos($message_lower, 'measurements') !== false) {
        return "For sizing information, please check the product details page for measurements and size guides. If you need help determining your size, I'd be happy to assist!";
    }

    // Handle questions about availability
    if (strpos($message_lower, 'available now') !== false || strpos($message_lower, 'ready to ship') !== false ||
        strpos($message_lower, 'in stock now') !== false) {
        return "I can check current stock levels for any product! Just let me know what you're looking for and I'll tell you if it's available.";
    }

    // Handle time-sensitive questions
    if (strpos($message_lower, 'today') !== false || strpos($message_lower, 'now') !== false ||
        strpos($message_lower, 'right now') !== false || strpos($message_lower, 'immediately') !== false) {
        return "I'm here right now and ready to help! What can I assist you with today?";
    }

    // Handle questions about recommendations
    if (strpos($message_lower, 'recommend something') !== false || strpos($message_lower, 'what do you suggest') !== false ||
        strpos($message_lower, 'what should i get') !== false) {
        return "I'd love to help you find the perfect product! Could you tell me what you're looking for or what your preferences are?";
    }

    // Handle questions about prices/deals
    if (strpos($message_lower, 'expensive') !== false || strpos($message_lower, 'cheap') !== false ||
        strpos($message_lower, 'affordable') !== false || strpos($message_lower, 'budget') !== false) {
        return "We have products for every budget! From affordable essentials to premium items, there's something for everyone. What price range are you looking for?";
    }

    // Handle questions about instructions/guidance
    if (strpos($message_lower, 'how do i') !== false || strpos($message_lower, 'how to') !== false ||
        strpos($message_lower, 'instructions') !== false || strpos($message_lower, 'guide') !== false) {
        return "I'd be happy to guide you! What would you like to know how to do? I can help with shopping, ordering, returns, and more.";
    }

    // Handle questions about features/specifications
    if (strpos($message_lower, 'features') !== false || strpos($message_lower, 'specs') !== false ||
        strpos($message_lower, 'specifications') !== false || strpos($message_lower, 'details') !== false) {
        return "I can provide detailed information about any product's features and specifications. Which product are you interested in?";
    }

    // Handle questions about comparisons
    if (strpos($message_lower, 'vs') !== false || strpos($message_lower, 'versus') !== false ||
        strpos($message_lower, 'compare') !== false || strpos($message_lower, 'better') !== false) {
        return "I'd be happy to help you compare products! Tell me which items you're considering and I'll help you decide which might be better for your needs.";
    }

    // Handle questions about customization/personalization
    if (strpos($message_lower, 'custom') !== false || strpos($message_lower, 'personalize') !== false ||
        strpos($message_lower, 'made to order') !== false) {
        return "We offer various customization options on select products. Let me know what you're looking for and I can check if we have customizable options available.";
    }

    // Handle questions about environmental/sustainability
    if (strpos($message_lower, 'eco') !== false || strpos($message_lower, 'sustainable') !== false ||
        strpos($message_lower, 'environment') !== false || strpos($message_lower, 'green') !== false) {
        return "We're committed to sustainability! Many of our products are eco-friendly, and we work with suppliers who share our environmental values. I can help you find sustainable options.";
    }

    // Handle questions about international shipping
    if (strpos($message_lower, 'international') !== false || strpos($message_lower, 'overseas') !== false ||
        strpos($message_lower, 'foreign') !== false || strpos($message_lower, 'outside') !== false) {
        return "Yes, we ship internationally! Shipping times and costs vary by location. I can help you check shipping options for your country.";
    }

    // Handle questions about gift options
    if (strpos($message_lower, 'gift') !== false || strpos($message_lower, 'present') !== false ||
        strpos($message_lower, 'birthday') !== false || strpos($message_lower, 'holiday') !== false) {
        return "Perfect for gifts! We offer gift wrapping options and can help you find the ideal present. What occasion are you shopping for?";
    }

    // Handle questions about bulk/wholesale
    if (strpos($message_lower, 'bulk') !== false || strpos($message_lower, 'wholesale') !== false ||
        strpos($message_lower, 'large quantity') !== false || strpos($message_lower, 'multiple') !== false) {
        return "For bulk orders or wholesale inquiries, please contact our sales team at wholesale@store.com. They can provide special pricing and arrangements.";
    }

    // Handle questions about technical support
    if (strpos($message_lower, 'technical') !== false || strpos($message_lower, 'tech support') !== false ||
        strpos($message_lower, 'setup') !== false || strpos($message_lower, 'installation') !== false) {
        return "For technical support or product setup assistance, our team is available to help. You can reach them at tech@store.com or through this chat.";
    }

    // Handle questions about business/corporate
    if (strpos($message_lower, 'business') !== false || strpos($message_lower, 'corporate') !== false ||
        strpos($message_lower, 'company') !== false || strpos($message_lower, 'organization') !== false) {
        return "We work with businesses and organizations! For corporate accounts, bulk orders, or business partnerships, please contact our business team at business@store.com.";
    }

    // Handle questions about careers/jobs
    if (strpos($message_lower, 'job') !== false || strpos($message_lower, 'career') !== false ||
        strpos($message_lower, 'work') !== false || strpos($message_lower, 'employment') !== false) {
        return "Interested in joining our team? Visit our careers page at store.com/careers to see current openings and learn about working with us!";
    }

    // Handle questions about partnerships/affiliates
    if (strpos($message_lower, 'partner') !== false || strpos($message_lower, 'affiliate') !== false ||
        strpos($message_lower, 'collaboration') !== false) {
        return "We're always interested in partnerships! For affiliate programs or business partnerships, please contact partnerships@store.com.";
    }

    // Default general question response - more comprehensive
    $general_responses = [
        "I'd be happy to help with that! Could you please provide more details about what you're looking for?",
        "That's a great question! Let me help you find the information you need. What specifically would you like to know?",
        "I'm here to assist you! Could you tell me more about what you're asking about?",
        "Let me help you with that. What would you like to know more about our products or services?",
        "I'm all ears! What's on your mind? Feel free to ask me anything.",
        "Great question! I'm here to help with whatever you need. What can I assist you with?",
        "I'm ready to help! What's your question or what would you like to know?",
        "Feel free to ask me anything - I'm here to help make your shopping experience wonderful!"
    ];
    return $general_responses[array_rand($general_responses)];
}

// Enhanced handler functions for comprehensive AI responses

function handleGreeting($pdo, $message, $user_id) {
    $hour = date('H');
    $time_greeting = '';

    if ($hour < 12) {
        $time_greeting = 'Good morning';
    } elseif ($hour < 17) {
        $time_greeting = 'Good afternoon';
    } else {
        $time_greeting = 'Good evening';
    }

    $greetings = [
        "$time_greeting! I'm your AI shopping assistant. How can I help you find the perfect products today?",
        "Hello there! Welcome to our store. I'm here to help you discover amazing products and answer any questions you have.",
        "Hi! Great to see you here. I'm your personal shopping assistant - what are you looking for today?",
        "$time_greeting! I'm excited to help you find exactly what you need. What brings you to our store today?"
    ];

    return $greetings[array_rand($greetings)];
}

function handleImageQuery($pdo, $message) {
    $product_name = extractProductName($message);
    if (empty($product_name)) {
        return "I'd love to show you some product images! Could you please tell me which product you're interested in seeing?";
    }

    try {
        $stmt = $pdo->prepare("SELECT product_id, name, image_url, price, stock_quantity, description FROM products WHERE LOWER(name) LIKE LOWER(?) AND stock_quantity > 0 LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            // Store product info for potential confirmation
            $_SESSION['pending_product'] = $product;

            $response = "Here's what I found for '$product_name':\n\n";
            $response .= "📸 **" . $product['name'] . "**\n";
            $response .= "💰 Price: $" . number_format($product['price'], 2) . "\n";
            $response .= "📦 Stock: " . $product['stock_quantity'] . " available\n\n";

            if (!empty($product['description'])) {
                $response .= "📝 " . $product['description'] . "\n\n";
            }

            $response .= "Would you like me to add this to your cart or show you similar products?";

            return $response;
        } else {
            return "I couldn't find any product matching '$product_name'. Could you please check the spelling or try a different product name? I can also show you products from specific categories if you'd like!";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving product images right now. Please try again in a moment, or let me know what other products you're interested in!";
    }
}

function handleOrderQuery($pdo, $message, $user_id) {
    $product_name = extractProductName($message);

    if (empty($product_name)) {
        return "I'd be happy to help you place an order! What product would you like to purchase? You can tell me the product name, and I'll guide you through the process.";
    }

    try {
        $stmt = $pdo->prepare("SELECT product_id, name, price, stock_quantity FROM products WHERE LOWER(name) LIKE LOWER(?) AND stock_quantity > 0 LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            // Store product info for confirmation
            $_SESSION['pending_product'] = $product;

            $response = "Great choice! I found '" . $product['name'] . "' for $" . number_format($product['price'], 2) . ".\n\n";
            $response .= "We have " . $product['stock_quantity'] . " units available.\n\n";
            $response .= "Would you like me to add this to your cart? Just say 'yes' or 'add it' to confirm!";

            return $response;
        } else {
            return "I couldn't find any product matching '$product_name' that's currently in stock. Could you please check the spelling or try a different product name?";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble processing your order request right now. Please try again later.";
    }
}

function handleProductConfirmation($pdo, $user_id) {
    if (!isset($_SESSION['pending_product'])) {
        return "I don't have any product ready for confirmation. What would you like to order?";
    }

    $product = $_SESSION['pending_product'];

    try {
        // Check if product is still available
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
        $stmt->execute([$product['product_id']]);
        $current_stock = $stmt->fetch();

        if (!$current_stock || $current_stock['stock_quantity'] <= 0) {
            unset($_SESSION['pending_product']);
            return "Sorry, '" . $product['name'] . "' is no longer available. Would you like me to help you find a similar product?";
        }

        // Add to cart (assuming there's a cart table)
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
        $stmt->execute([$user_id, $product['product_id']]);

        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - 1 WHERE product_id = ?");
        $stmt->execute([$product['product_id']]);

        unset($_SESSION['pending_product']);

        return "Perfect! I've added '" . $product['name'] . "' to your cart. You can proceed to checkout anytime. Is there anything else I can help you with?";
    } catch (Exception $e) {
        return "I had trouble adding that item to your cart. Please try again or contact customer support if the problem persists.";
    }
}

function handlePriceQuery($pdo, $message) {
    $product_name = extractProductName($message);

    if (empty($product_name)) {
        return "I'd be happy to help you with pricing information! Which product are you interested in?";
    }

    try {
        $stmt = $pdo->prepare("SELECT name, price, stock_quantity FROM products WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            $response = "The price for '" . $product['name'] . "' is $" . number_format($product['price'], 2) . ".";

            if ($product['stock_quantity'] > 0) {
                $response .= " We have " . $product['stock_quantity'] . " units available.";
            } else {
                $response .= " However, it's currently out of stock.";
            }

            return $response;
        } else {
            return "I couldn't find any product matching '$product_name'. Could you please check the spelling or provide more details?";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving pricing information right now. Please try again later.";
    }
}

function handleSalesQuery($pdo) {
    try {
        // Get products on sale (assuming there's a sale_price column or discount field)
        $stmt = $pdo->prepare("SELECT name, price, image_url, stock_quantity FROM products WHERE stock_quantity > 0 ORDER BY price ASC LIMIT 5");
        $stmt->execute();
        $products = $stmt->fetchAll();

        if (empty($products)) {
            return "I don't have any products on sale right now, but I can show you our current best deals! Would you like me to recommend some affordable options?";
        }

        $response = "Here are some great products you might be interested in:\n\n";

        foreach ($products as $product) {
            $response .= "🛍️ **" . $product['name'] . "**\n";
            $response .= "💰 Price: $" . number_format($product['price'], 2) . "\n";
            $response .= "📦 Stock: " . $product['stock_quantity'] . " available\n\n";
        }

        $response .= "Would you like me to show you more details about any of these products or help you add one to your cart?";

        return $response;
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving sale information right now. Please try again later.";
    }
}

function handleRecommendationQuery($pdo, $user_id) {
    try {
        // Get popular products or random recommendations
        $stmt = $pdo->prepare("SELECT name, price, image_url, stock_quantity FROM products WHERE stock_quantity > 0 ORDER BY RAND() LIMIT 3");
        $stmt->execute();
        $products = $stmt->fetchAll();

        if (empty($products)) {
            return "I'd love to recommend some products, but it looks like we're currently out of stock on most items. Please check back later!";
        }

        $response = "Based on popular choices, here are some recommendations:\n\n";

        foreach ($products as $product) {
            $response .= "⭐ **" . $product['name'] . "**\n";
            $response .= "💰 Price: $" . number_format($product['price'], 2) . "\n";
            $response .= "📦 Stock: " . $product['stock_quantity'] . " available\n\n";
        }

        $response .= "Would you like me to tell you more about any of these products or help you add one to your cart?";

        return $response;
    } catch (Exception $e) {
        return "Sorry, I had trouble generating recommendations right now. Please try again later.";
    }
}

function handleOrderStatusQuery($pdo, $user_id) {
    try {
        // Get user's recent orders
        $stmt = $pdo->prepare("SELECT order_id, status, order_date FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 3");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            return "I don't see any orders in your account yet. Would you like me to help you place your first order?";
        }

        $response = "Here are your recent orders:\n\n";

        foreach ($orders as $order) {
            $response .= "📦 Order #" . $order['order_id'] . "\n";
            $response .= "📅 Date: " . date('M j, Y', strtotime($order['order_date'])) . "\n";
            $response .= "📊 Status: " . ucfirst($order['status']) . "\n\n";
        }

        $response .= "Would you like me to provide more details about any of these orders?";

        return $response;
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving your order information right now. Please try again later or contact customer support.";
    }
}

function handleChatHistory($pdo, $user_id) {
    try {
        // Get recent chat messages for this user
        $stmt = $pdo->prepare("
            SELECT cm.message_text, cm.message_type, cm.created_at
            FROM chat_messages cm
            JOIN chat_sessions cs ON cm.session_id = cs.session_id
            WHERE cs.user_id = ?
            ORDER BY cm.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $messages = $stmt->fetchAll();

        if (empty($messages)) {
            return "I don't have any previous conversation history with you. This is our first chat! How can I help you today?";
        }

        $response = "Here's a summary of our recent conversation:\n\n";

        foreach (array_reverse($messages) as $message) {
            $type = $message['message_type'] === 'user' ? 'You' : 'Me';
            $time = date('H:i', strtotime($message['created_at']));
            $response .= "[$time] $type: " . substr($message['message_text'], 0, 100) . (strlen($message['message_text']) > 100 ? '...' : '') . "\n";
        }

        $response .= "\nWhat else can I help you with today?";

        return $response;
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving our conversation history right now. How else can I assist you?";
    }
}

function handleThankYou($pdo, $user_id) {
    $thank_responses = [
        "You're very welcome! I'm always here to help make your shopping experience amazing.",
        "My pleasure! Don't hesitate to reach out if you need anything else.",
        "You're welcome! I'm glad I could help. Happy shopping!",
        "Anytime! I'm here 24/7 to assist you with all your shopping needs.",
        "You're so welcome! Feel free to ask me anything else about our products or services."
    ];

    return $thank_responses[array_rand($thank_responses)];
}

function handleGeneralProductQuery($pdo, $product_name, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, name, price, stock_quantity, description FROM products WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            // Store for potential confirmation
            $_SESSION['pending_product'] = $product;

            $response = "I found information about '" . $product['name'] . "':\n\n";
            $response .= "💰 Price: $" . number_format($product['price'], 2) . "\n";

            if ($product['stock_quantity'] > 0) {
                $response .= "📦 Available: " . $product['stock_quantity'] . " units in stock\n";
            } else {
                $response .= "📦 Currently: Out of stock\n";
            }

            if (!empty($product['description'])) {
                $response .= "📝 Description: " . $product['description'] . "\n";
            }

            $response .= "\nWould you like me to add this to your cart or show you similar products?";

            return $response;
        } else {
            return "I couldn't find any product matching '$product_name'. Could you please check the spelling or try a different search term?";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving product information right now. Please try again later.";
    }
}

function handleCategoryQuery($pdo, $category) {
    try {
        // Assuming products have a category field
        $stmt = $pdo->prepare("SELECT name, price, stock_quantity FROM products WHERE LOWER(category) LIKE LOWER(?) AND stock_quantity > 0 LIMIT 5");
        $stmt->execute(['%' . $category . '%']);
        $products = $stmt->fetchAll();

        if (empty($products)) {
            return "I couldn't find any products in the '$category' category that are currently in stock. Would you like me to show you products from other categories?";
        }

        $response = "Here are some products from the '$category' category:\n\n";

        foreach ($products as $product) {
            $response .= "🛍️ **" . $product['name'] . "**\n";
            $response .= "💰 Price: $" . number_format($product['price'], 2) . "\n";
            $response .= "📦 Stock: " . $product['stock_quantity'] . " available\n\n";
        }

        $response .= "Would you like me to tell you more about any of these products?";

        return $response;
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving category information right now. Please try again later.";
    }
}

function extractProductName($message) {
    $message_lower = strtolower($message);

    // Common patterns for product names
    $patterns = [
        '/(?:about|tell me about|show me|find|looking for|search for|where can i find|do you have)\s+(.+?)(?:\?|$|\.|\!)/i',
        '/(?:price of|cost of|how much is)\s+(.+?)(?:\?|$|\.|\!)/i',
        '/(?:buy|purchase|order|get)\s+(.+?)(?:\?|$|\.|\!)/i',
        '/(?:stock of|availability of|in stock)\s+(.+?)(?:\?|$|\.|\!)/i',
        '/(?:picture of|image of|photo of|show)\s+(.+?)(?:\?|$|\.|\!)/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message, $matches)) {
            $product_name = trim($matches[1]);

            // Clean up common words that might not be part of the product name
            $stop_words = ['the', 'a', 'an', 'this', 'that', 'these', 'those', 'i', 'you', 'it', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'please', 'thank', 'thanks'];

            $words = explode(' ', $product_name);
            $filtered_words = array_filter($words, function($word) use ($stop_words) {
                return !in_array(strtolower($word), $stop_words) && strlen($word) > 1;
            });

            return implode(' ', $filtered_words);
        }
    }

    // If no pattern matches, try to extract nouns (simple approach)
    $words = explode(' ', $message_lower);
    $potential_product = '';

    foreach ($words as $word) {
        if (strlen($word) > 2 && !in_array($word, ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'i', 'you', 'it', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can'])) {
            $potential_product .= $word . ' ';
        }
    }

    return trim($potential_product);
}

function extractCategoryFromMessage($message) {
    $message_lower = strtolower($message);

    $categories = [
        'electronics', 'electronic', 'phone', 'phones', 'mobile', 'cell',
        'computer', 'computers', 'laptop', 'laptops', 'desktop', 'pc',
        'clothing', 'clothes', 'shirt', 'shirts', 'pants', 'jeans', 'dress', 'dresses',
        'shoes', 'shoe', 'sneakers', 'boots', 'sandals',
        'home', 'house', 'kitchen', 'bathroom', 'bedroom', 'living room',
        'furniture', 'chair', 'table', 'sofa', 'bed',
        'sports', 'sport', 'fitness', 'gym', 'exercise', 'workout',
        'books', 'book', 'novel', 'magazine', 'textbook',
        'toys', 'toy', 'games', 'game', 'puzzle', 'board game',
        'beauty', 'cosmetics', 'makeup', 'skincare', 'haircare',
        'health', 'medical', 'supplements', 'vitamins',
        'automotive', 'car', 'auto', 'vehicle', 'motorcycle',
        'garden', 'gardening', 'outdoor', 'patio', 'lawn',
        'pet', 'pets', 'dog', 'cat', 'animal', 'pet supplies'
    ];

    foreach ($categories as $category) {
        if (strpos($message_lower, $category) !== false) {
            return $category;
        }
    }

    return '';
}

