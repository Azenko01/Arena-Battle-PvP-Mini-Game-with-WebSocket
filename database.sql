-- Arena Battle Database Schema

-- Drop tables if they exist
DROP TABLE IF EXISTS battle_results;
DROP TABLE IF EXISTS battles;
DROP TABLE IF EXISTS user_characters;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    battles_played INT DEFAULT 0,
    battles_won INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_characters table
CREATE TABLE user_characters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    character_name VARCHAR(50) NOT NULL,
    character_class ENUM('warrior', 'archer', 'mage') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, character_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create battles table
CREATE TABLE battles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    battle_id VARCHAR(36) NOT NULL UNIQUE,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL,
    winner_id VARCHAR(36) NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create battle_results table
CREATE TABLE battle_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    battle_id INT NOT NULL,
    player_id VARCHAR(36) NOT NULL,
    character_name VARCHAR(50) NOT NULL,
    character_class ENUM('warrior', 'archer', 'mage') NOT NULL,
    is_winner BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (battle_id) REFERENCES battles(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes
CREATE INDEX idx_battles_battle_id ON battles(battle_id);
CREATE INDEX idx_battle_results_battle_id ON battle_results(battle_id);
CREATE INDEX idx_battle_results_player_id ON battle_results(player_id);
CREATE INDEX idx_users_battles_won ON users(battles_won DESC);

-- Insert sample data
INSERT INTO users (id, username, password, email) VALUES
('1', 'player1', '$2y$10$abcdefghijklmnopqrstuuW
