<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Load dependencies
    require dirname(__DIR__) . '/vendor/autoload.php';
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/db.php';
    require_once __DIR__ . '/server.php';

    // Initialize server
    $port = 8080;
    $host = '127.0.0.1';

    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new ChatServer()
            )
        ),
        $port,
        $host
    );

    echo "Chat WebSocket server running on {$host}:{$port}...\n";
    $server->run();

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line " . $e->getLine() . ")\n";
    exit(1);
}