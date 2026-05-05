<?php
/**
 * Advanced AI Chatbot API
 * Professional conversational AI like ChatGPT and Meta AI
 */

require_once '../config.php';
require_once '../db_connection_secure.php';
require_once '../security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialize security
$security = Security::getInstance();

// Get database connection
$pdo = DatabaseConnection::getInstance()->getConnection();

class AdvancedChatbot {
    private $pdo;
    private $user_id;
    private $session_id;
    private $conversation_context = [];

    public function __construct($pdo, $user_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->initializeSession();
        $this->loadConversationContext();
    }

    private function initializeSession() {
        if ($this->user_id) {
            // Check for existing active session
            $stmt = $this->pdo->prepare("
                SELECT session_id FROM chat_sessions
                WHERE user_id = ? AND is_active = true
                ORDER BY session_start DESC LIMIT 1
            ");
            $stmt->execute([$this->user_id]);
            $session = $stmt->fetch();

            if ($session) {
                $this->session_id = $session['session_id'];
            } else {
                // Create new session
                $stmt = $this->pdo->prepare("
                    INSERT INTO chat_sessions (user_id, user_agent, ip_address, session_metadata)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $this->user_id,
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    json_encode(['source' => 'api', 'version' => '2.0'])
                ]);
                $this->session_id = $this->pdo->lastInsertId();
