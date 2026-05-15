<?php declare(strict_types=1);

namespace skim\websocket;

// WebSocket handler interface — implemented per-route.
// Server backed by amphp/websocket-server with Revolt event loop.
// Why Revolt: PHP 8.5 Fibers are native, Revolt is the de-facto async loop.
// Future-proof: when PHP gets async/await syntax it will compile down to Fibers → drop-in.
//
// Usage:
//   $app->websocket('/ws/chat', new chat_handler());
//
// connection is passed to every method — use $conn->send() to push messages.
interface handler {
    /**
     * @ai-contract called when a WebSocket handshake succeeds and connection is open
     * @ai-contract store $conn->id() if you need to track connections in a room map
     */
    public function on_open(connection $conn): void;

    /**
     * @ai-contract called on every inbound message from the client
     * @ai-contract $message is the raw string payload (decode JSON yourself)
     */
    public function on_message(connection $conn, string $message): void;

    /**
     * @ai-contract called when the client disconnects (orderly or error)
     * @ai-contract clean up room membership here — $conn is already closed
     */
    public function on_close(connection $conn): void;

    /**
     * @ai-contract called on unhandled exception inside on_message or on_open
     * @ai-contract log and optionally close connection — never rethrow
     */
    public function on_error(connection $conn, \Throwable $e): void;
}
