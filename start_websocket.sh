#!/bin/bash

# Arena Battle WebSocket Server Startup Script
# This script starts the PHP WebSocket server for the Arena Battle game

echo "🎮 Starting Arena Battle WebSocket Server..."
echo "================================================"

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP is not installed or not in PATH"
    echo "Please install PHP 8.0 or higher"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "📋 PHP Version: $PHP_VERSION"

# Check if composer dependencies are installed
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    if command -v composer &> /dev/null; then
        composer install
    else
        echo "❌ Error: Composer is not installed"
        echo "Please install Composer and run 'composer install'"
        exit 1
    fi
fi

# Check if config file exists
if [ ! -f "config/config.php" ]; then
    echo "❌ Error: config/config.php not found"
    echo "Please create the configuration file"
    exit 1
fi

# Check if the WebSocket server file exists
if [ ! -f "game/websocket-server.php" ]; then
    echo "❌ Error: game/websocket-server.php not found"
    exit 1
fi

# Create logs directory if it doesn't exist
mkdir -p logs

# Function to handle cleanup on script termination
cleanup() {
    echo ""
    echo "🛑 Shutting down WebSocket server..."
    kill $SERVER_PID 2>/dev/null
    echo "✅ Server stopped"
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

# Start the WebSocket server
echo "🚀 Starting WebSocket server on port 8080..."
echo "📡 Server will be accessible at ws://localhost:8080"
echo "📝 Logs will be saved to logs/websocket.log"
echo ""
echo "Press Ctrl+C to stop the server"
echo "================================================"

# Start server and capture PID
php game/websocket-server.php 2>&1 | tee logs/websocket.log &
SERVER_PID=$!

# Wait for the server process
wait $SERVER_PID
