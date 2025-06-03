<?php
// Start session
session_start();

// Include configuration
require_once '../config/config.php';

// Check if user is logged in
requireLogin();

// Include classes
require_once '../classes/User.php';
require_once '../classes/Game.php';

// Get battle ID from URL
$battle_id = isset($_GET['battle_id']) ? intval($_GET['battle_id']) : 0;
$is_spectator = isset($_GET['spectate']) && $_GET['spectate'] == '1';

if (!$battle_id) {
    header("Location: lobby.php");
    exit;
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create instances
    $user = new User($pdo);
    $game = new Game($pdo);
    
    // Get battle info
    $battle = $game->getBattle($battle_id);
    if (!$battle) {
        $_SESSION['error'] = "Battle not found.";
        header("Location: lobby.php");
        exit;
    }
    
    // Get user data
    $userData = $user->getById($_SESSION['user_id']);
    $character = $user->getCharacter($_SESSION['user_id']);
    
    if (!$character && !$is_spectator) {
        $_SESSION['error'] = "You need to create a character first.";
        header("Location: lobby.php");
        exit;
    }
    
    // Check if user is in battle (if not spectating)
    if (!$is_spectator && !$game->isPlayerInBattle($battle_id, $_SESSION['user_id'])) {
        $_SESSION['error'] = "You are not part of this battle.";
        header("Location: lobby.php");
        exit;
    }
    
    // Get battle players
    $players = $game->getBattlePlayers($battle_id);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arena Battle - <?php echo htmlspecialchars($battle['name']); ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .game-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            height: 80vh;
        }
        
        .arena-container {
            background-color: rgba(0, 0, 0, 0.8);
            border-radius: 10px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .arena {
            width: 800px;
            height: 600px;
            background: linear-gradient(45deg, #1a1a1a 25%, transparent 25%),
                        linear-gradient(-45deg, #1a1a1a 25%, transparent 25%),
                        linear-gradient(45deg, transparent 75%, #1a1a1a 75%),
                        linear-gradient(-45deg, transparent 75%, #1a1a1a 75%);
            background-size: 40px 40px;
            background-position: 0 0, 0 20px, 20px -20px, -20px 0px;
            border: 3px solid #ff5722;
            border-radius: 10px;
            position: relative;
            margin: 0 auto;
        }
        
        .player {
            position: absolute;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
            transition: all 0.1s ease;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .player.warrior {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }
        
        .player.archer {
            background: linear-gradient(135deg, #4caf50, #388e3c);
        }
        
        .player.mage {
            background: linear-gradient(135deg, #2196f3, #1976d2);
        }
        
        .player.current-player {
            border-color: #ffeb3b;
            box-shadow: 0 0 15px rgba(255, 235, 59, 0.6);
        }
        
        .player.dead {
            opacity: 0.3;
            filter: grayscale(100%);
        }
        
        .health-bar {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 6px;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .health-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            transition: width 0.3s ease;
        }
        
        .health-fill.low {
            background: linear-gradient(90deg, #ff5722, #ff9800);
        }
        
        .health-fill.critical {
            background: linear-gradient(90deg, #f44336, #e91e63);
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .game-info {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
        }
        
        .players-list {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            flex: 1;
        }
        
        .chat-container {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            height: 300px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 10px;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .chat-message {
            margin-bottom: 8px;
            padding: 5px;
            border-radius: 3px;
        }
        
        .chat-message .username {
            font-weight: bold;
            color: #ff9800;
        }
        
        .chat-message .message {
            color: #e0e0e0;
        }
        
        .chat-input {
            display: flex;
            gap: 10px;
        }
        
        .chat-input input {
            flex: 1;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #333;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .chat-input button {
            padding: 8px 15px;
            background-color: #ff5722;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .controls {
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .control-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .control-btn {
            padding: 10px;
            background-color: rgba(255, 87, 34, 0.2);
            border: 1px solid #ff5722;
            color: #ff5722;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .control-btn:hover {
            background-color: rgba(255, 87, 34, 0.4);
        }
        
        .control-btn:active {
            background-color: rgba(255, 87, 34, 0.6);
        }
        
        .attack-btn {
            grid-column: 2;
            background-color: rgba(244, 67, 54, 0.2);
            border-color: #f44336;
            color: #f44336;
        }
        
        .attack-btn:hover {
            background-color: rgba(244, 67, 54, 0.4);
        }
        
        .game-status {
            text-align: center;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .status-waiting {
            background-color: rgba(255, 152, 0, 0.2);
            color: #ff9800;
            border: 1px solid #ff9800;
        }
        
        .status-active {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            border: 1px solid #4caf50;
        }
        
        .status-finished {
            background-color: rgba(96, 125, 139, 0.2);
            color: #607d8b;
            border: 1px solid #607d8b;
        }
        
        .player-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 10px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
        }
        
        .player-item.current {
            border: 1px solid #ff9800;
        }
        
        .player-name {
            font-weight: bold;
            color: #ff9800;
        }
        
        .player-class {
            font-size: 0.8rem;
            color: #b0b0b0;
        }
        
        .player-health {
            font-size: 0.9rem;
            color: #4caf50;
        }
        
        .player-health.low {
            color: #ff9800;
        }
        
        .player-health.critical {
            color: #f44336;
        }
        
        @media (max-width: 1200px) {
            .game-container {
                grid-template-columns: 1fr;
            }
            
            .arena {
                width: 100%;
                max-width: 800px;
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>ARENA BATTLE</h1>
            <p class="tagline"><?php echo htmlspecialchars($battle['name']); ?></p>
            
            <nav style="margin-top: 20px;">
                <a href="lobby.php" class="btn btn-secondary">Back to Lobby</a>
                <?php if ($is_spectator): ?>
                    <span class="btn btn-primary">Spectating</span>
                <?php endif; ?>
            </nav>
        </header>
        
        <main>
            <div class="game-status status-<?php echo $battle['status']; ?>">
                <?php if ($battle['status'] === 'waiting'): ?>
                    Waiting for players to join...
                <?php elseif ($battle['status'] === 'active'): ?>
                    Battle in progress!
                <?php else: ?>
                    Battle finished
                <?php endif; ?>
            </div>
            
            <div class="game-container">
                <div class="arena-container">
                    <div class="arena" id="arena">
                        <!-- Players will be rendered here by JavaScript -->
                    </div>
                    
                    <?php if (!$is_spectator): ?>
                    <div class="controls">
                        <h3>Controls</h3>
                        <p>Use WASD or arrow keys to move. Click to attack nearby enemies.</p>
                        
                        <div class="control-buttons">
                            <button class="control-btn" data-direction="up">↑</button>
                            <button class="control-btn attack-btn" id="attackBtn">⚔️</button>
                            <button class="control-btn" data-direction="down">↓</button>
                            <button class="control-btn" data-direction="left">←</button>
                            <button class="control-btn" data-direction="right">→</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="sidebar">
                    <div class="game-info">
                        <h3>Battle Info</h3>
                        <p><strong>Battle:</strong> <?php echo htmlspecialchars($battle['name']); ?></p>
                        <p><strong>Creator:</strong> <?php echo htmlspecialchars($battle['creator_username']); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst($battle['status']); ?></p>
                        <?php if (!$is_spectator && $character): ?>
                        <p><strong>Your Character:</strong> <?php echo htmlspecialchars($character['name']); ?> (<?php echo ucfirst($character['class']); ?>)</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="players-list">
                        <h3>Players</h3>
                        <div id="playersList">
                            <?php foreach ($players as $player): ?>
                            <div class="player-item <?php echo $player['id'] == $_SESSION['user_id'] ? 'current' : ''; ?>">
                                <div>
                                    <div class="player-name"><?php echo htmlspecialchars($player['character_name']); ?></div>
                                    <div class="player-class"><?php echo ucfirst($player['class']); ?></div>
                                </div>
                                <div class="player-health">100 HP</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="chat-container">
                        <h3>Chat</h3>
                        <div class="chat-messages" id="chatMessages">
                            <!-- Chat messages will appear here -->
                        </div>
                        
                        <?php if (!$is_spectator): ?>
                        <div class="chat-input">
                            <input type="text" id="chatInput" placeholder="Type a message..." maxlength="200">
                            <button onclick="sendChatMessage()">Send</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2025 Arena Battle. All rights reserved.</p>
        </footer>
    </div>
    
    <script>
        // Game state
        let gameState = {
            battleId: <?php echo $battle_id; ?>,
            playerId: <?php echo $_SESSION['user_id']; ?>,
            isSpectator: <?php echo $is_spectator ? 'true' : 'false'; ?>,
            username: '<?php echo addslashes($userData['username']); ?>',
            characterName: '<?php echo $character ? addslashes($character['name']) : ''; ?>',
            characterClass: '<?php echo $character ? addslashes($character['class']) : ''; ?>',
            players: {},
            currentPlayer: null,
            selectedTarget: null,
            ws: null,
            connected: false
        };
        
        // Initialize WebSocket connection
        function initWebSocket() {
            const wsUrl = `ws://<?php echo WS_HOST; ?>:<?php echo WS_PORT; ?>`;
            gameState.ws = new WebSocket(wsUrl);
            
            gameState.ws.onopen = function(event) {
                console.log('Connected to WebSocket server');
                gameState.connected = true;
                
                if (!gameState.isSpectator) {
                    // Join the battle
                    gameState.ws.send(JSON.stringify({
                        type: 'join_battle',
                        battle_id: gameState.battleId,
                        player_id: gameState.playerId,
                        username: gameState.username,
                        character_name: gameState.characterName,
                        character_class: gameState.characterClass
                    }));
                }
            };
            
            gameState.ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            };
            
            gameState.ws.onclose = function(event) {
                console.log('WebSocket connection closed');
                gameState.connected = false;
                
                // Try to reconnect after 3 seconds
                setTimeout(initWebSocket, 3000);
            };
            
            gameState.ws.onerror = function(error) {
                console.error('WebSocket error:', error);
            };
        }
        
        // Handle WebSocket messages
        function handleWebSocketMessage(data) {
            switch (data.type) {
                case 'game_state':
                    updateGameState(data);
                    break;
                    
                case 'player_joined':
                    addPlayer(data);
                    break;
                    
                case 'player_left':
                    removePlayer(data.player_id);
                    break;
                    
                case 'player_moved':
                    updatePlayerPosition(data.player_id, data.position);
                    break;
                    
                case 'player_attacked':
                    handlePlayerAttack(data);
                    break;
                    
                case 'player_died':
                    handlePlayerDeath(data);
                    break;
                    
                case 'battle_started':
                    handleBattleStart(data);
                    break;
                    
                case 'battle_ended':
                    handleBattleEnd(data);
                    break;
                    
                case 'chat_message':
                    addChatMessage(data);
                    break;
                    
                case 'heartbeat_response':
                    // Keep connection alive
                    break;
            }
        }
        
        // Update game state
        function updateGameState(data) {
            gameState.players = {};
            
            data.players.forEach(player => {
                gameState.players[player.player_id] = player;
                
                if (player.player_id == gameState.playerId) {
                    gameState.currentPlayer = player;
                }
            });
            
            renderPlayers();
            updatePlayersList();
        }
        
        // Add new player
        function addPlayer(data) {
            gameState.players[data.player_id] = {
                player_id: data.player_id,
                username: data.username,
                character_name: data.character_name,
                character_class: data.character_class,
                position: data.position,
                health: data.health,
                max_health: 100,
                is_alive: true
            };
            
            renderPlayers();
            updatePlayersList();
            
            addChatMessage({
                type: 'system',
                message: `${data.character_name} joined the battle!`,
                timestamp: Date.now() / 1000
            });
        }
        
        // Remove player
        function removePlayer(playerId) {
            if (gameState.players[playerId]) {
                const playerName = gameState.players[playerId].character_name;
                delete gameState.players[playerId];
                
                renderPlayers();
                updatePlayersList();
                
                addChatMessage({
                    type: 'system',
                    message: `${playerName} left the battle.`,
                    timestamp: Date.now() / 1000
                });
            }
        }
        
        // Update player position
        function updatePlayerPosition(playerId, position) {
            if (gameState.players[playerId]) {
                gameState.players[playerId].position = position;
                renderPlayers();
            }
        }
        
        // Handle player attack
        function handlePlayerAttack(data) {
            const attacker = gameState.players[data.attacker_id];
            const target = gameState.players[data.target_id];
            
            if (target) {
                target.health = data.target_health;
                
                // Visual feedback for attack
                const targetElement = document.querySelector(`[data-player-id="${data.target_id}"]`);
                if (targetElement) {
                    targetElement.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        targetElement.style.transform = 'scale(1)';
                    }, 200);
                }
                
                renderPlayers();
                updatePlayersList();
                
                addChatMessage({
                    type: 'system',
                    message: `${attacker.character_name} attacked ${target.character_name} for ${data.damage} damage!`,
                    timestamp: Date.now() / 1000
                });
            }
        }
        
        // Handle player death
        function handlePlayerDeath(data) {
            const player = gameState.players[data.player_id];
            const killer = gameState.players[data.killer_id];
            
            if (player) {
                player.is_alive = false;
                player.health = 0;
                
                renderPlayers();
                updatePlayersList();
                
                addChatMessage({
                    type: 'system',
                    message: `${player.character_name} was defeated by ${killer.character_name}!`,
                    timestamp: Date.now() / 1000
                });
            }
        }
        
        // Handle battle start
        function handleBattleStart(data) {
            document.querySelector('.game-status').className = 'game-status status-active';
            document.querySelector('.game-status').textContent = 'Battle in progress!';
            
            addChatMessage({
                type: 'system',
                message: 'Battle has started! Fight for victory!',
                timestamp: Date.now() / 1000
            });
        }
        
        // Handle battle end
        function handleBattleEnd(data) {
            document.querySelector('.game-status').className = 'game-status status-finished';
            
            if (data.winner_id) {
                document.querySelector('.game-status').textContent = `Battle finished! Winner: ${data.winner_name}`;
                
                addChatMessage({
                    type: 'system',
                    message: `🏆 ${data.winner_name} wins the battle!`,
                    timestamp: Date.now() / 1000
                });
            } else {
                document.querySelector('.game-status').textContent = 'Battle finished - No winner';
                
                addChatMessage({
                    type: 'system',
                    message: 'Battle ended with no winner.',
                    timestamp: Date.now() / 1000
                });
            }
        }
        
        // Render players on arena
        function renderPlayers() {
            const arena = document.getElementById('arena');
            
            // Clear existing players
            arena.querySelectorAll('.player').forEach(el => el.remove());
            
            // Render each player
            Object.values(gameState.players).forEach(player => {
                const playerElement = document.createElement('div');
                playerElement.className = `player ${player.character_class}`;
                playerElement.setAttribute('data-player-id', player.player_id);
                
                if (player.player_id == gameState.playerId) {
                    playerElement.classList.add('current-player');
                }
                
                if (!player.is_alive) {
                    playerElement.classList.add('dead');
                }
                
                playerElement.style.left = player.position.x + 'px';
                playerElement.style.top = player.position.y + 'px';
                
                // Character initial
                playerElement.textContent = player.character_name.charAt(0).toUpperCase();
                
                // Health bar
                const healthBar = document.createElement('div');
                healthBar.className = 'health-bar';
                
                const healthFill = document.createElement('div');
                healthFill.className = 'health-fill';
                
                const healthPercent = (player.health / player.max_health) * 100;
                healthFill.style.width = healthPercent + '%';
                
                if (healthPercent <= 25) {
                    healthFill.classList.add('critical');
                } else if (healthPercent <= 50) {
                    healthFill.classList.add('low');
                }
                
                healthBar.appendChild(healthFill);
                playerElement.appendChild(healthBar);
                
                // Click to target
                if (!gameState.isSpectator && player.player_id != gameState.playerId && player.is_alive) {
                    playerElement.style.cursor = 'crosshair';
                    playerElement.onclick = () => selectTarget(player.player_id);
                }
                
                arena.appendChild(playerElement);
            });
        }
        
        // Update players list in sidebar
        function updatePlayersList() {
            const playersList = document.getElementById('playersList');
            playersList.innerHTML = '';
            
            Object.values(gameState.players).forEach(player => {
                const playerItem = document.createElement('div');
                playerItem.className = 'player-item';
                
                if (player.player_id == gameState.playerId) {
                    playerItem.classList.add('current');
                }
                
                const healthPercent = (player.health / player.max_health) * 100;
                let healthClass = '';
                if (healthPercent <= 25) {
                    healthClass = 'critical';
                } else if (healthPercent <= 50) {
                    healthClass = 'low';
                }
                
                playerItem.innerHTML = `
                    <div>
                        <div class="player-name">${escapeHtml(player.character_name)}</div>
                        <div class="player-class">${ucfirst(player.character_class)}</div>
                    </div>
                    <div class="player-health ${healthClass}">
                        ${player.is_alive ? player.health + ' HP' : 'DEAD'}
                    </div>
                `;
                
                playersList.appendChild(playerItem);
            });
        }
        
        // Select target for attack
        function selectTarget(playerId) {
            if (gameState.isSpectator || !gameState.currentPlayer || !gameState.currentPlayer.is_alive) {
                return;
            }
            
            gameState.selectedTarget = playerId;
            
            // Visual feedback
            document.querySelectorAll('.player').forEach(el => {
                el.style.border = '2px solid rgba(255, 255, 255, 0.3)';
            });
            
            const targetElement = document.querySelector(`[data-player-id="${playerId}"]`);
            if (targetElement) {
                targetElement.style.border = '2px solid #f44336';
            }
        }
        
        // Movement handling
        let keys = {};
        let moveInterval = null;
        
        document.addEventListener('keydown', (e) => {
            if (gameState.isSpectator || !gameState.currentPlayer || !gameState.currentPlayer.is_alive) {
                return;
            }
            
            const key = e.key.toLowerCase();
            
            if (['w', 'a', 's', 'd', 'arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(key)) {
                e.preventDefault();
                keys[key] = true;
                
                if (!moveInterval) {
                    moveInterval = setInterval(handleMovement, 50);
                }
            }
            
            if (key === ' ' || key === 'enter') {
                e.preventDefault();
                attack();
            }
        });
        
        document.addEventListener('keyup', (e) => {
            const key = e.key.toLowerCase();
            keys[key] = false;
            
            // Stop movement if no movement keys are pressed
            if (!keys['w'] && !keys['a'] && !keys['s'] && !keys['d'] && 
                !keys['arrowup'] && !keys['arrowdown'] && !keys['arrowleft'] && !keys['arrowright']) {
                if (moveInterval) {
                    clearInterval(moveInterval);
                    moveInterval = null;
                }
            }
        });
        
        function handleMovement() {
            if (!gameState.currentPlayer || !gameState.currentPlayer.is_alive) {
                return;
            }
            
            let dx = 0;
            let dy = 0;
            const speed = 5;
            
            if (keys['w'] || keys['arrowup']) dy -= speed;
            if (keys['s'] || keys['arrowdown']) dy += speed;
            if (keys['a'] || keys['arrowleft']) dx -= speed;
            if (keys['d'] || keys['arrowright']) dx += speed;
            
            if (dx !== 0 || dy !== 0) {
                const newX = Math.max(0, Math.min(760, gameState.currentPlayer.position.x + dx));
                const newY = Math.max(0, Math.min(560, gameState.currentPlayer.position.y + dy));
                
                movePlayer(newX, newY);
            }
        }
        
        // Move player
        function movePlayer(x, y) {
            if (!gameState.connected || gameState.isSpectator) {
                return;
            }
            
            gameState.ws.send(JSON.stringify({
                type: 'player_move',
                x: x,
                y: y
            }));
            
            // Update local position immediately for smooth movement
            if (gameState.currentPlayer) {
                gameState.currentPlayer.position.x = x;
                gameState.currentPlayer.position.y = y;
                
                const playerElement = document.querySelector(`[data-player-id="${gameState.playerId}"]`);
                if (playerElement) {
                    playerElement.style.left = x + 'px';
                    playerElement.style.top = y + 'px';
                }
            }
        }
        
        // Attack function
        function attack() {
            if (!gameState.connected || gameState.isSpectator || !gameState.selectedTarget || 
                !gameState.currentPlayer || !gameState.currentPlayer.is_alive) {
                return;
            }
            
            gameState.ws.send(JSON.stringify({
                type: 'player_attack',
                target_id: gameState.selectedTarget
            }));
        }
        
        // Control buttons
        document.querySelectorAll('.control-btn[data-direction]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (gameState.isSpectator || !gameState.currentPlayer || !gameState.currentPlayer.is_alive) {
                    return;
                }
                
                const direction = btn.getAttribute('data-direction');
                const speed = 20;
                let dx = 0, dy = 0;
                
                switch (direction) {
                    case 'up': dy = -speed; break;
                    case 'down': dy = speed; break;
                    case 'left': dx = -speed; break;
                    case 'right': dx = speed; break;
                }
                
                const newX = Math.max(0, Math.min(760, gameState.currentPlayer.position.x + dx));
                const newY = Math.max(0, Math.min(560, gameState.currentPlayer.position.y + dy));
                
                movePlayer(newX, newY);
            });
        });
        
        document.getElementById('attackBtn').addEventListener('click', attack);
        
        // Chat functions
        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (!message || !gameState.connected || gameState.isSpectator) {
                return;
            }
            
            gameState.ws.send(JSON.stringify({
                type: 'chat_message',
                message: message
            }));
            
            input.value = '';
        }
        
        function addChatMessage(data) {
            const chatMessages = document.getElementById('chatMessages');
            const messageElement = document.createElement('div');
            messageElement.className = 'chat-message';
            
            const time = new Date(data.timestamp * 1000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            if (data.type === 'system') {
                messageElement.innerHTML = `<span style="color: #ff9800; font-style: italic;">[${time}] ${escapeHtml(data.message)}</span>`;
            } else {
                messageElement.innerHTML = `
                    <span style="color: #666;">[${time}]</span>
                    <span class="username">${escapeHtml(data.username)}:</span>
                    <span class="message">${escapeHtml(data.message)}</span>
                `;
            }
            
            chatMessages.appendChild(messageElement);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Chat input enter key
        document.getElementById('chatInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
        
        // Heartbeat to keep connection alive
        setInterval(() => {
            if (gameState.connected && !gameState.isSpectator) {
                gameState.ws.send(JSON.stringify({
                    type: 'heartbeat'
                }));
            }
        }, 30000);
        
        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        
        // Initialize the game
        document.addEventListener('DOMContentLoaded', () => {
            initWebSocket();
        });
        
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            if (gameState.ws) {
                gameState.ws.close();
            }
        });
    </script>
</body>
</html>
