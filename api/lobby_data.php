<?php
// Start session
session_start();

// Include configuration
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get active players
    $stmt = $pdo->prepare("
        SELECT 
            u.username,
            c.name as character_name,
            c.class,
            c.level
        FROM users u
        JOIN characters c ON u.id = c.user_id
        WHERE u.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.last_activity DESC
        LIMIT 20
    ");
    $stmt->execute();
    $activePlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active battles
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.name,
            b.status,
            b.created_at,
            COUNT(bp.player_id) as player_count
        FROM battles b
        LEFT JOIN battle_players bp ON b.id = bp.battle_id
        WHERE b.status = 'active' OR b.status = 'waiting'
        GROUP BY b.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $activeBattles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update user's last activity
    $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // Return data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'players' => $activePlayers,
        'battles' => $activeBattles
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
