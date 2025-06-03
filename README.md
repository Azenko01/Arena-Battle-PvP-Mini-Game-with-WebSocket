# 🎮 Arena Battle - Real-time Multiplayer Game

A real-time multiplayer arena battle game built with **Next.js** (frontend) and **PHP WebSocket server** (backend).

## 🚀 Features

- **Real-time multiplayer combat** with WebSocket connections
- **3 Character classes**: Warrior, Archer, Mage (each with unique stats)
- **Live chat system** during battles
- **Responsive arena** with grid-based movement
- **Health bars and visual feedback**
- **Attack range indicators**
- **Battle status tracking** (waiting, active, finished)

## 🛠️ Tech Stack

### Frontend
- **Next.js 14** with App Router
- **TypeScript** for type safety
- **Tailwind CSS** for styling
- **shadcn/ui** components
- **Custom WebSocket hook** for real-time communication

### Backend
- **PHP 8.0+** WebSocket server
- **Ratchet/ReactPHP** for WebSocket handling
- **MySQL** database for persistence
- **Composer** for dependency management

## 📋 Prerequisites

- **Node.js 18+** and npm/yarn
- **PHP 8.0+** with CLI
- **Composer** (PHP dependency manager)
- **MySQL 5.7+** or **MariaDB**

## 🔧 Installation

### 1. Clone the Repository
\`\`\`bash
git clone <your-repo-url>
cd arena-battle
\`\`\`

### 2. Install Frontend Dependencies
\`\`\`bash
npm install
# or
yarn install
\`\`\`

### 3. Install Backend Dependencies
\`\`\`bash
composer install
\`\`\`

### 4. Database Setup
\`\`\`bash
# Create database and tables
mysql -u root -p < database.sql

# Or run the script
composer run install-db
\`\`\`

### 5. Configure Database
Edit `config/config.php` with your database credentials:
\`\`\`php
define('DB_HOST', 'localhost');
define('DB_NAME', 'arena_battle');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
\`\`\`

## 🚀 Running the Game

### 1. Start the WebSocket Server
\`\`\`bash
# Using the startup script (recommended)
./start_websocket.sh

# Or manually
php game/websocket-server.php

# Or using Composer
composer run start-server
\`\`\`

### 2. Start the Frontend
\`\`\`bash
npm run dev
# or
yarn dev
\`\`\`

### 3. Open the Game
Navigate to `http://localhost:3000` in your browser.

## 🎮 How to Play

### Character Classes

| Class | Health | Damage | Range | Speed | Special |
|-------|--------|--------|-------|-------|---------|
| ⚔️ **Warrior** | 120 | 25 | 50px | 4 | High health, close combat |
| 🏹 **Archer** | 80 | 20 | 150px | 6 | Long range attacks |
| 🔮 **Mage** | 70 | 30 | 100px | 5 | High damage, magic attacks |

### Controls
- **Movement**: WASD or Arrow Keys
- **Attack**: Click on enemy, then press Space/Enter
- **Chat**: Type in the chat box on the right

### Game Flow
1. **Join Battle**: Enter your details and battle ID
2. **Wait for Players**: Battle starts when 2+ players join
3. **Fight**: Move around the arena and attack enemies
4. **Win**: Be the last player standing!

### Class Advantages
- **Warrior** beats **Archer** (1.2x damage)
- **Archer** beats **Mage** (1.2x damage)  
- **Mage** beats **Warrior** (1.2x damage)

## 🏗️ Project Structure

\`\`\`
arena-battle/
├── app/
│   ├── page.tsx                 # Main game entry point
│   ├── globals.css              # Global styles
│   └── layout.tsx               # Root layout
├── components/
│   ├── Arena.tsx                # Main game arena component
│   ├── game-interface.tsx       # Complete game UI
│   └── ui/                      # UI components
│       ├── chat.tsx             # Chat component
│       ├── player-list.tsx      # Player list sidebar
│       └── ...                  # shadcn/ui components
├── hooks/
│   └── useWebSocket.ts          # WebSocket connection hook
├── lib/
│   └── gameLogic.ts             # Game logic utilities
├── game/
│   └── websocket-server.php     # PHP WebSocket server
├── config/
│   └── config.php               # PHP configuration
├── classes/
│   ├── WebSocketServer.php      # Main server class
│   ├── User.php                 # User management
│   └── Game.php                 # Game logic
├── database.sql                 # Database schema
├── composer.json                # PHP dependencies
└── start_websocket.sh           # Server startup script
\`\`\`

## 🔧 Development

### Frontend Development
\`\`\`bash
# Start development server
npm run dev

# Build for production
npm run build

# Type checking
npm run type-check
\`\`\`

### Backend Development
\`\`\`bash
# Start WebSocket server with auto-reload
php game/websocket-server.php

# Run tests
composer run test

# Check PHP syntax
php -l game/websocket-server.php
\`\`\`

## 🐛 Troubleshooting

### WebSocket Connection Issues
- Ensure the WebSocket server is running on port 8080
- Check firewall settings
- Verify PHP extensions: `php -m | grep -E "(sockets|pcntl)"`

### Database Connection Issues
- Verify MySQL is running
- Check database credentials in `config/config.php`
- Ensure database and tables exist

### Performance Issues
- Monitor WebSocket server logs: `tail -f logs/websocket.log`
- Check browser console for JavaScript errors
- Verify network latency

## 📝 API Reference

### WebSocket Message Types

#### Client → Server
\`\`\`typescript
// Join battle
{
  type: 'init',
  battleId: string,
  data: {
    playerId: string,
    username: string,
    characterName: string,
    characterClass: 'warrior' | 'archer' | 'mage'
  }
}

// Move player
{
  type: 'player_moved',
  position: { x: number, y: number }
}

// Attack player
{
  type: 'player_attacked',
  targetId: string
}

// Send chat message
{
  type: 'chat_message',
  data: { message: string }
}
\`\`\`

#### Server → Client
\`\`\`typescript
// Game state update
{
  type: 'game_state',
  players: Player[],
  gameStatus: 'waiting' | 'active' | 'finished'
}

// Player joined
{
  type: 'player_joined',
  data: Player
}

// Battle events
{
  type: 'battle_started' | 'battle_ended',
  winner?: string
}
\`\`\`

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Ratchet](http://socketo.me/) for WebSocket server
- [shadcn/ui](https://ui.shadcn.com/) for UI components
- [Next.js](https://nextjs.org/) for the frontend framework
- [Tailwind CSS](https://tailwindcss.com/) for styling

---

**Happy Gaming! 🎮**

For support or questions, please open an issue on GitHub.
