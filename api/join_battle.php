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

// Include classes
require_once '../classes/Game.php';
require_once '../classes/User.php';

// Get form data
$battle_id = intval($_POST['battle_id']);

// Validate input
if (!$battle_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid battle ID']);
    exit;
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create instances
    $game = new Game($pdo);
    $user = new User($pdo);
    
    // Check if user has a character
    $character = $user->getCharacter($_SESSION['user_id']);
    if (!$character) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You need to create a character first']);
        exit;
    }
    
    // Join battle
    $success = $game->joinBattle($battle_id, $_SESSION['user_id']);
    
    if ($success) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Successfully joined battle'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to join battle. It may be full or already started.']);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
