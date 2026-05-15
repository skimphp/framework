<?php declare(strict_types=1);

namespace skim\http;

// Thin wrapper over PHP's native streams (file_get_contents + stream_context_create).
// No Guzzle, no Symfony HttpClient dependency for core.
// Why native streams: zero deps, always available, sufficient for 90% of API calls.
// For high-concurrency async HTTP, swap with symfony/http-client via module:add http-async.
//
// All methods return a response value object — never throw on non-2xx status.
// Caller decides whether 404 is an error (match on $resp->status()).
class client {
    private array  $default_headers = ['Content-Type' => 'application/json'];
    private int    $timeout         = 10;
    private bool   $verify_ssl      = true;
    private ?string $base_url       = null;

    public function __construct(array $options = []) {
        if (isset($options['base_url'])) {
            $this->base_url = rtrim($options['base_url'], '/');
        }
        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
        }
        if (isset($options['verify_ssl'])) {
            $this->verify_ssl = (bool) $options['verify_ssl'];
        }
        if (isset($options['headers'])) {
            $this->default_headers = array_merge($this->default_headers, $options['headers']);
        }
    }

    /**
     * @ai-contract sends GET request, returns http_response
     * @ai-contract $query array is appended as ?key=val URL params
     */
    public function get(string $url, array $query = [], array $headers = []): http_response {
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $this->send('GET', $url, null, $headers);
    }

    /**
     * @ai-contract sends POST with JSON body, returns http_response
     */
    public function post(string $url, array $data = [], array $headers = []): http_response {
        return $this->send('POST', $url, $data, $headers);
    }

    /**
     * @ai-contract sends PUT with JSON body
     */
    public function put(string $url, array $data = [], array $headers = []): http_response {
        return $this->send('PUT', $url, $data, $headers);
    }

    /**
     * @ai-contract sends PATCH with JSON body
     */
    public function patch(string $url, array $data = [], array $headers = []): http_response {
        return $this->send('PATCH', $url, $data, $headers);
    }

    /**
     * @ai-contract sends DELETE, optional JSON body
     */
    public function delete(string $url, array $data = [], array $headers = []): http_response {
        return $this->send('DELETE', $url, $data ?: null, $headers);
    }

    /**
     * @ai-contract returns a fake_client instance for tests — records all requests
     */
    public static function fake(array $stubs = []): fake_client {
        return new fake_client($stubs);
    }

    // --- internals ---

    private function send(string $method, string $url, ?array $body, array $extra_headers): http_response {
        $full_url = $this->base_url !== null ? $this->base_url . '/' . ltrim($url, '/') : $url;
        $headers  = array_merge($this->default_headers, $extra_headers);
        $content  = $body !== null ? json_encode($body) : null;

        $header_lines = array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys($headers),
            array_values($headers),
        );

        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $header_lines),
                'content'       => $content,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,  // don't throw on non-2xx — return response instead
            ],
            'ssl' => [
                'verify_peer'      => $this->verify_ssl,
                'verify_peer_name' => $this->verify_ssl,
            ],
        ];

        $ctx  = stream_context_create($opts);
        $raw  = @file_get_contents($full_url, false, $ctx);
        $meta = $http_response_header ?? [];

        return http_response::from_stream($raw === false ? '' : $raw, $meta);
    }
}
