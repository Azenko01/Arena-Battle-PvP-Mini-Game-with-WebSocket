<?php
// Start session
session_start();

// Include configuration
require_once '../config/config.php';

// Check if user is logged in
requireLogin();

// Include User class
require_once '../classes/User.php';

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create User instance
    $user = new User($pdo);
    
    // Get user data
    $userData = $user->getById($_SESSION['user_id']);
    
    // Get user's character
    $character = $user->getCharacter($_SESSION['user_id']);
    
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
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Lobby - Arena Battle</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .lobby-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        
        .sidebar {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
        }
        
        .main-content {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
        }
        
        .player-list {
            margin-top: 20px;
        }
        
        .player-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .player-item:last-child {
            border-bottom: none;
        }
        
        .player-name {
            font-weight: bold;
            color: #ff9800;
        }
        
        .player-class {
            color: #b0b0b0;
            font-size: 0.9rem;
        }
        
        .player-level {
            background-color: rgba(255, 87, 34, 0.2);
            color: #ff5722;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .battle-list {
            margin-top: 20px;
        }
        
        .battle-item {
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .battle-info {
            flex: 1;
        }
        
        .battle-name {
            font-weight: bold;
            color: #ff9800;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .battle-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #b0b0b0;
        }
        
        .battle-status {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-waiting {
            background-color: rgba(255, 152, 0, 0.2);
            color: #ff9800;
        }
        
        .status-active {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }
        
        .character-creation {
            background-color: rgba(255, 87, 34, 0.1);
            border: 1px solid rgba(255, 87, 34, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .character-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .character-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #ff5722;
        }
        
        .character-details h3 {
            margin-bottom: 5px;
        }
        
        .character-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }
        
        .stat {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .create-battle {
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .lobby-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>ARENA BATTLE</h1>
            <p class="tagline">Enter the arena. Fight for glory.</p>
            
            <nav style="margin-top: 20px;">
                <a href="lobby.php" class="btn btn-primary">Lobby</a>
                <a href="rankings.php" class="btn btn-secondary">Rankings</a>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </nav>
        </header>
        
        <main>
            <?php if (!$character): ?>
                <!-- Character Creation Form -->
                <div class="character-creation">
                    <h2>Create Your Character</h2>
                    <p>Before entering the arena, you need to create your character.</p>
                    
                    <form method="POST" action="create_character.php">
                        <div class="form-group">
                            <label for="character_name">Character Name</label>
                            <input type="text" id="character_name" name="character_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Character Class</label>
                            <div style="display: flex; gap: 20px; margin-top: 10px;">
                                <div>
                                    <input type="radio" id="warrior" name="character_class" value="warrior" checked>
                                    <label for="warrior">Warrior</label>
                                    <p class="form-text">Strong melee fighter with high health.</p>
                                </div>
                                
                                <div>
                                    <input type="radio" id="archer" name="character_class" value="archer">
                                    <label for="archer">Archer</label>
                                    <p class="form-text">Ranged fighter with high accuracy.</p>
                                </div>
                                
                                <div>
                                    <input type="radio" id="mage" name="character_class" value="mage">
                                    <label for="mage">Mage</label>
                                    <p class="form-text">Powerful spellcaster with area attacks.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Create Character</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="lobby-container">
                    <div class="sidebar">
                        <div class="character-info">
                            <div class="character-avatar">
                                <?php echo strtoupper(substr($character['name'], 0, 1)); ?>
                            </div>
                            <div class="character-details">
                                <h3><?php echo htmlspecialchars($character['name']); ?></h3>
                                <div class="player-class"><?php echo ucfirst(htmlspecialchars($character['class'])); ?></div>
                                
                                <div class="character-stats">
                                    <div class="stat">Level: <?php echo $character['level']; ?></div>
                                    <div class="stat">Wins: <?php echo $character['wins']; ?></div>
                                    <div class="stat">Losses: <?php echo $character['losses']; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <h3>Active Players</h3>
                        <div class="player-list">
                            <?php if (empty($activePlayers)): ?>
                                <p>No active players at the moment.</p>
                            <?php else: ?>
                                <?php foreach ($activePlayers as $player): ?>
                                    <div class="player-item">
                                        <div>
                                            <div class="player-name"><?php echo htmlspecialchars($player['character_name']); ?></div>
                                            <div class="player-class"><?php echo ucfirst(htmlspecialchars($player['class'])); ?></div>
                                        </div>
                                        <div class="player-level">Lvl <?php echo $player['level']; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="main-content">
                        <div class="create-battle">
                            <h2>Arena Battles</h2>
                            <p>Join an existing battle or create your own.</p>
                            <button class="btn btn-primary" onclick="createBattle()">Create New Battle</button>
                        </div>
                        
                        <div class="battle-list">
                            <?php if (empty($activeBattles)): ?>
                                <p>No active battles at the moment. Create one to start playing!</p>
                            <?php else: ?>
                                <?php foreach ($activeBattles as $battle): ?>
                                    <div class="battle-item">
                                        <div class="battle-info">
                                            <div class="battle-name"><?php echo htmlspecialchars($battle['name']); ?></div>
                                            <div class="battle-meta">
                                                <span>Players: <?php echo $battle['player_count']; ?>/10</span>
                                                <span>Created: <?php echo date('H:i', strtotime($battle['created_at'])); ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="battle-status status-<?php echo $battle['status']; ?>">
                                                <?php echo ucfirst($battle['status']); ?>
                                            </span>
                                            <?php if ($battle['status'] === 'waiting'): ?>
                                                <button class="btn btn-secondary" onclick="joinBattle(<?php echo $battle['id']; ?>)">Join</button>
                                            <?php elseif ($battle['status'] === 'active'): ?>
                                                <button class="btn btn-secondary" onclick="spectate(<?php echo $battle['id']; ?>)">Spectate</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; 2025 Arena Battle. All rights reserved.</p>
        </footer>
    </div>
    
    <script>
        // Auto-refresh the lobby data every 10 seconds
        setInterval(function() {
            fetchLobbyData();
        }, 10000);
        
        function fetchLobbyData() {
            fetch('api/lobby_data.php')
                .then(response => response.json())
                .then(data => {
                    updatePlayerList(data.players);
                    updateBattleList(data.battles);
                })
                .catch(error => console.error('Error fetching lobby data:', error));
        }
        
        function updatePlayerList(players) {
            const playerList = document.querySelector('.player-list');
            if (!playerList) return;
            
            if (players.length === 0) {
                playerList.innerHTML = '<p>No active players at the moment.</p>';
                return;
            }
            
            let html = '';
            players.forEach(player => {
                html += `
                    <div class="player-item">
                        <div>
                            <div class="player-name">${escapeHtml(player.character_name)}</div>
                            <div class="player-class">${escapeHtml(ucfirst(player.class))}</div>
                        </div>
                        <div class="player-level">Lvl ${player.level}</div>
                    </div>
                `;
            });
            
            playerList.innerHTML = html;
        }
        
        function updateBattleList(battles) {
            const battleList = document.querySelector('.battle-list');
            if (!battleList) return;
            
            if (battles.length === 0) {
                battleList.innerHTML = '<p>No active battles at the moment. Create one to start playing!</p>';
                return;
            }
            
            let html = '';
            battles.forEach(battle => {
                html += `
                    <div class="battle-item">
                        <div class="battle-info">
                            <div class="battle-name">${escapeHtml(battle.name)}</div>
                            <div class="battle-meta">
                                <span>Players: ${battle.player_count}/10</span>
                                <span>Created: ${formatTime(battle.created_at)}</span>
                            </div>
                        </div>
                        <div>
                            <span class="battle-status status-${battle.status}">
                                ${ucfirst(battle.status)}
                            </span>
                            ${battle.status === 'waiting' ? 
                                `<button class="btn btn-secondary" onclick="joinBattle(${battle.id})">Join</button>` : 
                                `<button class="btn btn-secondary" onclick="spectate(${battle.id})">Spectate</button>`
                            }
                        </div>
                    </div>
                `;
            });
            
            battleList.innerHTML = html;
        }
        
        function createBattle() {
            const battleName = prompt('Enter a name for your battle:');
            if (!battleName) return;
            
            fetch('api/create_battle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `name=${encodeURIComponent(battleName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `game/game.php?battle_id=${data.battle_id}`;
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error creating battle:', error));
        }
        
        function joinBattle(battleId) {
            fetch('api/join_battle.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `battle_id=${battleId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `game/game.php?battle_id=${battleId}`;
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error joining battle:', error));
        }
        
        function spectate(battleId) {
            window.location.href = `game/game.php?battle_id=${battleId}&spectate=1`;
        }
        
        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        function formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    </script>
</body>
</html>
