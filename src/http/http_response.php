<?php declare(strict_types=1);

namespace skim\http;

// Immutable value object wrapping an HTTP response.
// Clone-with (PHP 8.5) can derive modified copies: clone($r, ['status' => 200]).
final class http_response {
    public function __construct(
        public readonly int    $status,
        public readonly string $body,
        public readonly array  $headers,
    ) {}

    /**
     * @ai-contract parses $http_response_header meta from PHP streams into an http_response
     */
    public static function from_stream(string $body, array $meta): static {
        $status  = 0;
        $headers = [];

        foreach ($meta as $line) {
            if (preg_match('#^HTTP/[\d.]+ (\d+)#', $line, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($line, ':')) {
                [$key, $val] = explode(':', $line, 2);
                $headers[trim($key)] = trim($val);
            }
        }

        return new static($status, $body, $headers);
    }

    /**
     * @ai-contract parses JSON body, returns array; empty array on invalid JSON
     */
    public function json(): array {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @ai-contract returns true for 2xx status codes
     */
    public function ok(): bool {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * @ai-contract returns a named response header, null if absent
     */
    public function header(string $name): ?string {
        return $this->headers[$name] ?? null;
    }
}
