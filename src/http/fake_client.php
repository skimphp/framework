<?php declare(strict_types=1);

namespace skim\http;

// Test double for client — intercepts all requests and returns stub responses.
// Why: real HTTP in tests is slow, flaky, and pollutes external systems.
// Usage: inject via constructor or service binding.
//
//   $http = client::fake(['GET https://api.example.com/users' => ['status' => 200, 'body' => [...]]]);
//   $resp = $http->get('https://api.example.com/users');
//   expect($resp->ok())->toBeTrue();
final class fake_client extends client {
    /** @var list<array{method:string,url:string,body:mixed}> */
    private array $recorded = [];
    private array $stubs;

    public function __construct(array $stubs = []) {
        $this->stubs = $stubs;
    }

    public function get(string $url, array $query = [], array $headers = []): http_response {
        return $this->fake_send('GET', $url, null);
    }

    public function post(string $url, array $data = [], array $headers = []): http_response {
        return $this->fake_send('POST', $url, $data);
    }

    public function put(string $url, array $data = [], array $headers = []): http_response {
        return $this->fake_send('PUT', $url, $data);
    }

    public function patch(string $url, array $data = [], array $headers = []): http_response {
        return $this->fake_send('PATCH', $url, $data);
    }

    public function delete(string $url, array $data = [], array $headers = []): http_response {
        return $this->fake_send('DELETE', $url, $data);
    }

    /**
     * @ai-contract asserts that a request matching method+url was made
     */
    public function assert_sent(string $method, string $url): void {
        $found = array_find(
            $this->recorded,
            fn($r) => $r['method'] === strtoupper($method) && str_contains($r['url'], $url),
        );
        if ($found === null) {
            throw new \RuntimeException("Expected {$method} {$url} to have been sent, but it was not.");
        }
    }

    /**
     * @ai-contract asserts that no requests were made
     */
    public function assert_nothing_sent(): void {
        if ($this->recorded !== []) {
            throw new \RuntimeException('Expected no HTTP requests, but ' . count($this->recorded) . ' were sent.');
        }
    }

    /**
     * @ai-contract returns all recorded requests for custom assertions
     */
    public function recorded(): array {
        return $this->recorded;
    }

    // --- internals ---

    private function fake_send(string $method, string $url, mixed $body): http_response {
        $this->recorded[] = ['method' => $method, 'url' => $url, 'body' => $body];

        $key = "{$method} {$url}";
        $stub = $this->stubs[$key]
            ?? $this->stubs[$url]
            ?? $this->stubs[$method]
            ?? null;

        if ($stub === null) {
            // Default: 200 empty body
            return new http_response(200, '{}', ['Content-Type' => 'application/json']);
        }

        if ($stub instanceof http_response) {
            return $stub;
        }

        $body_content = isset($stub['body']) && is_array($stub['body'])
            ? (string) json_encode($stub['body'])
            : (string) ($stub['body'] ?? '');

        return new http_response(
            $stub['status'] ?? 200,
            $body_content,
            $stub['headers'] ?? ['Content-Type' => 'application/json'],
        );
    }
}
