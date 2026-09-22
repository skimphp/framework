<?php declare(strict_types=1);

namespace Skim\Websocket;

/**
 * WebSocket handler interface — implement per-route for custom WebSocket logic. #AI:class
 *
 * Use when registering WebSocket endpoints via $app->websocket('/ws/chat', new ChatHandler()).
 * The server is backed by amphp/websocket-server with the Revolt event loop.
 * Connection is passed to every method — use $conn->send() to push messages.
 *
 * Example:
 *   class ChatHandler implements Handler {
 *       public function onOpen(Connection $conn): void {
 *           $conn->join('chat:general');
 *       }
 *       public function onMessage(Connection $conn, string $message): void {
 *           Connection::broadcast('chat:general', $message, except_id: $conn->id());
 *       }
 *       public function onClose(Connection $conn): void {
 *           $conn->leave('chat:general');
 *       }
 *       public function onError(Connection $conn, \Throwable $e): void {
 *           Log::error("WS error: " . $e->getMessage());
 *       }
 *   }
 *
 * Testing: Mock connection objects and call handler methods directly.
 *
 * #AI:class
 */
interface Handler {
    /**
     * Called when a WebSocket handshake succeeds and the connection is open. #AI:onOpen
     *
     * Store $conn->id() or call $conn->join() to track connections in rooms.
     *
     * @param \Skim\Websocket\Connection $conn The newly opened connection.
     */
    public function onOpen(\Skim\Websocket\Connection $conn): void;

    /**
     * Called on every inbound message from the client. #AI:onMessage
     *
     * The $message is the raw string payload — decode JSON yourself.
     *
     * @param \Skim\Websocket\Connection $conn The sending connection.
     * @param string     $message Raw string payload from the client.
     */
    public function onMessage(\Skim\Websocket\Connection $conn, string $message): void;

    /**
     * Called when the client disconnects (orderly or error). #AI:onClose
     *
     * Clean up room membership here — $conn is already closed.
     *
     * @param \Skim\Websocket\Connection $conn The closed connection.
     */
    public function onClose(\Skim\Websocket\Connection $conn): void;

    /**
     * Called on unhandled exception inside on_message or on_open. #AI:onError
     *
     * Log the error and optionally close the connection. Never rethrow —
     * the event loop would crash.
     *
     * @param \Skim\Websocket\Connection $conn The connection that caused the error.
     * @param \Throwable $e    The unhandled exception.
     */
    public function onError(\Skim\Websocket\Connection $conn, \Throwable $e): void;
}

#AI:class
#AI symbol: Skim\Websocket\Handler
#AI source_path: src/Websocket/Handler.php
#AI title: handler
#AI description: WebSocket handler interface for per-route WebSocket logic with open/message/close/error callbacks.
#AI role: websocket handler interface
#AI layer: websocket
#AI badges: [interface; websocket; handler; event-loop]
#AI intro: `handler` defines the contract for WebSocket route handlers. Implement this interface and register it via `$app->websocket()` to handle connection lifecycle events.
#AI lifecycle: one implementation per WebSocket route, methods called by the amphp server on events
#AI fallback: n/a — interface only
#AI test_seam: mock connection objects and call handler methods directly
#AI invariants: [onError must never rethrow — the event loop would crash; onClose is called for both orderly and error disconnects; $message in onMessage is always a raw string]
#AI core_behaviors: [Four lifecycle callbacks: open, message, close, error; Connection object passed to every callback for room management and messaging]
#AI warnings: [onError must never rethrow exceptions — doing so crashes the Revolt event loop and kills all connections]
#AI notes: The server uses amphp/websocket-server with the Revolt event loop. PHP 8.5 Fibers are native, making Revolt the de-facto async loop.
#AI owns: nothing — contract only
#AI entry_points: [onOpen; onMessage; onClose; onError]
#AI config_reads: []
#AI non_goals: [Does not manage the WebSocket server; Does not handle HTTP upgrade; Does not provide authentication]
#AI side_effects: [Handler implementations typically call Connection::send/broadcast/join/leave]
#AI flow: amphp server -> Handler::onOpen/onMessage/onClose/onError -> connection API
#AI lifecycle_steps: [WebSocket handshake -> Handler::onOpen($conn); -> client message -> Handler::onMessage($conn, $msg); -> disconnect -> Handler::onClose($conn); -> exception -> Handler::onError($conn, $e)]
#AI section_order: [Handler API; Architecture]
#AI architectural_notes: Interface kept minimal — four callbacks covering the full WebSocket lifecycle. The amphp server handles connection management, framing, and the event loop.

#AI:onOpen
#AI group: Handler API
#AI frequency: high
#AI signature: public function onOpen(Connection $conn): void
#AI contract: Called when a WebSocket handshake succeeds. Use to join rooms or track the connection.
#AI param_details: [{name: $conn | type: connection | required: true | desc: The newly opened connection.}]

#AI:onMessage
#AI group: Handler API
#AI frequency: high
#AI signature: public function onMessage(Connection $conn, string $message): void
#AI contract: Called on every inbound message. The payload is a raw string — decode JSON yourself.
#AI param_details: [{name: $conn | type: connection | required: true | desc: The sending connection.}; {name: $message | type: string | required: true | desc: Raw string payload from the client.}]

#AI:onClose
#AI group: Handler API
#AI frequency: high
#AI signature: public function onClose(Connection $conn): void
#AI contract: Called when the client disconnects. Clean up room membership — $conn is already closed.
#AI param_details: [{name: $conn | type: connection | required: true | desc: The closed connection.}]

#AI:onError
#AI group: Handler API
#AI frequency: low
#AI signature: public function onError(Connection $conn, \Throwable $e): void
#AI contract: Called on unhandled exception in onMessage or onOpen. Log and optionally close. Never rethrow.
#AI param_details: [{name: $conn | type: connection | required: true | desc: The connection that caused the error.}; {name: $e | type: \Throwable | required: true | desc: The unhandled exception.}]
#AI warnings: [Never rethrow — the Revolt event loop would crash and kill all connections]
