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

// Handle POST requests for chat messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = trim($input['message'] ?? '');

    if (empty($message)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }

    // Process the AI response with product image and ordering capabilities
    $response = processAIResponse($pdo, $message, $_SESSION['user_id']);

    echo json_encode([
        'response' => $response,
        'timestamp' => date('H:i')
    ]);
    exit;
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
    if (strpos($message_lower, 'price') !== false || strpos($message_lower, 'cost') !== false) {
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
    if (strpos($message_lower, 'recommend') !== false || strpos($message_lower, 'suggest') !== false) {
        return handleRecommendationQuery($pdo, $user_id);
    }

    // Order status queries
    if (strpos($message_lower, 'order status') !== false || strpos($message_lower, 'tracking') !== false ||
        strpos($message_lower, 'delivery') !== false) {
        return handleOrderStatusQuery($pdo, $user_id);
    }

    // Chat history
    if (strpos($message_lower, 'history') !== false || strpos($message_lower, 'previous') !== false ||
        strpos($message_lower, 'before') !== false) {
        return handleChatHistory($pdo, $user_id);
    }

    // Thank you responses
    if (strpos($message_lower, 'thank') !== false) {
        return handleThankYou($pdo, $user_id);
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

function handleImageQuery($pdo, $message) {
    $product_name = extractProductName($message);
    if (empty($product_name)) {
        return "Please specify the product name you want to see the picture of.";
    }

    try {
        $stmt = $pdo->prepare("SELECT product_id, name, image_url, price, stock_quantity FROM products WHERE LOWER(name) LIKE LOWER(?) AND stock_quantity > 0 LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            // Store product info for potential confirmation
            $_SESSION['pending_product'] = $product;

            $image_filename = basename($product['image_url']);
            $image_url = '../images/' . $image_filename;
            $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 200px; height: auto; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";
            return "Here's an image of the {$product['name']}:<br><br>{$image_html}<br><br>Price: R " . number_format($product['price'], 2) . "<br>Stock: {$product['stock_quantity']} available<br><br>Would you like me to add this to your cart?";
        }
        return "I couldn't find the product image for '{$product_name}', but I can help you find other products or information.";
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving the product image. Please try again later.";
    }
}


function handlePriceQuery($pdo, $message) {
    $product_name = extractProductName($message);
    if (empty($product_name)) {
        return "Please specify the product name you want pricing information for.";
    }

    try {
        $stmt = $pdo->prepare("SELECT price, discount_percent, is_active FROM products LEFT JOIN promotions ON products.product_id = promotions.product_id AND promotions.is_active = true WHERE LOWER(name) LIKE LOWER(?) AND stock_quantity > 0 LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            $price = $product['price'];
            $response = "The price of {$product_name} is R " . number_format($price, 2);
            if ($product['is_active'] && $product['discount_percent'] > 0) {
                $discounted_price = $price * (1 - $product['discount_percent'] / 100);
                $response .= ", but it's currently on sale for R " . number_format($discounted_price, 2) . " ({$product['discount_percent']}% off)";
            }
            return $response . ".";
        } else {
            return "I couldn't find pricing information for '{$product_name}'. Please check the product name or try another item.";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving pricing information. Please try again later.";
    }
}

function handleSalesQuery($pdo) {
    try {
        // Get promotional products with details
        $stmt = $pdo->prepare("
            SELECT p.product_id, p.name, p.price, p.image_url, p.stock_quantity,
                   pr.discount_percent, pr.end_date, pr.promotion_type
            FROM products p
            JOIN promotions pr ON p.product_id = pr.product_id
            WHERE pr.is_active = true AND p.stock_quantity > 0
            AND pr.start_date <= CURRENT_DATE
            AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
            ORDER BY pr.discount_percent DESC
            LIMIT 5
        ");
        $stmt->execute();
        $promo_products = $stmt->fetchAll();

        if (!empty($promo_products)) {
            $response = "🎉 Great news! Here are our current promotions with amazing discounts:\n\n";

            foreach ($promo_products as $product) {
                $original_price = $product['price'];
                $discount_percent = $product['discount_percent'];
                $discounted_price = $original_price * (1 - $discount_percent / 100);

                // Show product image
                $image_filename = basename($product['image_url']);
                $image_url = '../images/' . $image_filename;
                $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 150px; height: auto; border-radius: 8px; margin: 5px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";

                $response .= "{$image_html}\n";
                $response .= "🏷️ <strong>{$product['name']}</strong>\n";
                $response .= "💰 <del>R " . number_format($original_price, 2) . "</del> → <span style='color: #ff6b35; font-weight: bold;'>R " . number_format($discounted_price, 2) . "</span>\n";
                $response .= "🔥 {$discount_percent}% OFF!\n";
                $response .= "📦 {$product['stock_quantity']} in stock\n";

                // Show promotion end date if available
                if ($product['end_date']) {
                    $end_date = new DateTime($product['end_date']);
                    $response .= "⏰ Sale ends: " . $end_date->format('M j, Y') . "\n";
                }

                $response .= "\n";
            }

            $response .= "Would you like me to add any of these amazing deals to your cart? Just let me know which one! 🛒";

            return $response;
        } else {
            return "There are no active promotions at the moment, but I can help you find some great products! What are you looking for?";
        }
    } catch (Exception $e) {
        return "Sorry, I couldn't retrieve promotion information right now. Please try again later.";
    }
}

function handleRecommendationQuery($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM products WHERE stock_quantity > 0 ORDER BY created_at DESC LIMIT 3");
        $stmt->execute();
        $products = $stmt->fetchAll();

        if (!empty($products)) {
            $product_names = array_column($products, 'name');
            $product_list = implode(', ', $product_names);
            return "Here are some products you might like: {$product_list}. Would you like to know more about any of these?";
        } else {
            return "I couldn't find any product recommendations at the moment.";
        }
    } catch (Exception $e) {
        return "Sorry, I couldn't retrieve product recommendations right now.";
    }
}

function handleOrderStatusQuery($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT order_id, status, order_date FROM orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $order = $stmt->fetch();

        if ($order) {
            return "Your latest order #{$order['order_id']} placed on {$order['order_date']} is currently '{$order['status']}'.";
        } else {
            return "You have no recent orders.";
        }
    } catch (Exception $e) {
        return "Sorry, I couldn't retrieve your order status right now.";
    }
}

function getRelatedProducts($pdo, $category, $exclude_product_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT product_id, name, price, image_url, discount_percent, is_active
            FROM products
            LEFT JOIN promotions ON products.product_id = promotions.product_id
            AND promotions.is_active = true AND promotions.start_date <= CURRENT_DATE
            AND (promotions.end_date IS NULL OR promotions.end_date >= CURRENT_DATE)
            WHERE category = ? AND product_id != ? AND stock_quantity > 0
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$category, $exclude_product_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function handleGeneralProductQuery($pdo, $product_name, $user_id) {
    try {
        // Debug: Log the query being executed
        error_log("AI Support Debug - Product Query: Looking for '{$product_name}'");

        // First, try to find exact or similar products
        $stmt = $pdo->prepare("SELECT products.product_id, name, price, stock_quantity, category, image_url, discount_percent, is_active FROM products LEFT JOIN promotions ON products.product_id = promotions.product_id AND promotions.is_active = true WHERE LOWER(name) LIKE LOWER(?) AND stock_quantity > 0 ORDER BY name ASC LIMIT 6");
        $stmt->execute(['%' . $product_name . '%']);
        $products = $stmt->fetchAll();

        if (!empty($products)) {
            error_log("AI Support Debug - Products found: " . count($products) . " products");

            // If only one product found, show detailed info
            if (count($products) === 1) {
                $product = $products[0];
                $price = $product['price'];
                if ($product['is_active'] && $product['discount_percent'] > 0) {
                    $price = $price * (1 - $product['discount_percent'] / 100);
                }

                $response = "Here's information about the {$product['name']}:\n\n";

                // Show product image
                $image_filename = basename($product['image_url']);
                $image_url = '../images/' . $image_filename;
                $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 200px; height: auto; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";
                $response .= "{$image_html}\n\n";

                $response .= "Price: R " . number_format($price, 2) . "\n";
                $response .= "Stock: {$product['stock_quantity']} available\n\n";

                // Get related products based on category
                $related_products = getRelatedProducts($pdo, $product['category'], $product['product_id']);

                if (!empty($related_products)) {
                    $response .= "You might also like these related products:\n\n";
                    foreach ($related_products as $related) {
                        $related_price = $related['price'];
                        if ($related['is_active'] && $related['discount_percent'] > 0) {
                            $related_price = $related_price * (1 - $related['discount_percent'] / 100);
                        }

                        $related_image_filename = basename($related['image_url']);
                        $related_image_url = '../images/' . $related_image_filename;
                        $related_image_html = "<img src='{$related_image_url}' alt='" . htmlspecialchars($related['name']) . "' style='max-width: 150px; height: auto; border-radius: 6px; margin: 5px 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);' />";
                        $response .= "{$related_image_html}\n";
                        $response .= "{$related['name']}\n";
                        $response .= "Price: R " . number_format($related_price, 2) . "\n\n";
                    }
                }

                $response .= "Would you like me to add this to your cart?";
                return $response;
            } else {
                // Multiple products found - show all of them
                $response = "I found several products matching '{$product_name}':\n\n";

                $counter = 1;
                foreach ($products as $product) {
                    $price = $product['price'];
                    if ($product['is_active'] && $product['discount_percent'] > 0) {
                        $price = $price * (1 - $product['discount_percent'] / 100);
                    }

                    // Show product image
                    $image_filename = basename($product['image_url']);
                    $image_url = '../images/' . $image_filename;
                    $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 180px; height: auto; border-radius: 8px; margin: 8px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";

                    $response .= "{$counter}. {$image_html}\n";
                    $response .= "🏷️ <strong>{$product['name']}</strong>\n";
                    $response .= "💰 R " . number_format($price, 2) . "\n";
                    $response .= "📦 {$product['stock_quantity']} in stock\n\n";

                    $counter++;
                }

                $response .= "Which one would you like me to add to your cart? Just tell me the number or the product name! 🛒";
                return $response;
            }
        } else {
            error_log("AI Support Debug - No product found for: '{$product_name}'");
            return "I couldn't find the product '{$product_name}' in our store. Please check the spelling or try a different product name.";
        }
    } catch (Exception $e) {
        error_log("AI Support Error - handleGeneralProductQuery: " . $e->getMessage());
        return "Sorry, I had trouble retrieving product information. Error: " . $e->getMessage();
    }
}

function handleCategoryQuery($pdo, $category) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, name, price, image_url, discount_percent, is_active FROM products LEFT JOIN promotions ON products.product_id = promotions.product_id AND promotions.is_active = true WHERE LOWER(category) LIKE LOWER(?) AND stock_quantity > 0 ORDER BY created_at DESC LIMIT 6");
        $stmt->execute(['%' . $category . '%']);
        $products = $stmt->fetchAll();

        if (!empty($products)) {
            $response = "Here are some products in the {$category} category:\n\n";

            $counter = 1;
            foreach ($products as $product) {
                $price = $product['price'];
                if ($product['is_active'] && $product['discount_percent'] > 0) {
                    $price = $price * (1 - $product['discount_percent'] / 100);
                }

                // Show product image
                $image_filename = basename($product['image_url']);
                $image_url = '../images/' . $image_filename;
                $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 180px; height: auto; border-radius: 8px; margin: 8px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";

                $response .= "{$counter}. {$image_html}\n";
                $response .= "🏷️ <strong>{$product['name']}</strong>\n";
                $response .= "💰 R " . number_format($price, 2) . "\n";
                $response .= "📦 {$product['stock_quantity']} in stock\n\n";

                $counter++;
            }

            $response .= "Which one would you like me to add to your cart? Just tell me the number or the product name! 🛒";
            return $response;
        } else {
            return "I couldn't find any products in the '{$category}' category. Please try a different category.";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving category information. Please try again later.";
    }
}

function extractCategoryFromMessage($message) {
    $message_lower = strtolower($message);

    // Common category keywords
    $categories = [
        'electronics', 'electronic', 'phone', 'phones', 'mobile', 'cellphone', 'cell phones',
        'clothing', 'clothes', 'fashion', 'apparel', 'wear', 'shoes', 'shoe', 'boots', 'boot',
        'sports', 'sport', 'fitness', 'gym', 'exercise', 'soccer', 'football', 'basketball', 'tennis',
        'home', 'house', 'kitchen', 'bathroom', 'bedroom', 'living room', 'garden', 'outdoor',
        'books', 'book', 'reading', 'education', 'study', 'school', 'university',
        'beauty', 'cosmetics', 'makeup', 'skincare', 'hair', 'personal care',
        'toys', 'toy', 'games', 'gaming', 'kids', 'children', 'baby',
        'automotive', 'car', 'vehicle', 'auto', 'motorcycle', 'bike',
        'health', 'medical', 'wellness', 'supplements', 'vitamins',
        'jewelry', 'jewellery', 'watches', 'watch', 'accessories', 'bags', 'handbags'
    ];

    foreach ($categories as $category) {
        if (strpos($message_lower, $category) !== false) {
            return $category;
        }
    }

    return '';
}

function extractProductName($message) {
    $patterns = [
        '/picture of (.+)/i',
        '/image of (.+)/i',
        '/photo of (.+)/i',
        '/show me (.+)/i',
        '/can i see (.+)/i',
        '/let me see (.+)/i',
        '/have (.+)/i',
        '/order (.+)/i',
        '/buy (.+)/i',
        '/purchase (.+)/i',
        '/add to cart (.+)/i',
        '/price of (.+)/i',
        '/cost of (.+)/i',
        '/tell me about (.+)/i',
        '/information about (.+)/i',
        '/details about (.+)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message, $matches)) {
            return trim($matches[1]);
        }
    }

    // If no specific pattern matches, treat the entire message as a potential product name
    // Remove common stop words and return the cleaned message
    $stop_words = ['i', 'want', 'need', 'looking', 'for', 'a', 'an', 'the', 'some', 'any', 'please', 'can', 'you', 'show', 'me', 'tell', 'about'];
    $words = explode(' ', strtolower(trim($message)));
    $filtered_words = array_filter($words, function($word) use ($stop_words) {
        return !in_array($word, $stop_words) && strlen($word) > 2;
    });

    if (!empty($filtered_words)) {
        return implode(' ', $filtered_words);
    }

    return '';
}

// Enhanced greeting function - behave like a real human
function handleGreeting($pdo, $message, $user_id) {
    $message_lower = strtolower($message);
    $current_hour = date('H');
    $greeting_responses = [];

    // Time-based greetings
    if ($current_hour >= 5 && $current_hour < 12) {
        $greeting_responses = [
            "Good morning! ☀️ Hope you're having a great start to your day! How can I help you with your shopping today?",
            "Morning! 🌅 Ready to find some amazing products? What are you looking for?",
            "Good morning! I'm here and ready to help you shop. What can I assist you with today?"
        ];
    } elseif ($current_hour >= 12 && $current_hour < 17) {
        $greeting_responses = [
            "Good afternoon! 🌤️ How's your day going? I'm here to help you find the perfect products!",
            "Afternoon! 😊 What can I help you discover in our store today?",
            "Good afternoon! Ready to find some great deals? How can I assist you?"
        ];
    } elseif ($current_hour >= 17 && $current_hour < 22) {
        $greeting_responses = [
            "Good evening! 🌙 Hope you're having a wonderful evening. What can I help you find?",
            "Evening! 🌆 I'm here to make your shopping experience great. What are you looking for?",
            "Good evening! Ready to browse our amazing products? How can I help?"
        ];
    } else {
        $greeting_responses = [
            "Hello there! 🌙 Even at this hour, I'm here to help you shop. What can I assist you with?",
            "Hi! I'm always here to help with your shopping needs. What are you looking for?",
            "Hello! Don't worry about the time - I'm here to help you find what you need!"
        ];
    }

    // Personal greetings
    if (strpos($message_lower, 'how are you') !== false) {
        $personal_responses = [
            "I'm doing great, thank you for asking! 😊 I'm excited to help you find some amazing products today!",
            "I'm wonderful! Thanks for asking. I'm here and ready to make your shopping experience fantastic!",
            "I'm fantastic! 😄 It's always a good day when I get to help customers like you find what they need!"
        ];
        return $personal_responses[array_rand($personal_responses)];
    }

    if (strpos($message_lower, 'how is your day') !== false) {
        $day_responses = [
            "My day's going wonderfully! 🌟 Helping customers like you is what makes it great. What can I help you find?",
            "It's been a fantastic day so far! 😊 I'm loving helping people discover amazing products. How can I assist you?",
            "My day's been excellent! 💫 I'm here to make your shopping experience just as great. What are you looking for?"
        ];
        return $day_responses[array_rand($day_responses)];
    }

    if (strpos($message_lower, 'how did you sleep') !== false) {
        $sleep_responses = [
            "I slept great! 💤 As an AI, I'm always well-rested and ready to help you shop! What can I assist you with today?",
            "I had a wonderful 'sleep' - I'm always energized and ready to help! 😊 What are you looking for?",
            "As an AI, I don't sleep, but I'm always fully charged and ready to help you find amazing products! What can I assist you with?"
        ];
        return $sleep_responses[array_rand($sleep_responses)];
    }

    // Default greeting response
    return $greeting_responses[array_rand($greeting_responses)];
}

// Chat history function
function handleChatHistory($pdo, $user_id) {
    try {
        // For now, we'll show recent order history as chat history isn't stored
        // In a real implementation, you'd store chat messages in a database
        $stmt = $pdo->prepare("
            SELECT order_id, status, order_date, total_amount
            FROM orders
            WHERE customer_id = ?
            ORDER BY order_date DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll();

        if (!empty($orders)) {
            $response = "Here's your recent order history:\n\n";
            foreach ($orders as $order) {
                $response .= "📦 Order #{$order['order_id']}\n";
                $response .= "📅 Date: {$order['order_date']}\n";
                $response .= "💰 Total: R " . number_format($order['total_amount'], 2) . "\n";
                $response .= "📊 Status: {$order['status']}\n\n";
            }
            $response .= "Is there anything specific you'd like to know about these orders?";
            return $response;
        } else {
            return "You don't have any previous orders yet. Would you like me to help you find some great products to get started?";
        }
    } catch (Exception $e) {
        return "I couldn't retrieve your chat history right now. Is there something specific you'd like help with?";
    }
}

// Enhanced thank you function
function handleThankYou($pdo, $user_id) {
    $thank_responses = [
        "You're so welcome! 😊 It was my pleasure to help you. Is there anything else I can assist you with today?",
        "My pleasure! I'm always happy to help. Feel free to ask me anything else about our products or services!",
        "You're very welcome! 😄 I'm here whenever you need help with shopping, orders, or anything else!",
        "Anytime! It's what I'm here for. Don't hesitate to ask if you need help with anything else!",
        "You're welcome! I'm glad I could help. What else can I assist you with today?"
    ];
    return $thank_responses[array_rand($thank_responses)];
}

// Enhanced order query with category browsing and accessory recommendations
function handleOrderQuery($pdo, $message, $user_id) {
    $product_name = extractProductName($message);
    $message_lower = strtolower($message);

    // Check if this is a category-based order request (like "soccer boots" which should show sports category)
    $category = extractCategoryFromMessage($message);
    if (!empty($category) && (strpos($message_lower, 'order') !== false || strpos($message_lower, 'buy') !== false || strpos($message_lower, 'want') !== false)) {
        // This is a category-based order request - show category products first
        return handleCategoryOrderQuery($pdo, $category, $user_id);
    }

    if (empty($product_name)) {
        return "Please specify the product name you want to order.";
    }

    try {
        // Debug: Log the order attempt
        error_log("AI Support Debug - Order Query: User {$user_id} trying to order '{$product_name}'");

        // First, get the main product details
        $stmt = $pdo->prepare("SELECT products.product_id, name, price, stock_quantity, category, image_url, discount_percent, is_active FROM products LEFT JOIN promotions ON products.product_id = promotions.product_id AND promotions.is_active = true WHERE LOWER(name) LIKE LOWER(?) AND stock_quantity > 0 LIMIT 1");
        $stmt->execute(['%' . $product_name . '%']);
        $product = $stmt->fetch();

        if ($product) {
            error_log("AI Support Debug - Product found for order: {$product['name']} (ID: {$product['product_id']})");

            $price = $product['price'];
            if ($product['is_active'] && $product['discount_percent'] > 0) {
                $price = $price * (1 - $product['discount_percent'] / 100);
            }

            // Add to cart
            $cart_stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, 1) ON CONFLICT (customer_id, product_id) DO UPDATE SET quantity = cart.quantity + 1");
            $cart_stmt->execute([$user_id, $product['product_id']]);

            error_log("AI Support Debug - Added to cart: User {$user_id} added product {$product['product_id']}");

            $response = "Perfect! I've added the {$product['name']} to your cart at R " . number_format($price, 2) . ".<br/><br/>";

            // Show product image
            $image_url = $product['image_url'];
            if (strpos($image_url, 'images/') === 0) {
                $image_url = '../' . $image_url;
            }
            $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 200px; height: auto; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";
            $response .= "{$image_html}<br/><br/>";

            // Special accessory recommendations based on product type
            $accessories = getAccessoryRecommendations($pdo, $product['name'], $product['category']);

            if (!empty($accessories)) {
                $response .= "✨ Based on your purchase, you might also love these accessories:\n\n";
                foreach ($accessories as $accessory) {
                    $acc_price = $accessory['price'];
                    if ($accessory['is_active'] && $accessory['discount_percent'] > 0) {
                        $acc_price = $acc_price * (1 - $accessory['discount_percent'] / 100);
                    }

                    $acc_image_url = $accessory['image_url'];
                    if (strpos($acc_image_url, 'images/') === 0) {
                        $acc_image_url = '../' . $acc_image_url;
                    }
                    $acc_image_html = "<img src='{$acc_image_url}' alt='" . htmlspecialchars($accessory['name']) . "' style='max-width: 120px; height: auto; border-radius: 6px; margin: 5px 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);' />";
                    $response .= "{$acc_image_html}<br/>";
                    $response .= "🔸 {$accessory['name']}<br/>";
                    $response .= "💰 R " . number_format($acc_price, 2) . "<br/><br/>";
                }
                $response .= "Would you like me to add any of these accessories to your cart as well?\n\n";
            }

            // Get related products based on category
            $related_products = getRelatedProducts($pdo, $product['category'], $product['product_id']);

            if (!empty($related_products)) {
                $response .= "You might also like these related products:\n\n";
                foreach ($related_products as $related) {
                    $related_price = $related['price'];
                    if ($related['is_active'] && $related['discount_percent'] > 0) {
                        $related_price = $related_price * (1 - $related['discount_percent'] / 100);
                    }

                    $related_image_url = $related['image_url'];
                    if (strpos($related_image_url, 'images/') === 0) {
                        $related_image_url = '../' . $related_image_url;
                    }
                    $related_image_html = "<img src='{$related_image_url}' alt='" . htmlspecialchars($related['name']) . "' style='max-width: 150px; height: auto; border-radius: 6px; margin: 5px 0; box-shadow: 0 2px 6px rgba(0,0,0,0.1);' />";
                    $response .= "{$related_image_html}<br/>";
                    $response .= "{$related['name']}<br/>";
                    $response .= "Price: R " . number_format($related_price, 2) . "<br/><br/>";
                }
                $response .= "Would you like me to add any of these related items to your cart as well?";
            } else {
                $response .= "Would you like to continue shopping or proceed to checkout?";
            }

            return $response;
        } else {
            error_log("AI Support Debug - No product found for order: '{$product_name}'");
            return "I couldn't find the product '{$product_name}' in stock. Please check the spelling or try another item.";
        }
    } catch (Exception $e) {
        error_log("AI Support Error - handleOrderQuery: " . $e->getMessage());
        return "Sorry, I encountered an issue while adding the product to your cart. Error: " . $e->getMessage();
    }
}

// New function to handle category-based order requests
function handleCategoryOrderQuery($pdo, $category, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, name, price, image_url, discount_percent, is_active FROM products LEFT JOIN promotions ON products.product_id = promotions.product_id AND promotions.is_active = true WHERE LOWER(category) LIKE LOWER(?) AND stock_quantity > 0 ORDER BY created_at DESC LIMIT 6");
        $stmt->execute(['%' . $category . '%']);
        $products = $stmt->fetchAll();

        if (!empty($products)) {
            $response = "Great! Here are some {$category} products you might be interested in:\n\n";

            $counter = 1;
            foreach ($products as $product) {
                $price = $product['price'];
                if ($product['is_active'] && $product['discount_percent'] > 0) {
                    $price = $price * (1 - $product['discount_percent'] / 100);
                }

                // Show product image
                $image_url = $product['image_url'];
                if (strpos($image_url, 'images/') === 0) {
                    $image_url = '../' . $image_url;
                }
                $image_html = "<img src='{$image_url}' alt='" . htmlspecialchars($product['name']) . "' style='max-width: 180px; height: auto; border-radius: 8px; margin: 8px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);' />";

                $response .= "{$counter}. {$image_html}\n";
                $response .= "🏷️ <strong>{$product['name']}</strong>\n";
                $response .= "💰 R " . number_format($price, 2) . "\n\n";

                $counter++;
            }

            $response .= "Which one would you like me to add to your cart? Just tell me the number or the product name! 🛒";

            return $response;
        } else {
            return "I couldn't find any products in the '{$category}' category right now. Would you like to try a different category or search for something specific?";
        }
    } catch (Exception $e) {
        return "Sorry, I had trouble retrieving the category information. Please try again later.";
    }
}

// Handle product confirmation (when user says "yes" to add to cart)
function handleProductConfirmation($pdo, $user_id) {
    if (!isset($_SESSION['pending_product'])) {
        return "I don't have a product in mind to add to your cart. What would you like to add?";
    }

    $product = $_SESSION['pending_product'];

    try {
        // Add to cart using PostgreSQL syntax
        $cart_stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, 1) ON CONFLICT (customer_id, product_id) DO UPDATE SET quantity = cart.quantity + 1");
        $cart_stmt->execute([$user_id, $product['product_id']]);

        // Clear the pending product
        unset($_SESSION['pending_product']);

        $price = $product['price'];
        if (isset($product['discount_percent']) && $product['discount_percent'] > 0) {
            $price = $price * (1 - $product['discount_percent'] / 100);
        }

        return "Perfect! I've added the {$product['name']} to your cart at R " . number_format($price, 2) . ". Would you like to continue shopping or proceed to checkout?";
    } catch (Exception $e) {
        return "Sorry, I encountered an issue while adding the product to your cart. Error: " . $e->getMessage();
    }
}

// Get accessory recommendations based on product type
function getAccessoryRecommendations($pdo, $product_name, $category) {
    try {
        $product_name_lower = strtolower($product_name);
        $accessories = [];

        // Phone accessories
        if (strpos($product_name_lower, 'phone') !== false || strpos($product_name_lower, 'iphone') !== false ||
            strpos($product_name_lower, 'samsung') !== false || strpos($product_name_lower, 'mobile') !== false ||
            strpos($category, 'phone') !== false) {

            $stmt = $pdo->prepare("
                SELECT product_id, name, price, image_url, discount_percent, is_active
                FROM products
                LEFT JOIN promotions ON products.product_id = promotions.product_id
                AND promotions.is_active = true AND promotions.start_date <= CURRENT_DATE
                AND (promotions.end_date IS NULL OR promotions.end_date >= CURRENT_DATE)
                WHERE (LOWER(name) LIKE '%case%' OR LOWER(name) LIKE '%pouch%' OR LOWER(name) LIKE '%charger%' OR LOWER(name) LIKE '%screen protector%')
                AND stock_quantity > 0
                ORDER BY created_at DESC
                LIMIT 2
            ");
            $stmt->execute();
            $accessories = $stmt->fetchAll();
        }

        // Laptop accessories
        elseif (strpos($product_name_lower, 'laptop') !== false || strpos($product_name_lower, 'computer') !== false) {
            $stmt = $pdo->prepare("
                SELECT product_id, name, price, image_url, discount_percent, is_active
                FROM products
                LEFT JOIN promotions ON products.product_id = promotions.product_id
                AND promotions.is_active = true AND promotions.start_date <= CURRENT_DATE
                AND (promotions.end_date IS NULL OR promotions.end_date >= CURRENT_DATE)
                WHERE (LOWER(name) LIKE '%mouse%' OR LOWER(name) LIKE '%keyboard%' OR LOWER(name) LIKE '%bag%' OR LOWER(name) LIKE '%cooling%')
                AND stock_quantity > 0
                ORDER BY created_at DESC
                LIMIT 2
            ");
            $stmt->execute();
            $accessories = $stmt->fetchAll();
        }

        return $accessories;
    } catch (Exception $e) {
        return [];
    }
}
?>
