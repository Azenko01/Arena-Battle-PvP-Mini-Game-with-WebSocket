<?php
class Game {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new battle
     * 
     * @param string $name Battle name
     * @param int $creator_id User ID of the creator
     * @return int|false Battle ID or false on failure
     */
    public function createBattle($name, $creator_id) {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Insert battle
            $stmt = $this->pdo->prepare("
                INSERT INTO battles (name, creator_id, status, created_at)
                VALUES (:name, :creator_id, 'waiting', NOW())
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':creator_id', $creator_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $battle_id = $this->pdo->lastInsertId();
            
            // Add creator as a player
            $stmt = $this->pdo->prepare("
                INSERT INTO battle_players (battle_id, player_id, joined_at)
                VALUES (:battle_id, :player_id, NOW())
            ");
            $stmt->bindParam(':battle_id', $battle_id, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $creator_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit transaction
            $this->pdo->commit();
            
            return $battle_id;
        } catch (PDOException $e) {
            // Rollback transaction
            $this->pdo->rollBack();
            error_log("Error creating battle: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Join a battle
     * 
     * @param int $battle_id Battle ID
     * @param int $player_id User ID of the player
     * @return bool Success status
     */
    public function joinBattle($battle_id, $player_id) {
        try {
            // Check if battle exists and is waiting
            $stmt = $this->pdo->prepare("SELECT status FROM battles WHERE id = :id");
            $stmt->bindParam(':id', $battle_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $battle = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$battle || $battle['status'] !== 'waiting') {
                return false;
            }
            
            // Check if player is already in the battle
            $stmt = $this->pdo->prepare("SELECT id FROM battle_players WHERE battle_id = :battle_id AND player_id = :player_id");
            $stmt->bindParam(':battle_id', $battle_id, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return false; // Already in battle
            }
            
            // Check if battle is full
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM battle_players WHERE battle_id = :battle_id");
            $stmt->bindParam(':battle_id', $battle_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($count >= GAME_MAX_PLAYERS) {
                return false; // Battle is full
            }
            
            // Add player to battle
            $stmt = $this->pdo->prepare("
                INSERT INTO battle_players (battle_id, player_id, joined_at)
                VALUES (:battle_id, :player_id, NOW())
            ");
            $stmt->bindParam(':battle_id', $battle_id, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // If we have enough players, start the battle
            if ($count + 1 >= 2) { // Minimum 2 players to start
                $this->startBattle($battle_id);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error joining battle: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Start a battle
     * 
     * @param int $battle_id Battle ID
     * @return bool Success status
     */
    public function startBattle($battle_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE battles 
                SET status = 'active', started_at = NOW()
                WHERE id = :id AND status = 'waiting'
            ");
            $stmt->bindParam(':id', $battle_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error starting battle: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * End a battle
     * 
     * @param int $battle_id Battle ID
     * @param int $winner_id User ID of the winner (optional)
     * @return bool Success status
     */
    public function endBattle($battle_id, $winner_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE battles 
                SET status = 'finished', ended_at = NOW(), winner_id = :winner_id
                WHERE id = :id AND status = 'active'
            ");
            $stmt->bindParam(':id', $battle_id, PDO::PARAM_INT);
            $stmt->bindParam(':winner_id', $winner_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error ending battle: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get battle information
     * 
     * @param int $battle_id Battle ID
     * @return array|false Battle data or false if not found
     */
    public function getBattle($battle_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    b.*,
                    u.username as creator_username
                FROM battles b
                JOIN users u ON b.creator_id = u.id
                WHERE b.id = :id
            ");
            $stmt->bindParam(':id', $battle_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting battle: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get battle players
     * 
     * @param int $battle_id Battle ID
     * @return array|false Array of players or false on failure
     */
    public function getBattlePlayers($battle_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.id,
                    u.username,
                    c.name as character_name,
                    c.class,
                    c.level,
                    bp.joined_at
                FROM battle_players bp
                JOIN users u ON bp.player_id = u.id
                JOIN characters c ON u.id = c.user_id
                WHERE bp.battle_id = :battle_id
                ORDER BY bp.joined_at ASC
            ");
            $stmt->bindParam(':battle_id', $battle_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting battle players: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is in battle
     * 
     * @param int $battle_id Battle ID
     * @param int $user_id User ID
     * @return bool True if user is in battle
     */
    public function isPlayerInBattle($battle_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM battle_players 
                WHERE battle_id = :battle_id AND player_id = :player_id
            ");
            $stmt->bindParam(':battle_id', $battle_id, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error checking if player is in battle: " . $e->getMessage());
            return false;
        }
    }
}
