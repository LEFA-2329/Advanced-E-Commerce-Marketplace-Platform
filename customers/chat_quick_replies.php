<?php
session_start();
require_once '../db_connection.php';

// Set CORS headers
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
    echo json_encode(['error' => 'Please log in to use this feature']);
    exit;
}

// Handle GET request for quick replies based on user input
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_input = trim($_GET['input'] ?? '');

    if (empty($user_input)) {
        // Return general quick replies
        $quick_replies = getGeneralQuickReplies($pdo);
    } else {
        // Return contextual quick replies based on user input
        $quick_replies = getContextualQuickReplies($pdo, $user_input);
    }

    echo json_encode(['quick_replies' => $quick_replies]);
    exit;
}

// Handle POST request to track quick reply usage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $reply_id = intval($input['reply_id'] ?? 0);
    $used = $input['used'] ?? false;

    if ($reply_id > 0) {
        trackQuickReplyUsage($pdo, $reply_id, $used);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid reply ID']);
    }
    exit;
}

function getGeneralQuickReplies($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT reply_id, reply_text, reply_category
            FROM chat_quick_replies
            WHERE is_active = true
            ORDER BY usage_count DESC, created_at DESC
            LIMIT 6
        ");
        $stmt->execute();
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($reply) {
            return [
                'id' => $reply['reply_id'],
                'text' => $reply['reply_text'],
                'category' => $reply['reply_category']
            ];
        }, $replies);
    } catch (Exception $e) {
        return [];
    }
}

function getContextualQuickReplies($pdo, $user_input) {
    $input_lower = strtolower($user_input);

    try {
        // Find quick replies where user input matches trigger keywords
        $stmt = $pdo->prepare("
            SELECT reply_id, reply_text, reply_category, trigger_keywords
            FROM chat_quick_replies
            WHERE is_active = true
            ORDER BY usage_count DESC
        ");
        $stmt->execute();
        $all_replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $contextual_replies = [];
        $general_replies = [];

        foreach ($all_replies as $reply) {
            $keywords = $reply['trigger_keywords'] ?? [];
            $is_contextual = false;

            if (is_array($keywords)) {
                foreach ($keywords as $keyword) {
                    if (strpos($input_lower, strtolower($keyword)) !== false) {
                        $is_contextual = true;
                        break;
                    }
                }
            }

            if ($is_contextual) {
                $contextual_replies[] = [
                    'id' => $reply['reply_id'],
                    'text' => $reply['reply_text'],
                    'category' => $reply['reply_category'],
                    'relevance' => 'high'
                ];
            } else {
                $general_replies[] = [
                    'id' => $reply['reply_id'],
                    'text' => $reply['reply_text'],
                    'category' => $reply['reply_category'],
                    'relevance' => 'medium'
                ];
            }
        }

        // Return contextual replies first, then general ones
        $result = array_merge($contextual_replies, array_slice($general_replies, 0, 4 - count($contextual_replies)));

        return $result;
    } catch (Exception $e) {
        return getGeneralQuickReplies($pdo);
    }
}

function trackQuickReplyUsage($pdo, $reply_id, $used) {
    try {
        if ($used) {
            // Increment usage count
            $stmt = $pdo->prepare("
                UPDATE chat_quick_replies
                SET usage_count = usage_count + 1
                WHERE reply_id = ?
            ");
            $stmt->execute([$reply_id]);
        }
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Error tracking quick reply usage: " . $e->getMessage());
    }
}

// Function to add new quick replies dynamically
function addQuickReply($pdo, $keywords, $reply_text, $category) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_quick_replies (trigger_keywords, reply_text, reply_category)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$keywords, $reply_text, $category]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        return false;
    }
}

// Function to get quick reply analytics
function getQuickReplyAnalytics($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT reply_category, COUNT(*) as total_replies,
                   SUM(usage_count) as total_usage,
                   AVG(success_rate) as avg_success_rate
            FROM chat_quick_replies
            WHERE is_active = true
            GROUP BY reply_category
            ORDER BY total_usage DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
?>
