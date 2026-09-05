<?php declare(strict_types=1);

namespace Skim\Testing;

use Skim\Core\App;

/**
 * Test HTTP client that dispatches requests through the app without a real server. #AI:class
 *
 * Use in Pest/PHPUnit to test routes, controllers, and middleware in-process.
 * Each method creates a fresh pending_request, so configuration (acting_as,
 * headers, session) does not leak between calls.
 *
 * Example:
 *   $client = new http_client($app);
 *   $client->acting_as($user)->post('/api/posts', json: ['title' => 'Hello']);
 *   $client->get('/api/posts')->assert_ok()->assert_json([...]);
 *
 * Testing: This class IS the test utility — instantiate directly in test cases.
 *
 * #AI:class
 */
class HttpClient {
    public function __construct(private readonly \Skim\Core\App $app) {}

    /**
     * Creates a pending request authenticated as the given user. #AI:acting_as
     *
     * @param object $user User object injected into the auth service.
     */
    public function acting_as(object $user): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->acting_as($user);
    }

    /**
     * Creates a pending request with custom headers. #AI:with_headers
     *
     * @param array $headers Key-value header pairs (e.g., ['Accept' => 'application/json']).
     */
    public function with_headers(array $headers): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->with_headers($headers);
    }

    /**
     * Creates a pending request with pre-populated session data. #AI:with_session
     *
     * @param array $data Key-value session pairs available during the request.
     */
    public function with_session(array $data): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->with_session($data);
    }

    /**
     * Creates a pending request that follows redirects automatically. #AI:following_redirects
     */
    public function following_redirects(): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->following_redirects();
    }

    /**
     * Creates a pending request that skips all middleware. #AI:without_middleware
     */
    public function without_middleware(): \Skim\Testing\PendingRequest {
        return (new \Skim\Testing\PendingRequest($this->app))->without_middleware();
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
#AI source_path: src/testing/http_client.php
#AI title: http_client
#AI description: In-process HTTP test client that dispatches requests through the app without a real server.
#AI role: test HTTP client
#AI layer: testing
#AI badges: [testing; http; client; in-process]
#AI intro: `http_client` provides a fluent API for testing HTTP endpoints in-process. Each method creates a fresh `pending_request`, so configuration does not leak between calls.
#AI lifecycle: instantiated per-test, wraps an app instance
#AI fallback: n/a — test-only class
#AI test_seam: instantiate directly in test cases with the app under test
#AI invariants: [Each method creates a fresh pending_request; Configuration does not leak between calls]
#AI core_behaviors: [Delegates all configuration and dispatch to pending_request; Provides a convenience layer for common test patterns]
#AI notes: Use acting_as() for authenticated routes, with_session() for session-dependent routes, without_middleware() to isolate controller logic.
#AI owns: app instance reference
#AI entry_points: [acting_as; with_headers; with_session; following_redirects; without_middleware; get; post; put; delete]
#AI config_reads: []
#AI non_goals: [Does not open real network connections; Does not test CORS or real HTTP headers]
#AI side_effects: [Dispatches through app::dispatch() which runs the full router pipeline]
#AI flow: test -> http_client::method() -> pending_request -> app::dispatch() -> http_response
#AI lifecycle_steps: [new http_client($app); -> http_client::get/post/etc(); -> new pending_request($app); -> pending_request::send(); -> app::dispatch(); -> http_response]
#AI section_order: [Configuration; HTTP Methods; Architecture]
#AI architectural_notes: Thin convenience layer over pending_request — each call creates a fresh instance to prevent state leakage.

#AI:acting_as
#AI group: Configuration
#AI frequency: high
#AI signature: public function acting_as(object $user): pending_request
#AI contract: Creates a pending request authenticated as the given user.
#AI param_details: [{name: $user | type: object | required: true | desc: User object injected into the auth service.}]
#AI return_detail: {type: pending_request | desc: Configured pending request ready for HTTP method calls.}

#AI:with_headers
#AI group: Configuration
#AI frequency: medium
#AI signature: public function with_headers(array $headers): pending_request
#AI contract: Creates a pending request with custom headers.
#AI param_details: [{name: $headers | type: array | required: true | desc: Key-value header pairs.}]
#AI return_detail: {type: pending_request | desc: Configured pending request.}

#AI:with_session
#AI group: Configuration
#AI frequency: medium
#AI signature: public function with_session(array $data): pending_request
#AI contract: Creates a pending request with pre-populated session data.
#AI param_details: [{name: $data | type: array | required: true | desc: Key-value session pairs.}]
#AI return_detail: {type: pending_request | desc: Configured pending request.}

#AI:following_redirects
#AI group: Configuration
#AI frequency: low
#AI signature: public function following_redirects(): pending_request
#AI contract: Creates a pending request that automatically follows 3xx redirects.
#AI return_detail: {type: pending_request | desc: Configured pending request.}

#AI:without_middleware
#AI group: Configuration
#AI frequency: low
#AI signature: public function without_middleware(): pending_request
#AI contract: Creates a pending request that skips all middleware during dispatch.
#AI return_detail: {type: pending_request | desc: Configured pending request.}

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $path, array $query = []): http_response
#AI contract: Sends a GET request through the app.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $query | type: array | required: false | desc: Query string parameters.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $path, array $post = [], array $json = []): http_response
#AI contract: Sends a POST request. When $json is non-empty, sets Content-Type to application/json.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded POST data.}; {name: $json | type: array | required: false | desc: JSON body data.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $path, array $post = []): http_response
#AI contract: Sends a PUT request with form-encoded body data.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded body data.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $path): http_response
#AI contract: Sends a DELETE request.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}
