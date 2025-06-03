<?php
/**
 * Arena Battle WebSocket Server
 * 
 * This script starts the WebSocket server for the Arena Battle game.
 * It handles player connections, movements, attacks, and chat messages.
 * 
 * Usage: php game/websocket-server.php
 * 
 * @author Your Name
 * @version 1.0
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../classes/WebSocketServer.php';
require __DIR__ . '/../classes/Game.php';
require __DIR__ . '/../classes/User.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Create WebSocket server instance
$server = new WebSocketServer();

// Set up the server
$wsServer = new WsServer($server);
$httpServer = new HttpServer($wsServer);

// Get port from config or use default
$port = defined('WEBSOCKET_PORT') ? WEBSOCKET_PORT : 8080;

// Create and run the server
$io = IoServer::factory($httpServer, $port, '0.0.0.0');

echo "WebSocket server started on port $port\n";
echo "Press Ctrl+C to stop the server\n";

// Run the server
$io->run();
