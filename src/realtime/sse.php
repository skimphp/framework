<?php declare(strict_types=1);

namespace skim\realtime;

// Server-Sent Events helper. Passed to the $res->stream() callback.
// Output buffering must be disabled before use — response::stream() handles this.
//
// SSE is a long-running HTTP connection — PHP-FPM execution time limits apply.
// nginx: set proxy_read_timeout 3600; to prevent upstream timeout on long streams.
class sse {
    /**
     * @ai-contract sends one SSE event to the client and flushes immediately
     * @ai-contract $data array is JSON-encoded; string sent as-is
     * @ai-contract named events: client listens with addEventListener(event, handler)
     */
    public function send(mixed $data, ?string $event = null, ?string $id = null): void {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        if ($event !== null) {
            echo "event: {$event}\n";
        }
        $payload = is_array($data) ? json_encode($data) : $data;
        foreach (explode("\n", (string) $payload) as $line) {
            echo "data: {$line}\n";
        }
        echo "\n";
        $this->flush();
    }

    /**
     * @ai-contract sends a comment line (: ping) to keep idle proxies from closing the connection
     * @ai-contract send every 15–30 seconds for stable long-lived streams
     */
    public function ping(): void {
        echo ": ping\n\n";
        $this->flush();
    }

    /**
     * @ai-contract sends a final event and closes the stream
     */
    public function close(): void {
        $this->send('close', event: 'close');
    }

    private function flush(): void {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
