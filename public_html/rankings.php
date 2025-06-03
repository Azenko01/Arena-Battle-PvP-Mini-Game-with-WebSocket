<?php
// Start session
session_start();

// Include configuration
require_once '../config/config.php';

// Include User class
require_once '../classes/User.php';

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create User instance
    $user = new User($pdo);
    
    // Get top players
    $topPlayers = $user->getTopPlayers(50);
    
    // Get current user's stats if logged in
    $currentUserStats = null;
    if (isLoggedIn()) {
        $currentUserStats = $user->getCharacter($_SESSION['user_id']);
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rankings - Arena Battle</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .rankings-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 87, 34, 0.3);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #ff5722;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #b0b0b0;
            font-size: 0.9rem;
        }
        
        .rankings-table {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table th {
            background-color: rgba(255, 87, 34, 0.2);
            color: #ff5722;
            font-weight: bold;
        }
        
        .table tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .rank {
            font-weight: bold;
            color: #ff9800;
        }
        
        .rank.gold {
            color: #ffd700;
        }
        
        .rank.silver {
            color: #c0c0c0;
        }
        
        .rank.bronze {
            color: #cd7f32;
        }
        
        .character-name {
            font-weight: bold;
            color: #ff9800;
        }
        
        .character-class {
            color: #b0b0b0;
            font-size: 0.9rem;
        }
        
        .win-rate {
            font-weight: bold;
        }
        
        .win-rate.excellent {
            color: #4caf50;
        }
        
        .win-rate.good {
            color: #8bc34a;
        }
        
        .win-rate.average {
            color: #ff9800;
        }
        
        .win-rate.poor {
            color: #f44336;
        }
        
        .current-user {
            background-color: rgba(255, 152, 0, 0.1);
            border: 1px solid rgba(255, 152, 0, 0.3);
        }
        
        .no-data {
            text-align: center;
            color: #b0b0b0;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>ARENA BATTLE</h1>
            <p class="tagline">Hall of Champions</p>
            
            <nav style="margin-top: 20px;">
                <?php if (isLoggedIn()): ?>
                    <a href="lobby.php" class="btn btn-primary">Lobby</a>
                    <a href="rankings.php" class="btn btn-secondary">Rankings</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                <?php endif; ?>
            </nav>
        </header>
        
        <main>
            <div class="rankings-container">
                <?php if ($currentUserStats): ?>
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $currentUserStats['level']; ?></div>
                        <div class="stat-label">Your Level</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $currentUserStats['wins']; ?></div>
                        <div class="stat-label">Your Wins</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $currentUserStats['losses']; ?></div>
                        <div class="stat-label">Your Losses</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                            $total = $currentUserStats['wins'] + $currentUserStats['losses'];
                            $winRate = $total > 0 ? round(($currentUserStats['wins'] / $total) * 100) : 0;
                            echo $winRate . '%';
                            ?>
                        </div>
                        <div class="stat-label">Your Win Rate</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $currentUserStats['experience']; ?></div>
                        <div class="stat-label">Your Experience</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="rankings-table">
                    <h2>Top Players</h2>
                    
                    <?php if (empty($topPlayers)): ?>
                        <div class="no-data">
                            <h3>No players found</h3>
                            <p>Be the first to create a character and start battling!</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Character</th>
                                    <th>Class</th>
                                    <th>Level</th>
                                    <th>Wins</th>
                                    <th>Losses</th>
                                    <th>Win Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topPlayers as $index => $player): ?>
                                    <?php
                                    $rank = $index + 1;
                                    $total = $player['wins'] + $player['losses'];
                                    $winRate = $total > 0 ? round(($player['wins'] / $total) * 100) : 0;
                                    
                                    $rankClass = '';
                                    if ($rank === 1) $rankClass = 'gold';
                                    elseif ($rank === 2) $rankClass = 'silver';
                                    elseif ($rank === 3) $rankClass = 'bronze';
                                    
                                    $winRateClass = '';
                                    if ($winRate >= 80) $winRateClass = 'excellent';
                                    elseif ($winRate >= 60) $winRateClass = 'good';
                                    elseif ($winRate >= 40) $winRateClass = 'average';
                                    else $winRateClass = 'poor';
                                    
                                    $isCurrentUser = isLoggedIn() && $currentUserStats && 
                                                   $player['character_name'] === $currentUserStats['name'];
                                    ?>
                                    <tr <?php echo $isCurrentUser ? 'class="current-user"' : ''; ?>>
                                        <td>
                                            <span class="rank <?php echo $rankClass; ?>">
                                                <?php if ($rank <= 3): ?>
                                                    <?php echo $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉'); ?>
                                                <?php endif; ?>
                                                #<?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="character-name"><?php echo htmlspecialchars($player['character_name']); ?></div>
                                            <div style="font-size: 0.8rem; color: #888;">
                                                <?php echo htmlspecialchars($player['username']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="character-class"><?php echo ucfirst(htmlspecialchars($player['class'])); ?></span>
                                        </td>
                                        <td><?php echo $player['level']; ?></td>
                                        <td><?php echo $player['wins']; ?></td>
                                        <td><?php echo $player['losses']; ?></td>
                                        <td>
                                            <span class="win-rate <?php echo $winRateClass; ?>">
                                                <?php echo $winRate; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2025 Arena Battle. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
