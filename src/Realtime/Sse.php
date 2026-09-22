<?php declare(strict_types=1);

namespace Skim\Realtime;

/**
 * Server-Sent Events helper for long-lived HTTP push streams.
 *
 * Use when a controller needs to push data to the browser over SSE.
 * Passed to the $res->stream() callback; output buffering must be
 * disabled before use (Response::stream() handles this).
 *
 * Example:
 *   return $res->stream(function(Sse $sse) {
 *       $sse->send(['count' => 1], event: 'update');
 *       sleep(1);
 *       $sse->ping();
 *       $sse->close();
 *   });
 *
 * Testing: SSE output is echo-based; capture with ob_start() in tests.
 *
 * #AI:class
 */
class Sse {
    /**
     * Sends one SSE event to the client and flushes immediately. #AI:send
     *
     * Arrays are JSON-encoded; strings are sent as-is. Named events let
     * the client listen with addEventListener(event, handler).
     *
     * Example:
     *   $sse->send(['user' => $name], event: 'joined', id: '42');
     *
     * @param mixed       $data  Array (JSON-encoded) or string payload.
     * @param string|null $event Optional event name for client addEventListener.
     * @param string|null $id    Optional event ID for client Last-Event-ID tracking.
     */
    public function send(mixed $data, ?string $event = null, ?string $id = null): void {
        if (connection_aborted()) {
            return;
        }
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
     * Sends a comment line to keep idle proxies from closing the connection. #AI:ping
     *
     * Send every 15-30 seconds for stable long-lived streams behind nginx
     * or load balancers with idle timeouts.
     */
    public function ping(): void {
        if (connection_aborted()) {
            return;
        }
        echo ": ping\n\n";
        $this->flush();
    }

    /**
     * Sends a final close event and signals stream end. #AI:close
     */
    public function close(): void {
        $this->send('close', event: 'close');
    }

    protected function flush(): void {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}

#AI:class
#AI symbol: Skim\Realtime\Sse
#AI source_path: src/Realtime/Sse.php
#AI title: sse
#AI description: Server-Sent Events helper for pushing data over long-lived HTTP connections.
#AI role: SSE stream helper
#AI layer: realtime
#AI badges: [sse; streaming; realtime; push]
#AI intro: `sse` provides methods to send events, pings, and close signals over a Server-Sent Events stream. It is passed to the Response::stream() callback and handles SSE wire format and output flushing.
#AI lifecycle: created per-stream by Response::stream() callback; lives until close() or client disconnect
#AI test_seam: capture output with ob_start()/ob_get_clean() in tests
#AI invariants: [Output buffering must be disabled before use; Each send() flushes immediately; ping() sends SSE comment line]
#AI core_behaviors: [Formats data as SSE wire protocol; JSON-encodes arrays; Flushes output buffer after each event]
#AI owns: output stream
#AI entry_points: [send; ping; close]
#AI config_reads: []
#AI non_goals: [Does not manage connection lifecycle; Does not handle reconnection; Does not disable output buffering]
#AI side_effects: [Writes to PHP output buffer; Calls flush()]
#AI flow: controller -> res->stream(fn($sse) => $sse->send(...)) -> echo SSE format -> flush()
#AI section_order: [Stream API; Connection Management]

#AI:send
#AI group: Stream API
#AI frequency: high
#AI signature: public function send(mixed $data, ?string $event = null, ?string $id = null): void
#AI contract: Formats and sends one SSE event. Arrays are JSON-encoded. Named events allow client-side addEventListener. Flushes immediately.
#AI param_details: [{name: $data | type: mixed | required: true | desc: Array (JSON-encoded) or string payload.}; {name: $event | type: ?string | required: false | desc: Optional event name for client addEventListener.}; {name: $id | type: ?string | required: false | desc: Optional event ID for Last-Event-ID tracking.}]
#AI side_effects: [Writes to output buffer and flushes]

#AI:ping
#AI group: Connection Management
#AI frequency: medium
#AI signature: public function ping(): void
#AI contract: Sends an SSE comment line (`: ping`) to prevent idle proxy timeouts. Call every 15-30 seconds.
#AI side_effects: [Writes to output buffer and flushes]

#AI:close
#AI group: Connection Management
#AI frequency: low
#AI signature: public function close(): void
#AI contract: Sends a final event named 'close' to signal stream end to the client.
#AI side_effects: [Writes to output buffer and flushes]
