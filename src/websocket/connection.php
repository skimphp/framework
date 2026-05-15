<?php declare(strict_types=1);

namespace skim\websocket;

// Abstracts a single WebSocket connection — wraps the underlying amphp connection.
// Room support: connections join/leave string room names.
// Rooms are process-scoped (array) — use Redis pub/sub for multi-process room fanout.
class connection {
    private static array $rooms = [];    // ['room_name' => [conn_id => connection]]

    public function __construct(
        private readonly string $id,
        private readonly mixed  $raw_conn,    // amphp\websocket\WebsocketClient or mock
    ) {}

    /**
     * @ai-contract returns unique connection ID (UUID assigned at handshake)
     */
    public function id(): string {
        return $this->id;
    }

    /**
     * @ai-contract sends a string or JSON-encoded array to this client
     */
    public function send(string|array $message): void {
        $payload = is_array($message) ? (string) json_encode($message) : $message;
        if (method_exists($this->raw_conn, 'sendText')) {
            $this->raw_conn->sendText($payload);
        }
    }

    /**
     * @ai-contract closes this connection with an optional code and reason
     */
    public function close(int $code = 1000, string $reason = ''): void {
        if (method_exists($this->raw_conn, 'close')) {
            $this->raw_conn->close($code, $reason);
        }
    }

    /**
     * @ai-contract adds this connection to a named room
     */
    public function join(string $room): void {
        self::$rooms[$room][$this->id] = $this;
    }

    /**
     * @ai-contract removes this connection from a named room
     */
    public function leave(string $room): void {
        unset(self::$rooms[$room][$this->id]);
        if (empty(self::$rooms[$room])) {
            unset(self::$rooms[$room]);
        }
    }

    /**
     * @ai-contract broadcasts a message to all connections in a room except optionally $except_id
     */
    public static function broadcast(string $room, string|array $message, ?string $except_id = null): void {
        foreach (self::$rooms[$room] ?? [] as $id => $conn) {
            if ($except_id !== null && $id === $except_id) {
                continue;
            }
            $conn->send($message);
        }
    }

    /**
     * @ai-contract returns IDs of all connections in a room
     */
    public static function room_ids(string $room): array {
        return array_keys(self::$rooms[$room] ?? []);
    }

    /**
     * @ai-contract for tests — reset room map
     */
    public static function reset_rooms(): void {
        self::$rooms = [];
    }
}
