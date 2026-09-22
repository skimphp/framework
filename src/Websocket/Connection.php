<?php declare(strict_types=1);

namespace Skim\Websocket;

/**
 * Abstracts a single WebSocket connection with room-based broadcasting. #AI:class
 *
 * Use inside handler implementations to send messages, manage room membership,
 * and broadcast to groups. Rooms are process-scoped (in-memory array) — use
 * Redis pub/sub for multi-process room fanout.
 *
 * Example:
 *   $conn->join('chat:general');
 *   $conn->send(['type' => 'message', 'text' => 'Hello']);
 *   Connection::broadcast('chat:general', ['type' => 'notification'], except_id: $conn->id());
 *
 * Testing: Use resetRooms() in tearDown() to clear room state.
 *
 * #AI:class
 */
class Connection {
    private static array $rooms = [];    // ['room_name' => [conn_id => connection]]

    public function __construct(
        private readonly string $id,
        private readonly mixed  $rawConn,    // amphp\websocket\WebsocketClient or mock
    ) {}

    /**
     * Returns the unique connection ID. #AI:id
     */
    public function id(): string {
        return $this->id;
    }

    /**
     * Sends a string or JSON-encoded array to this client. #AI:send
     *
     * Arrays are automatically JSON-encoded before sending. No-ops if the
     * underlying connection does not support sendText().
     *
     * @param string|array $message Raw string or array (auto JSON-encoded).
     */
    public function send(string|array $message): void {
        $payload = is_array($message) ? (string) json_encode($message) : $message;
        if (method_exists($this->rawConn, 'sendText')) {
            $this->rawConn->sendText($payload);
        }
    }

    /**
     * Closes this connection with an optional code and reason. #AI:close
     *
     * @param int    $code   WebSocket close code (default 1000 = normal).
     * @param string $reason Human-readable close reason sent to client.
     */
    public function close(int $code = 1000, string $reason = ''): void {
        if (method_exists($this->rawConn, 'close')) {
            $this->rawConn->close($code, $reason);
        }
    }

    /**
     * Adds this connection to a named room. #AI:join
     *
     * @param string $room Room identifier (e.g., 'chat:general').
     */
    public function join(string $room): void {
        self::$rooms[$room][$this->id] = $this;
    }

    /**
     * Removes this connection from a named room. #AI:leave
     *
     * Cleans up the room entry if no connections remain.
     *
     * @param string $room Room identifier to leave.
     */
    public function leave(string $room): void {
        unset(self::$rooms[$room][$this->id]);
        if (empty(self::$rooms[$room])) {
            unset(self::$rooms[$room]);
        }
    }

    /**
     * Broadcasts a message to all connections in a room. #AI:broadcast
     *
     * Optionally excludes one connection (typically the sender) via $except_id.
     *
     * Example:
     *   Connection::broadcast('chat:general', ['text' => 'Hello'], except_id: $sender_id);
     *
     * @param string       $room      Room to broadcast to.
     * @param string|array $message   Payload (arrays are JSON-encoded per connection).
     * @param string|null  $exceptId Connection ID to exclude from broadcast.
     */
    public static function broadcast(string $room, string|array $message, ?string $exceptId = null): void {
        foreach (self::$rooms[$room] ?? [] as $id => $conn) {
            if ($exceptId !== null && $id === $exceptId) {
                continue;
            }
            $conn->send($message);
        }
    }

    /**
     * Returns connection IDs of all connections in a room. #AI:roomIds
     *
     * @param string $room Room identifier to query.
     */
    public static function roomIds(string $room): array {
        return array_keys(self::$rooms[$room] ?? []);
    }

    /**
     * Clears the entire room map (testing only). #AI:resetRooms
     *
     * Call in tearDown() to prevent room state leaking between tests.
     */
    public static function resetRooms(): void {
        self::$rooms = [];
    }
}

#AI:class
#AI symbol: Skim\Websocket\Connection
#AI source_path: src/Websocket/Connection.php
#AI title: connection
#AI description: WebSocket connection abstraction with room-based broadcasting and process-scoped room management.
#AI role: websocket connection
#AI layer: websocket
#AI badges: [websocket; connection; rooms; broadcast]
#AI intro: `connection` wraps an underlying amphp WebSocket connection and adds room-based grouping. Rooms are stored in a static process-scoped array — use Redis pub/sub for multi-process fanout.
#AI lifecycle: created per WebSocket handshake by the server, destroyed on disconnect
#AI fallback: n/a — wraps real connection
#AI test_seam: resetRooms() clears room state between tests
#AI invariants: [Rooms are process-scoped static arrays; broadcast() skips the excluded connection ID; leave() cleans up empty rooms; send() auto-JSON-encodes arrays]
#AI core_behaviors: [Room management via join/leave; Broadcasting to room members with optional sender exclusion; JSON auto-encoding for array messages]
#AI warnings: [Rooms are process-local — connections in different PHP workers cannot see each other's rooms; Use Redis pub/sub for multi-process room fanout]
#AI notes: The raw connection is duck-typed (method_exists checks) to support both real amphp connections and test mocks.
#AI owns: static rooms array, raw connection reference
#AI entry_points: [id; send; close; join; leave; broadcast; roomIds; resetRooms]
#AI config_reads: []
#AI non_goals: [Does not handle WebSocket handshake; Does not manage connection lifecycle beyond close(); Does not provide cross-process room synchronization]
#AI side_effects: [send() writes to the WebSocket; close() terminates the connection; join/leave mutate the static rooms array; broadcast() sends to multiple connections]
#AI flow: Handler::onMessage() -> Connection::send/broadcast/join/leave -> amphp WebSocket
#AI lifecycle_steps: [WebSocket handshake -> new Connection($id, $raw); -> Handler::onOpen($conn); -> $conn->join('room'); -> Handler::onMessage() -> $conn->send() or Connection::broadcast()]
#AI section_order: [Connection API; Room Management; Broadcasting; Testing Hooks; Architecture]
#AI architectural_notes: Rooms are intentionally process-scoped. For multi-server WebSocket deployments, layer Redis pub/sub on top of the room API.

#AI:id
#AI group: Connection API
#AI frequency: high
#AI signature: public function id(): string
#AI contract: Returns the unique connection ID assigned at handshake.
#AI return_detail: {type: string | desc: Unique connection identifier.}

#AI:send
#AI group: Connection API
#AI frequency: high
#AI signature: public function send(string|array $message): void
#AI contract: Sends a string or JSON-encoded array to this client. Arrays are auto-encoded. No-ops if the underlying connection lacks sendText().
#AI param_details: [{name: $message | type: string|array | required: true | desc: Raw string or array (auto JSON-encoded).}]
#AI side_effects: [Writes to the WebSocket connection]

#AI:close
#AI group: Connection API
#AI frequency: low
#AI signature: public function close(int $code = 1000, string $reason = ''): void
#AI contract: Closes this connection with an optional WebSocket close code and reason.
#AI param_details: [{name: $code | type: int | required: false | desc: WebSocket close code, default 1000 (normal).}; {name: $reason | type: string | required: false | desc: Human-readable close reason.}]
#AI side_effects: [Terminates the WebSocket connection]

#AI:join
#AI group: Room Management
#AI frequency: high
#AI signature: public function join(string $room): void
#AI contract: Adds this connection to a named room.
#AI param_details: [{name: $room | type: string | required: true | desc: Room identifier (e.g., 'chat:general').}]
#AI side_effects: [Mutates the static rooms array]

#AI:leave
#AI group: Room Management
#AI frequency: medium
#AI signature: public function leave(string $room): void
#AI contract: Removes this connection from a named room. Cleans up empty room entries.
#AI param_details: [{name: $room | type: string | required: true | desc: Room identifier to leave.}]
#AI side_effects: [Mutates the static rooms array]

#AI:broadcast
#AI group: Broadcasting
#AI frequency: high
#AI signature: public static function broadcast(string $room, string|array $message, ?string $exceptId = null): void
#AI contract: Sends a message to all connections in a room, optionally excluding one connection by ID.
#AI param_details: [{name: $room | type: string | required: true | desc: Room to broadcast to.}; {name: $message | type: string|array | required: true | desc: Payload (arrays are JSON-encoded per connection).}; {name: $exceptId | type: ?string | required: false | desc: Connection ID to exclude from broadcast.}]
#AI side_effects: [Sends to multiple WebSocket connections]

#AI:roomIds
#AI group: Room Management
#AI frequency: low
#AI signature: public static function roomIds(string $room): array
#AI contract: Returns connection IDs of all connections in a room.
#AI param_details: [{name: $room | type: string | required: true | desc: Room identifier to query.}]
#AI return_detail: {type: array | desc: Array of connection ID strings.}

#AI:resetRooms
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function resetRooms(): void
#AI contract: Clears the entire room map. Use in test tearDown() to prevent state leakage.
#AI side_effects: [Clears the static rooms array]
