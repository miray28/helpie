<?php
function sendWebSocketNotification($message, $type = 'info') {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    try {
        $client = new \WebSocket\Client('ws://localhost:8081', [
            'context' => $context
        ]);

        $client->send(json_encode([
            'message' => $message,
            'type' => $type
        ]));

        $client->close();
        return true;
    } catch (Exception $e) {
        error_log("WebSocket notification error: " . $e->getMessage());
        return false;
    }
}