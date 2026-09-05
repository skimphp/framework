<?php declare(strict_types=1);

namespace Skim\Http;

/**
 * Test double for client — intercepts requests and returns stub responses.
 *
 * Use in tests to avoid real HTTP calls. Records all requests for
 * assertion. Stubs are matched by "METHOD URL" key, URL-only key, or
 * method-only key, in that priority order.
 *
 * Example:
 *   $http = Client::fake([
 *       'GET https://api.example.com/users' => ['status' => 200, 'body' => [['id' => 1]]],
 *   ]);
 *   $resp = $http->get('https://api.example.com/users');
 *   $http->assertSent('GET', 'users');
 *
 * Testing: Create via Client::fake() — no external dependencies needed.
 *
 * #AI:class
 */
final class FakeClient extends \Skim\Http\Client {
    /** @var list<array{method:string,url:string,body:mixed}> */
    private array $recorded = [];
    private array $stubs;

    public function __construct(array $stubs = []) {
        $this->stubs = $stubs;
    }

    /**
     * Records a GET request and returns the matching stub. #AI:get
     *
     * @param string $url     Target URL.
     * @param array  $query   Query params (ignored in fake — not appended to URL).
     * @param array  $headers Request headers (recorded but not sent).
     */
    public function get(string $url, array $query = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->fakeSend('GET', $url, null);
    }

    /**
     * Records a POST request and returns the matching stub. #AI:post
     *
     * @param string $url     Target URL.
     * @param array  $data    Request body data (recorded).
     * @param array  $headers Request headers (recorded but not sent).
     */
    public function post(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->fakeSend('POST', $url, $data);
    }

    /**
     * Records a PUT request and returns the matching stub. #AI:put
     *
     * @param string $url     Target URL.
     * @param array  $data    Request body data (recorded).
     * @param array  $headers Request headers (recorded but not sent).
     */
    public function put(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->fakeSend('PUT', $url, $data);
    }

    /**
     * Records a PATCH request and returns the matching stub. #AI:patch
     *
     * @param string $url     Target URL.
     * @param array  $data    Request body data (recorded).
     * @param array  $headers Request headers (recorded but not sent).
     */
    public function patch(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->fakeSend('PATCH', $url, $data);
    }

    /**
     * Records a DELETE request and returns the matching stub. #AI:delete
     *
     * @param string $url     Target URL.
     * @param array  $data    Request body data (recorded).
     * @param array  $headers Request headers (recorded but not sent).
     */
    public function delete(string $url, array $data = [], array $headers = []): \Skim\Http\HttpResponse {
        return $this->fakeSend('DELETE', $url, $data);
    }

    /**
     * Asserts that a request matching method+url substring was made. #AI:assertSent
     *
     * Throws RuntimeException when no recorded request matches. The URL
     * match uses str_contains, so partial URLs work.
     *
     * Example:
     *   $http->assertSent('GET', 'users'); // matches any URL containing 'users'
     *
     * @param string $method HTTP method (case-insensitive).
     * @param string $url    URL substring to match against recorded URLs.
     * @throws \RuntimeException When no matching request was recorded.
     */
    public function assertSent(string $method, string $url): void {
        $found = array_find(
            $this->recorded,
            fn($r) => $r['method'] === strtoupper($method) && str_contains($r['url'], $url),
        );
        if ($found === null) {
            throw new \RuntimeException("Expected {$method} {$url} to have been sent, but it was not.");
        }
    }

    /**
     * Asserts that no HTTP requests were recorded. #AI:assertNothingSent
     *
     * @throws \RuntimeException When any requests were recorded.
     */
    public function assertNothingSent(): void {
        if ($this->recorded !== []) {
            throw new \RuntimeException('Expected no HTTP requests, but ' . count($this->recorded) . ' were sent.');
        }
    }

    /**
     * Returns all recorded requests for custom assertions. #AI:recorded
     *
     * Each entry contains method, url, and body keys.
     *
     * @return list<array{method:string,url:string,body:mixed}>
     */
    public function recorded(): array {
        return $this->recorded;
    }

    private function fakeSend(string $method, string $url, mixed $body): \Skim\Http\HttpResponse {
        $this->recorded[] = ['method' => $method, 'url' => $url, 'body' => $body];

        $key = "{$method} {$url}";
        $stub = $this->stubs[$key]
            ?? $this->stubs[$url]
            ?? $this->stubs[$method]
            ?? null;

        if ($stub === null) {
            return new \Skim\Http\HttpResponse(200, '{}', ['Content-Type' => 'application/json']);
        }

        if ($stub instanceof \Skim\Http\HttpResponse) {
            return $stub;
        }

        $bodyContent = isset($stub['body']) && is_array($stub['body'])
            ? (string) json_encode($stub['body'])
            : (string) ($stub['body'] ?? '');

        return new \Skim\Http\HttpResponse(
            $stub['status'] ?? 200,
            $bodyContent,
            $stub['headers'] ?? ['Content-Type' => 'application/json'],
        );
    }
}

#AI:class
#AI symbol: Skim\Http\FakeClient
#AI source_path: src/Http/FakeClient.php
#AI title: FakeClient
#AI description: Test double for HTTP client that records requests and returns stub responses.
#AI role: HTTP test fake
#AI layer: http
#AI badges: [testing; http; fake; stub]
#AI intro: `FakeClient` extends `client` to intercept all HTTP requests in tests. It records every request for later assertion and returns configurable stub responses without making real network calls.
#AI lifecycle: created via Client::fake(); lives for the test case duration
#AI test_seam: Client::fake($stubs) factory; assertSent/assertNothingSent for verification
#AI invariants: [No real HTTP calls are made; Unstubbed requests return 200 with empty JSON body; Stub matching priority: METHOD+URL > URL > METHOD]
#AI core_behaviors: [Records all requests with method, URL, and body; Matches stubs by key priority; Provides assertion methods for test verification]
#AI owns: recorded request log
#AI entry_points: [get; post; put; patch; delete; assertSent; assertNothingSent; recorded]
#AI config_reads: []
#AI non_goals: [Does not simulate network failures; Does not validate request format; Does not follow redirects]
#AI side_effects: [Records requests in memory]
#AI flow: Client::fake($stubs) -> FakeClient -> method() -> fakeSend() -> record + match stub -> HttpResponse
#AI section_order: [HTTP Methods; Assertions; Inspection]

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $url, array $query = [], array $headers = []): HttpResponse
#AI contract: Records a GET request and returns the matching stub response. Query params are not appended to the URL in the fake.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL.}; {name: $query | type: array | required: false | desc: Query params (ignored in fake).}; {name: $headers | type: array | required: false | desc: Request headers (recorded but not sent).}]
#AI return_detail: {type: HttpResponse | desc: Stub response or default 200 empty JSON.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Records a POST request with body data and returns the matching stub response.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL.}; {name: $data | type: array | required: false | desc: Request body data (recorded).}; {name: $headers | type: array | required: false | desc: Request headers (recorded but not sent).}]
#AI return_detail: {type: HttpResponse | desc: Stub response or default 200 empty JSON.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Records a PUT request with body data and returns the matching stub response.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL.}; {name: $data | type: array | required: false | desc: Request body data (recorded).}; {name: $headers | type: array | required: false | desc: Request headers (recorded but not sent).}]
#AI return_detail: {type: HttpResponse | desc: Stub response or default 200 empty JSON.}

#AI:patch
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function patch(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Records a PATCH request with body data and returns the matching stub response.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL.}; {name: $data | type: array | required: false | desc: Request body data (recorded).}; {name: $headers | type: array | required: false | desc: Request headers (recorded but not sent).}]
#AI return_detail: {type: HttpResponse | desc: Stub response or default 200 empty JSON.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $url, array $data = [], array $headers = []): HttpResponse
#AI contract: Records a DELETE request with optional body data and returns the matching stub response.
#AI param_details: [{name: $url | type: string | required: true | desc: Target URL.}; {name: $data | type: array | required: false | desc: Request body data (recorded).}; {name: $headers | type: array | required: false | desc: Request headers (recorded but not sent).}]
#AI return_detail: {type: HttpResponse | desc: Stub response or default 200 empty JSON.}

#AI:assertSent
#AI group: Assertions
#AI frequency: high
#AI signature: public function assertSent(string $method, string $url): void
#AI contract: Throws RuntimeException when no recorded request matches the given method and URL substring.
#AI param_details: [{name: $method | type: string | required: true | desc: HTTP method (case-insensitive).}; {name: $url | type: string | required: true | desc: URL substring to match via str_contains.}]
#AI throws_details: [{type: \RuntimeException | desc: When no matching request was recorded.}]

#AI:assertNothingSent
#AI group: Assertions
#AI frequency: medium
#AI signature: public function assertNothingSent(): void
#AI contract: Throws RuntimeException when any HTTP requests were recorded.
#AI throws_details: [{type: \RuntimeException | desc: When any requests were recorded.}]

#AI:recorded
#AI group: Inspection
#AI frequency: low
#AI signature: public function recorded(): array
#AI contract: Returns all recorded requests for custom assertions. Each entry has method, url, and body keys.
#AI return_detail: {type: list<array{method:string,url:string,body:mixed}> | desc: All recorded requests.}
