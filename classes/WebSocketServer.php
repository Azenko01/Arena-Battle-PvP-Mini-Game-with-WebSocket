<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $battles = [];
    protected $playerToBattle = [];
    protected $playerToConnection = [];
    protected $connectionToPlayer = [];
    protected $game;
    protected $db;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize SplObjectStorage for clients
        $this->clients = new \SplObjectStorage;
        
        // Initialize Game class
        $this->game = new Game();
        
        // Connect to database
        try {
            $this->db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "Database connected successfully\n";
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
        }
        
        echo "WebSocket server initialized\n";
    }

    /**
     * Handle new connection
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        
        echo "New connection! ({$conn->resourceId})\n";
    }

    /**
     * Handle incoming message
     * @param ConnectionInterface $from
     * @param string $msg
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            echo "Invalid message format\n";
            return;
        }
        
        echo "Message received: {$data['type']} from connection {$from->resourceId}\n";
        
        // Handle message based on type
        switch ($data['type']) {
            case 'init':
                $this->handleInit($from, $data);
                break;
                
            case 'player_moved':
                $this->handlePlayerMoved($from, $data);
                break;
                
            case 'player_attacked':
                $this->handlePlayerAttacked($from, $data);
                break;
                
            case 'chat_message':
                $this->handleChatMessage($from, $data);
                break;
                
            case 'heartbeat':
                $this->handleHeartbeat($from);
                break;
                
            default:
                echo "Unknown message type: {$data['type']}\n";
                break;
        }
    }

    /**
     * Handle connection close
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) {
        // Remove the connection
        $this->clients->detach($conn);
        
        // Handle player disconnect
        if (isset($this->connectionToPlayer[$conn->resourceId])) {
            $playerId = $this->connectionToPlayer[$conn->resourceId];
            $this->handlePlayerDisconnect($playerId);
            
            // Clean up references
            unset($this->connectionToPlayer[$conn->resourceId]);
            unset($this->playerToConnection[$playerId]);
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    /**
     * Handle error
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        
        // Close the connection
        $conn->close();
    }

    /**
     * Handle player initialization
     * @param ConnectionInterface $conn
     * @param array $data
     */
    private function handleInit(ConnectionInterface $conn, $data) {
        if (!isset($data['battleId']) || !isset($data['data']) || 
            !isset($data['data']['playerId']) || !isset($data['data']['username']) || 
            !isset($data['data']['characterName']) || !isset($data['data']['characterClass'])) {
            
            $this->sendError($conn, "Invalid initialization data");
            return;
        }
        
        $battleId = $data['battleId'];
        $playerId = $data['data']['playerId'];
        $username = $data['data']['username'];
        $characterName = $data['data']['characterName'];
        $characterClass = $data['data']['characterClass'];
        
        // Validate token if provided
        if (isset($data['data']['token'])) {
            $token = $data['data']['token'];
            if (!$this->validateToken($playerId, $token)) {
                $this->sendError($conn, "Invalid authentication token");
                return;
            }
        }
        
        // Create battle if it doesn't exist
        if (!isset($this->battles[$battleId])) {
            $this->battles[$battleId] = [
                'players' => [],
                'status' => 'waiting',
                'winner' => null,
                'startTime' => null,
                'endTime' => null
            ];
            
            echo "Created new battle: $battleId\n";
        }
        
        // Initialize player stats based on class
        $stats = $this->game->getClassStats($characterClass);
        
        // Add player to battle
        $player = [
            'id' => $playerId,
            'username' => $username,
            'characterName' => $characterName,
            'characterClass' => $characterClass,
            'position' => $this->game->getRandomPosition(),
            'health' => $stats['maxHealth'],
            'maxHealth' => $stats['maxHealth'],
            'isAlive' => true,
            'lastAction' => time()
        ];
        
        $this->battles[$battleId]['players'][$playerId] = $player;
        $this->playerToBattle[$playerId] = $battleId;
        $this->playerToConnection[$playerId] = $conn->resourceId;
        $this->connectionToPlayer[$conn->resourceId] = $playerId;
        
        // Send current game state to the new player
        $this->sendToPlayer($playerId, [
            'type' => 'game_state',
            'battleId' => $battleId,
            'players' => array_values($this->battles[$battleId]['players']),
            'gameStatus' => $this->battles[$battleId]['status']
        ]);
        
        // Notify other players about the new player
        $this->broadcastToBattle($battleId, [
            'type' => 'player_joined',
            'data' => $player
        ], [$playerId]);
        
        echo "Player $playerId joined battle $battleId\n";
        
        // Check if battle should start
        $this->checkBattleStart($battleId);
    }

    /**
     * Handle player movement
     * @param ConnectionInterface $conn
     * @param array $data
     */
    private function handlePlayerMoved(ConnectionInterface $conn, $data) {
        if (!isset($data['position']) || !isset($data['position']['x']) || !isset($data['position']['y'])) {
            $this->sendError($conn, "Invalid movement data");
            return;
        }
        
        // Get player ID from connection
        $playerId = $this->connectionToPlayer[$conn->resourceId] ?? null;
        if (!$playerId) {
            $this->sendError($conn, "Player not initialized");
            return;
        }
        
        // Get battle ID
        $battleId = $this->playerToBattle[$playerId] ?? null;
        if (!$battleId || !isset($this->battles[$battleId])) {
            $this->sendError($conn, "Battle not found");
            return;
        }
        
        // Check if battle is active
        if ($this->battles[$battleId]['status'] !== 'active') {
            $this->sendError($conn, "Battle is not active");
            return;
        }
        
        // Check if player is alive
        if (!$this->battles[$battleId]['players'][$playerId]['isAlive']) {
            $this->sendError($conn, "Player is not alive");
            return;
        }
        
        // Validate and update position
        $position = $this->game->validatePosition($data['position']);
        $this->battles[$battleId]['players'][$playerId]['position'] = $position;
        $this->battles[$battleId]['players'][$playerId]['lastAction'] = time();
        
        // Broadcast movement to all players in battle
        $this->broadcastToBattle($battleId, [
            'type' => 'player_moved',
            'playerId' => $playerId,
            'position' => $position
        ]);
    }

    /**
     * Handle player attack
     * @param ConnectionInterface $conn
     * @param array $data
     */
    private function handlePlayerAttacked(ConnectionInterface $conn, $data) {
        if (!isset($data['targetId'])) {
            $this->sendError($conn, "Invalid attack data");
            return;
        }
        
        // Get player ID from connection
        $playerId = $this->connectionToPlayer[$conn->resourceId] ?? null;
        if (!$playerId) {
            $this->sendError($conn, "Player not initialized");
            return;
        }
        
        // Get battle ID
        $battleId = $this->playerToBattle[$playerId] ?? null;
        if (!$battleId || !isset($this->battles[$battleId])) {
            $this->sendError($conn, "Battle not found");
            return;
        }
        
        // Check if battle is active
        if ($this->battles[$battleId]['status'] !== 'active') {
            $this->sendError($conn, "Battle is not active");
            return;
        }
        
        // Check if player is alive
        if (!$this->battles[$battleId]['players'][$playerId]['isAlive']) {
            $this->sendError($conn, "Player is not alive");
            return;
        }
        
        $targetId = $data['targetId'];
        
        // Check if target exists and is alive
        if (!isset($this->battles[$battleId]['players'][$targetId]) || 
            !$this->battles[$battleId]['players'][$targetId]['isAlive']) {
            $this->sendError($conn, "Target is not valid or not alive");
            return;
        }
        
        // Get attacker and target
        $attacker = $this->battles[$battleId]['players'][$playerId];
        $target = $this->battles[$battleId]['players'][$targetId];
        
        // Check if target is in range
        if (!$this->game->isInRange($attacker, $target)) {
            $this->sendError($conn, "Target is out of range");
            return;
        }
        
        // Calculate damage
        $damage = $this->game->calculateDamage($attacker, $target);
        
        // Apply damage
        $targetHealth = max(0, $target['health'] - $damage);
        $this->battles[$battleId]['players'][$targetId]['health'] = $targetHealth;
        
        // Broadcast attack to all players
        $this->broadcastToBattle($battleId, [
            'type' => 'player_attacked',
            'data' => [
                'attackerId' => $playerId,
                'targetId' => $targetId,
                'damage' => $damage,
                'targetHealth' => $targetHealth
            ]
        ]);
        
        // Check if target died
        if ($targetHealth <= 0) {
            $this->battles[$battleId]['players'][$targetId]['isAlive'] = false;
            
            // Broadcast death
            $this->broadcastToBattle($battleId, [
                'type' => 'player_died',
                'data' => [
                    'playerId' => $targetId,
                    'killerId' => $playerId
                ]
            ]);
            
            // Check if battle is over
            $this->checkBattleEnd($battleId);
        }
    }

    /**
     * Handle chat message
     * @param ConnectionInterface $conn
     * @param array $data
     */
    private function handleChatMessage(ConnectionInterface $conn, $data) {
        if (!isset($data['data']) || !isset($data['data']['message'])) {
            $this->sendError($conn, "Invalid chat message");
            return;
        }
        
        // Get player ID from connection
        $playerId = $this->connectionToPlayer[$conn->resourceId] ?? null;
        if (!$playerId) {
            $this->sendError($conn, "Player not initialized");
            return;
        }
        
        // Get battle ID
        $battleId = $this->playerToBattle[$playerId] ?? null;
        if (!$battleId || !isset($this->battles[$battleId])) {
            $this->sendError($conn, "Battle not found");
            return;
        }
        
        // Get player info
        $player = $this->battles[$battleId]['players'][$playerId];
        
        // Sanitize message
        $message = htmlspecialchars($data['data']['message']);
        
        // Broadcast chat message to all players in battle
        $this->broadcastToBattle($battleId, [
            'type' => 'chat_message',
            'data' => [
                'playerId' => $playerId,
                'username' => $player['characterName'],
                'message' => $message,
                'timestamp' => time()
            ]
        ]);
    }

    /**
     * Handle heartbeat (keep-alive)
     * @param ConnectionInterface $conn
     */
    private function handleHeartbeat(ConnectionInterface $conn) {
        $this->sendToConnection($conn, [
            'type' => 'heartbeat_response',
            'timestamp' => time()
        ]);
    }

    /**
     * Handle player disconnect
     * @param string $playerId
     */
    private function handlePlayerDisconnect($playerId) {
        // Get battle ID
        $battleId = $this->playerToBattle[$playerId] ?? null;
        if (!$battleId || !isset($this->battles[$battleId])) {
            return;
        }
        
        // Remove player from battle
        unset($this->battles[$battleId]['players'][$playerId]);
        unset($this->playerToBattle[$playerId]);
        
        // Notify other players
        $this->broadcastToBattle($battleId, [
            'type' => 'player_left',
            'playerId' => $playerId
        ]);
        
        // Check if battle should end
        $this->checkBattleEnd($battleId);
        
        // Remove battle if empty
        if (empty($this->battles[$battleId]['players'])) {
            unset($this->battles[$battleId]);
            echo "Battle $battleId removed (no players)\n";
        }
    }

    /**
     * Check if battle should start
     * @param string $battleId
     */
    private function checkBattleStart($battleId) {
        if (!isset($this->battles[$battleId])) {
            return;
        }
        
        // Start battle if there are at least 2 players and status is waiting
        if (count($this->battles[$battleId]['players']) >= 2 && $this->battles[$battleId]['status'] === 'waiting') {
            $this->battles[$battleId]['status'] = 'active';
            $this->battles[$battleId]['startTime'] = time();
            
            // Broadcast battle start
            $this->broadcastToBattle($battleId, [
                'type' => 'battle_started'
            ]);
            
            echo "Battle $battleId started\n";
        }
    }

    /**
     * Check if battle should end
     * @param string $battleId
     */
    private function checkBattleEnd($battleId) {
        if (!isset($this->battles[$battleId]) || $this->battles[$battleId]['status'] !== 'active') {
            return;
        }
        
        // Count alive players
        $alivePlayers = [];
        foreach ($this->battles[$battleId]['players'] as $id => $player) {
            if ($player['isAlive']) {
                $alivePlayers[$id] = $player;
            }
        }
        
        // End battle if there is only one player left
        if (count($alivePlayers) <= 1) {
            $this->battles[$battleId]['status'] = 'finished';
            $this->battles[$battleId]['endTime'] = time();
            
            // Set winner
            $winner = null;
            if (count($alivePlayers) === 1) {
                $winner = array_key_first($alivePlayers);
                $this->battles[$battleId]['winner'] = $winner;
            }
            
            // Broadcast battle end
            $this->broadcastToBattle($battleId, [
                'type' => 'battle_ended',
                'winner' => $winner
            ]);
            
            // Save battle result to database
            $this->saveBattleResult($battleId);
            
            echo "Battle $battleId ended. Winner: " . ($winner ?? 'none') . "\n";
        }
    }

    /**
     * Save battle result to database
     * @param string $battleId
     */
    private function saveBattleResult($battleId) {
        if (!isset($this->battles[$battleId]) || !$this->db) {
            return;
        }
        
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Insert battle record
            $stmt = $this->db->prepare("
                INSERT INTO battles (battle_id, start_time, end_time, winner_id)
                VALUES (:battle_id, :start_time, :end_time, :winner_id)
            ");
            
            $stmt->execute([
                ':battle_id' => $battleId,
                ':start_time' => date('Y-m-d H:i:s', $this->battles[$battleId]['startTime']),
                ':end_time' => date('Y-m-d H:i:s', $this->battles[$battleId]['endTime']),
                ':winner_id' => $this->battles[$battleId]['winner']
            ]);
            
            $battleDbId = $this->db->lastInsertId();
            
            // Insert player results
            foreach ($this->battles[$battleId]['players'] as $playerId => $player) {
                $stmt = $this->db->prepare("
                    INSERT INTO battle_results (battle_id, player_id, character_name, character_class, is_winner)
                    VALUES (:battle_id, :player_id, :character_name, :character_class, :is_winner)
                ");
                
                $stmt->execute([
                    ':battle_id' => $battleDbId,
                    ':player_id' => $playerId,
                    ':character_name' => $player['characterName'],
                    ':character_class' => $player['characterClass'],
                    ':is_winner' => ($playerId === $this->battles[$battleId]['winner']) ? 1 : 0
                ]);
                
                // Update player stats
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET 
                        battles_played = battles_played + 1,
                        battles_won = battles_won + :won
                    WHERE id = :player_id
                ");
                
                $stmt->execute([
                    ':player_id' => $playerId,
                    ':won' => ($playerId === $this->battles[$battleId]['winner']) ? 1 : 0
                ]);
            }
            
            // Commit transaction
            $this->db->commit();
            
            echo "Battle result saved to database\n";
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->db->rollBack();
            echo "Failed to save battle result: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Validate authentication token
     * @param string $playerId
     * @param string $token
     * @return bool
     */
    private function validateToken($playerId, $token) {
        // Simple validation for demo purposes
        // In a real application, you would validate against the database
        return true;
    }

    /**
     * Send message to specific connection
     * @param ConnectionInterface $conn
     * @param array $message
     */
    private function sendToConnection(ConnectionInterface $conn, $message) {
        $conn->send(json_encode($message));
    }

    /**
     * Send message to specific player
     * @param string $playerId
     * @param array $message
     */
    private function sendToPlayer($playerId, $message) {
        if (!isset($this->playerToConnection[$playerId])) {
            return;
        }
        
        $connId = $this->playerToConnection[$playerId];
        
        foreach ($this->clients as $client) {
            if ($client->resourceId === $connId) {
                $this->sendToConnection($client, $message);
                break;
            }
        }
    }

    /**
     * Broadcast message to all players in a battle
     * @param string $battleId
     * @param array $message
     * @param array $excludePlayers Players to exclude
     */
    private function broadcastToBattle($battleId, $message, $excludePlayers = []) {
        if (!isset($this->battles[$battleId])) {
            return;
        }
        
        foreach ($this->battles[$battleId]['players'] as $playerId => $player) {
            if (in_array($playerId, $excludePlayers)) {
                continue;
            }
            
            $this->sendToPlayer($playerId, $message);
        }
    }

    /**
     * Send error message to connection
     * @param ConnectionInterface $conn
     * @param string $message
     */
    private function sendError(ConnectionInterface $conn, $message) {
        $this->sendToConnection($conn, [
            'type' => 'error',
            'data' => $message
        ]);
        
        echo "Error sent to client: $message\n";
    }
}
