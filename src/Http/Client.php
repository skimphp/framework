<?php declare(strict_types=1);

namespace Skim\Http;

/**
 * Zero-dependency HTTP client using PHP native streams.
 *
 * Use for outbound API calls when Guzzle or Symfony HttpClient are not
 * needed. All methods return an http_response value object — never throw
 * on non-2xx status. The caller decides whether a status code is an error.
 *
 * Example:
 *   $http = new Client(['base_url' => 'https://api.example.com', 'timeout' => 5]);
 *   $resp = $http->get('/users', ['page' => 1]);
 *   if ($resp->ok()) { $users = $resp->json(); }
 *
 * Testing: Use Client::fake() to get a fake_client that records requests.
 *
 * #AI:class
 */
class Client {
    private array  $defaultHeaders = ['Content-Type' => 'application/json'];
    private int    $timeout         = 10;
    private bool   $verifySsl      = true;
    private ?string $baseUrl       = null;

    public function __construct(array $options = []) {
        if (isset($options['base_url'])) {
            $this->baseUrl = rtrim($options['base_url'], '/');
        }
        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
        }
        if (isset($options['verify_ssl'])) {
            $this->verifySsl = (bool) $options['verify_ssl'];
        }
        if (isset($options['headers'])) {
            $this->defaultHeaders = array_merge($this->defaultHeaders, $options['headers']);
        }
    }

    /**
     * Sends a GET request. #AI:get
     *
     * The $query array is appended as ?key=val URL params.
     *
     * @param string $url     Target URL or path (prepended with base_url).
     * @param array  $query   Query params appended as URL string.
     * @param array  $headers Additional headers merged with defaults.
     */
    public function get(string $url, array $query = [], array $headers = []): \Skim\Http\HttpResponse {
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $this->send('GET', $url, null, $headers);
    }

    /**
     * Sends a POST request with JSON body. #AI:post
     *
     * @param string $url     Target URL or path.
     * @param array  $data    Request body, JSON-encoded.
     * @param array  $headers Additional headers merged with defaults.
     */
    public function post(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->send('POST', $url, $data, $headers);
    }

    /**
     * Sends a PUT request with JSON body. #AI:put
     *
     * @param string $url     Target URL or path.
     * @param array  $data    Request body, JSON-encoded.
     * @param array  $headers Additional headers merged with defaults.
     */
    public function put(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->send('PUT', $url, $data, $headers);
    }

    /**
     * Sends a PATCH request with JSON body. #AI:patch
     *
     * @param string $url     Target URL or path.
     * @param array  $data    Request body, JSON-encoded.
     * @param array  $headers Additional headers merged with defaults.
     */
    public function patch(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->send('PATCH', $url, $data, $headers);
    }

    /**
     * Sends a DELETE request with optional JSON body. #AI:delete
     *
     * @param string $url     Target URL or path.
     * @param array  $data    Optional request body, JSON-encoded when non-empty.
     * @param array  $headers Additional headers merged with defaults.
     */
    public function delete(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->send('DELETE', $url, $data ?: null, $headers);
    }

    /**
     * Returns a fake_client for tests that records all requests. #AI:fake
     *
     * Example:
     *   $http = Client::fake(['GET https://api.example.com/users' => ['status' => 200, 'body' => []]]);
     *   $resp = $http->get('https://api.example.com/users');
     *   $http->assertSent('GET', 'users');
     *
     * @param array $stubs Map of "METHOD URL" => response stub arrays or http_response objects.
     */
    public static function fake(array $stubs = []): \Skim\Http\FakeClient {
        return new \Skim\Http\FakeClient($stubs);
    }

    private function send(string $method, string $url, ?array $body, array $extraHeaders): \Skim\Http\HttpResponse {
        $fullUrl = $this->baseUrl !== null ? $this->baseUrl . '/' . ltrim($url, '/') : $url;
        $headers  = array_merge($this->defaultHeaders, $extraHeaders);
        $content  = $body !== null ? json_encode($body) : null;

        $headerLines = array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys($headers),
            array_values($headers),
        );

        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headerLines),
                'content'       => $content,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => $this->verifySsl,
                'verify_peer_name' => $this->verifySsl,
            ],
        ];

        $ctx  = stream_context_create($opts);
        $raw  = @file_get_contents($fullUrl, false, $ctx);
        $meta = $http_response_header ?? [];

        return \Skim\Http\HttpResponse::fromStream($raw === false ? '' : $raw, $meta);
    }
}

#AI:class
#AI symbol: Skim\Http\Client
#AI source_path: src/Http/Client.php
#AI title: client
#AI description: Zero-dependency HTTP client using PHP native streams with JSON body and test faking.
#AI role: HTTP client
#AI layer: http
#AI badges: [http; client; streams; zero-dep; testable]
#AI intro: `client` wraps PHP's native `file_get_contents` + `stream_context_create` for outbound HTTP. It returns immutable `HttpResponse` objects and never throws on non-2xx status codes.
#AI lifecycle: instantiated per-service or per-request; no persistent connections
#AI test_seam: Client::fake() returns a FakeClient that records requests and returns stubs
#AI invariants: [Never throws on non-2xx status; All responses are HttpResponse value objects; JSON Content-Type by default]
#AI core_behaviors: [Sends HTTP via PHP streams; JSON-encodes request bodies; Merges default and per-request headers; Supports baseUrl prefixing]
#AI owns: nothing
#AI entry_points: [get; post; put; patch; delete; fake]
#AI config_reads: []
#AI non_goals: [Does not support async/concurrent requests; Does not follow redirects; Does not retry on failure; No Guzzle dependency]
#AI side_effects: [Makes outbound HTTP requests]
#AI flow: Client::method() -> send() -> stream_context_create() -> file_get_contents() -> HttpResponse::fromStream()
#AI section_order: [HTTP Methods; Testing; Architecture]

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $url, array $query = [], array $headers = []): HttpResponse
#AI contract: Sends a GET request. The $query array is appended as URL query parameters.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL or path (prepended with baseUrl if set).}; {name: $query | type: array | required: false | desc: Query params appended as ?key=val URL string.}; {name: $headers | type: array | required: false | desc: Additional headers merged with defaults.}]
#AI return_detail: {type: HttpResponse | desc: Immutable response value object.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Sends a POST request with JSON-encoded body.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL or path.}; {name: $data | type: array | required: false | desc: Request body, JSON-encoded.}; {name: $headers | type: array | required: false | desc: Additional headers merged with defaults.}]
#AI return_detail: {type: HttpResponse | desc: Immutable response value object.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Sends a PUT request with JSON-encoded body.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL or path.}; {name: $data | type: array | required: false | desc: Request body, JSON-encoded.}; {name: $headers | type: array | required: false | desc: Additional headers merged with defaults.}]
#AI return_detail: {type: HttpResponse | desc: Immutable response value object.}

#AI:patch
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function patch(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Sends a PATCH request with JSON-encoded body.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL or path.}; {name: $data | type: array | required: false | desc: Request body, JSON-encoded.}; {name: $headers | type: array | required: false | desc: Additional headers merged with defaults.}]
#AI return_detail: {type: HttpResponse | desc: Immutable response value object.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Sends a DELETE request with optional JSON body. Body is omitted when $data is empty.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL or path.}; {name: $data | type: array | required: false | desc: Optional request body, JSON-encoded when non-empty.}; {name: $headers | type: array | required: false | desc: Additional headers merged with defaults.}]
#AI return_detail: {type: HttpResponse | desc: Immutable response value object.}

#AI:fake
#AI group: Testing
#AI frequency: high
#AI signature: public static function fake(array $stubs = []): FakeClient
#AI contract: Returns a FakeClient that intercepts all HTTP requests, records them, and returns stub responses.
#AI param_details: [{name: $stubs | type: array | required: false | desc: Map of 'METHOD URL' => response stub arrays or HttpResponse objects.}]
#AI return_detail: {type: FakeClient | desc: Test double that records requests and returns stubs.}
#AI examples: [{label: Basic stub | code: $http = Client::fake(['GET https://api.example.com/users' => ['status' => 200, 'body' => []]]);}]
