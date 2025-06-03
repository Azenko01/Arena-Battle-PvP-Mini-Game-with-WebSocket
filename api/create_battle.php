<?php
// Start session
session_start();

// Include configuration
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Include Game class
require_once '../classes/Game.php';

// Get form data
$name = trim($_POST['name']);

// Validate input
if (empty($name)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Battle name is required']);
    exit;
}

if (strlen($name) > 100) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Battle name is too long']);
    exit;
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create Game instance
    $game = new Game($pdo);
    
    // Create battle
    $battle_id = $game->createBattle($name, $_SESSION['user_id']);
    
    if ($battle_id) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'battle_id' => $battle_id,
            'message' => 'Battle created successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create battle']);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
