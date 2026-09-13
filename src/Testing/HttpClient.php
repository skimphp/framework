<?php declare(strict_types=1);

namespace Skim\Testing;

use Skim\Core\App;

/**
 * Test HTTP client that dispatches requests through the app without a real server. #AI:class
 *
 * Use in Pest/PHPUnit to test routes, controllers, and middleware in-process.
 * Each method creates a fresh PendingRequest, so configuration (actingAs,
 * headers, session) does not leak between calls.
 *
 * Example:
 *   $client = new HttpClient($app);
 *   $client->actingAs($user)->post('/api/posts', json: ['title' => 'Hello']);
 *   $client->get('/api/posts')->assertOk()->assertJson([...]);
 *
 * Testing: This class IS the test utility — instantiate directly in test cases.
 *
 * #AI:class
 */
class HttpClient {
    public function __construct(private readonly \Skim\Core\App $app) {}

    /**
     * Creates a pending request authenticated as the given user. #AI:actingAs
     *
     * @param object $user User object injected into the auth service.
     */
    public function actingAs(object $user): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->actingAs($user);
    }

    /**
     * Creates a pending request with custom headers. #AI:withHeaders
     *
     * @param array $headers Key-value header pairs (e.g., ['Accept' => 'application/json']).
     */
    public function withHeaders(array $headers): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->withHeaders($headers);
    }

    /**
     * Creates a pending request with pre-populated session data. #AI:withSession
     *
     * @param array $data Key-value session pairs available during the request.
     */
    public function withSession(array $data): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->withSession($data);
    }

    /**
     * Creates a pending request that follows redirects automatically. #AI:followingRedirects
     */
    public function followingRedirects(): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->followingRedirects();
    }

    /**
     * Creates a pending request that skips all middleware. #AI:withoutMiddleware
     */
    public function withoutMiddleware(): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->withoutMiddleware();
    }

    /**
     * Sends a GET request. #AI:get
     *
     * @param string $path  Request URI path.
     * @param array  $query Query string parameters.
     */
    public function get(string $path, array $query = []): \Skim\Testing\HttpResponse {
        return (new \Skim\Testing\PendingRequest($this->app))->get($path, $query);
    }

    /**
     * Sends a POST request. #AI:post
     *
     * @param string $path Request URI path.
     * @param array  $post Form-encoded POST data.
     * @param array  $json JSON body data (sets Content-Type automatically).
     */
    public function post(string $path, array $post = [], array $json = []): \Skim\Testing\HttpResponse {
        return (new \Skim\Testing\PendingRequest($this->app))->post($path, $post, $json);
    }

    /**
     * Sends a PUT request. #AI:put
     *
     * @param string $path Request URI path.
     * @param array  $post Form-encoded body data.
     */
    public function put(string $path, array $post = []): \Skim\Testing\HttpResponse {
        return (new \Skim\Testing\PendingRequest($this->app))->put($path, $post);
    }

    /**
     * Sends a DELETE request. #AI:delete
     *
     * @param string $path Request URI path.
     */
    public function delete(string $path): \Skim\Testing\HttpResponse {
        return (new \Skim\Testing\PendingRequest($this->app))->delete($path);
    }
}

#AI:class
#AI symbol: Skim\Testing\HttpClient
#AI source_path: src/Testing/HttpClient.php
#AI title: HttpClient
#AI description: In-process HTTP test client that dispatches requests through the app without a real server.
#AI role: test HTTP client
#AI layer: testing
#AI badges: [testing; http; client; in-process]
#AI intro: `HttpClient` provides a fluent API for testing HTTP endpoints in-process. Each method creates a fresh `PendingRequest`, so configuration does not leak between calls.
#AI lifecycle: instantiated per-test, wraps an app instance
#AI fallback: n/a — test-only class
#AI test_seam: instantiate directly in test cases with the app under test
#AI invariants: [Each method creates a fresh PendingRequest; Configuration does not leak between calls]
#AI core_behaviors: [Delegates all configuration and dispatch to PendingRequest; Provides a convenience layer for common test patterns]
#AI notes: Use actingAs() for authenticated routes, withSession() for session-dependent routes, withoutMiddleware() to isolate controller logic.
#AI owns: app instance reference
#AI entry_points: [actingAs; withHeaders; withSession; followingRedirects; withoutMiddleware; get; post; put; delete]
#AI config_reads: []
#AI non_goals: [Does not open real network connections; Does not test CORS or real HTTP headers]
#AI side_effects: [Dispatches through App::dispatch() which runs the full router pipeline]
#AI flow: test -> HttpClient::method() -> PendingRequest -> App::dispatch() -> HttpResponse
#AI lifecycle_steps: [new HttpClient($app); -> HttpClient::get/post/etc(); -> new PendingRequest($app); -> PendingRequest::send(); -> App::dispatch(); -> HttpResponse]
#AI section_order: [Configuration; HTTP Methods; Architecture]
#AI architectural_notes: Thin convenience layer over PendingRequest — each call creates a fresh instance to prevent state leakage.

#AI:actingAs
#AI group: Configuration
#AI frequency: high
#AI signature: public function actingAs(object $user): PendingRequest
#AI contract: Creates a pending request authenticated as the given user.
#AI param_details: [{name: $user | type: object | required: true | desc: User object injected into the auth service.}]
#AI return_detail: {type: PendingRequest | desc: Configured pending request ready for HTTP method calls.}

#AI:withHeaders
#AI group: Configuration
#AI frequency: medium
#AI signature: public function withHeaders(array $headers): PendingRequest
#AI contract: Creates a pending request with custom headers.
#AI param_details: [{name: $headers | type: array | required: true | desc: Key-value header pairs.}]
#AI return_detail: {type: PendingRequest | desc: Configured pending request.}

#AI:withSession
#AI group: Configuration
#AI frequency: medium
#AI signature: public function withSession(array $data): PendingRequest
#AI contract: Creates a pending request with pre-populated session data.
#AI param_details: [{name: $data | type: array | required: true | desc: Key-value session pairs.}]
#AI return_detail: {type: PendingRequest | desc: Configured pending request.}

#AI:followingRedirects
#AI group: Configuration
#AI frequency: low
#AI signature: public function followingRedirects(): PendingRequest
#AI contract: Creates a pending request that automatically follows 3xx redirects.
#AI return_detail: {type: PendingRequest | desc: Configured pending request.}

#AI:withoutMiddleware
#AI group: Configuration
#AI frequency: low
#AI signature: public function withoutMiddleware(): PendingRequest
#AI contract: Creates a pending request that skips all middleware during dispatch.
#AI return_detail: {type: PendingRequest | desc: Configured pending request.}

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $path, array $query = []): HttpResponse
#AI contract: Sends a GET request through the app.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $query | type: array | required: false | desc: Query string parameters.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $path, array $post = [], array $json = []): HttpResponse
#AI contract: Sends a POST request. When $json is non-empty, sets Content-Type to application/json.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded POST data.}; {name: $json | type: array | required: false | desc: JSON body data.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $path, array $post = []): HttpResponse
#AI contract: Sends a PUT request with form-encoded body data.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded body data.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $path): HttpResponse
#AI contract: Sends a DELETE request.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}
