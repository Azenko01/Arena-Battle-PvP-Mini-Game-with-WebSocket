<?php
// Start session
session_start();

// Include configuration
require_once '../config/config.php';

// Check if user is logged in
requireLogin();

// Include User class
require_once '../classes/User.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: lobby.php");
    exit;
}

// Get form data
$character_name = trim($_POST['character_name']);
$character_class = trim($_POST['character_class']);

// Validate input
if (empty($character_name)) {
    $_SESSION['error'] = "Character name is required.";
    header("Location: lobby.php");
    exit;
}

// Validate class
$valid_classes = ['warrior', 'archer', 'mage'];
if (!in_array($character_class, $valid_classes)) {
    $_SESSION['error'] = "Invalid character class.";
    header("Location: lobby.php");
    exit;
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create User instance
    $user = new User($pdo);
    
    // Create character
    $character_id = $user->createCharacter($_SESSION['user_id'], $character_name, $character_class);
    
    if ($character_id) {
        $_SESSION['success'] = "Character created successfully!";
    } else {
        $_SESSION['error'] = "Failed to create character. The name may already be taken or you already have a character.";
    }
    
    header("Location: lobby.php");
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: lobby.php");
    exit;
}
