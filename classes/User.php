<?php
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @return array|false User data or false if not found
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Username
     * @return array|false User data or false if not found
     */
    public function getByUsername($username) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, password, created_at FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user by username: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new user
     * 
     * @param string $username Username
     * @param string $email Email
     * @param string $password Plain text password
     * @return int|false User ID or false on failure
     */
    public function create($username, $email, $password) {
        try {
            // Check if username exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return false;
            }
            
            // Check if email exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return false;
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert user
            $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (:username, :email, :password, NOW())");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->execute();
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Authenticate user
     * 
     * @param string $username Username
     * @param string $password Plain text password
     * @return array|false User data or false if authentication fails
     */
    public function authenticate($username, $password) {
        $user = $this->getByUsername($username);
        
        if ($user && password_verify($password, $user['password'])) {
            // Remove password from array
            unset($user['password']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Get user's character
     * 
     * @param int $user_id User ID
     * @return array|false Character data or false if not found
     */
    public function getCharacter($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.name, c.class, c.level, c.experience, c.wins, c.losses
                FROM characters c
                WHERE c.user_id = :user_id
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting character: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create character for user
     * 
     * @param int $user_id User ID
     * @param string $name Character name
     * @param string $class Character class
     * @return int|false Character ID or false on failure
     */
    public function createCharacter($user_id, $name, $class) {
        try {
            // Check if user already has a character
            $stmt = $this->pdo->prepare("SELECT id FROM characters WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return false;
            }
            
            // Check if character name exists
            $stmt = $this->pdo->prepare("SELECT id FROM characters WHERE name = :name");
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return false;
            }
            
            // Validate class
            $valid_classes = ['warrior', 'archer', 'mage'];
            if (!in_array(strtolower($class), $valid_classes)) {
                return false;
            }
            
            // Insert character
            $stmt = $this->pdo->prepare("
                INSERT INTO characters (user_id, name, class, level, experience, wins, losses, created_at)
                VALUES (:user_id, :name, :class, 1, 0, 0, 0, NOW())
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':class', $class);
            $stmt->execute();
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating character: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user's stats after battle
     * 
     * @param int $user_id User ID
     * @param bool $won Whether the user won the battle
     * @return bool Success status
     */
    public function updateBattleStats($user_id, $won) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE characters
                SET 
                    wins = wins + :win,
                    losses = losses + :loss,
                    experience = experience + :exp,
                    level = CASE 
                        WHEN experience + :exp >= level * 100 THEN level + 1
                        ELSE level
                    END
                WHERE user_id = :user_id
            ");
            
            $win = $won ? 1 : 0;
            $loss = $won ? 0 : 1;
            $exp = $won ? 50 : 10;
            
            $stmt->bindParam(':win', $win, PDO::PARAM_INT);
            $stmt->bindParam(':loss', $loss, PDO::PARAM_INT);
            $stmt->bindParam(':exp', $exp, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating battle stats: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get top players by wins
     * 
     * @param int $limit Number of players to return
     * @return array|false Array of top players or false on failure
     */
    public function getTopPlayers($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.username,
                    c.name as character_name,
                    c.class,
                    c.level,
                    c.wins,
                    c.losses
                FROM characters c
                JOIN users u ON c.user_id = u.id
                ORDER BY c.wins DESC, c.level DESC
                LIMIT :limit
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting top players: " . $e->getMessage());
            return false;
        }
    }
}
