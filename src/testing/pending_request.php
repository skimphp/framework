<?php declare(strict_types=1);

namespace skim\testing;

use skim\auth\auth_service;
use skim\core\app;
use skim\session\session;

/**
 * Immutable request builder that dispatches through the app without a real server. #AI:class
 *
 * Use as the core dispatch mechanism in HTTP tests. Each configuration method
 * (acting_as, with_headers, etc.) returns a clone, so the original is never mutated.
 * Dispatches through app::dispatch() with optional middleware skipping.
 *
 * Example:
 *   $req = new pending_request($app);
 *   $req->acting_as($user)
 *       ->with_headers(['Accept' => 'application/json'])
 *       ->post('/api/posts', json: ['title' => 'Test'])
 *       ->assert_created();
 *
 * Testing: This class IS the test dispatch mechanism — use via http_client.
 *
 * #AI:class
 */
class pending_request {
    private ?object $user = null;
    private array $headers = [];
    private array $session = [];
    private array $cookies = [];
    private bool $follow_redirects = false;
    private bool $skip_middleware = false;

    public function __construct(private readonly app $app) {}

    /**
     * Returns a clone configured to authenticate as the given user. #AI:acting_as
     *
     * @param object $user User object bound to auth_service in the container.
     */
    public function acting_as(object $user): static {
        $clone = clone $this;
        $clone->user = $user;
        return $clone;
    }

    /**
     * Returns a clone with additional request headers. #AI:with_headers
     *
     * @param array $headers Key-value header pairs merged with existing headers.
     */
    public function with_headers(array $headers): static {
        $clone = clone $this;
        $clone->headers = array_merge($this->headers, $headers);
        return $clone;
    }

    /**
     * Returns a clone with pre-populated session data. #AI:with_session
     *
     * @param array $data Key-value session pairs bound via session_fake.
     */
    public function with_session(array $data): static {
        $clone = clone $this;
        $clone->session = array_merge($this->session, $data);
        return $clone;
    }

    /**
     * Returns a clone with the given cookies. #AI:with_cookies
     *
     * @param array $cookies Key-value cookie pairs.
     */
    public function with_cookies(array $cookies): static {
        $clone = clone $this;
        $clone->cookies = $cookies;
        return $clone;
    }

    /**
     * Returns a clone that follows redirects automatically. #AI:following_redirects
     */
    public function following_redirects(): static {
        $clone = clone $this;
        $clone->follow_redirects = true;
        return $clone;
    }

    /**
     * Returns a clone that skips all middleware during dispatch. #AI:without_middleware
     */
    public function without_middleware(): static {
        $clone = clone $this;
        $clone->skip_middleware = true;
        return $clone;
    }

    /**
     * Dispatches a GET request. #AI:get
     *
     * @param string $path  Request URI path.
     * @param array  $query Query string parameters.
     */
    public function get(string $path, array $query = []): http_response {
        return $this->send('GET', $path, query: $query);
    }

    /**
     * Dispatches a POST request. #AI:post
     *
     * @param string $path Request URI path.
     * @param array  $post Form-encoded POST data.
     * @param array  $json JSON body (sets Content-Type automatically).
     */
    public function post(string $path, array $post = [], array $json = []): http_response {
        return $this->send('POST', $path, post: $post, json: $json);
    }

    /**
     * Dispatches a PUT request. #AI:put
     *
     * @param string $path Request URI path.
     * @param array  $post Form-encoded body data.
     */
    public function put(string $path, array $post = []): http_response {
        return $this->send('PUT', $path, post: $post);
    }

    /**
     * Dispatches a DELETE request. #AI:delete
     *
     * @param string $path Request URI path.
     */
    public function delete(string $path): http_response {
        return $this->send('DELETE', $path);
    }

    private function send(string $method, string $path, array $query = [], array $post = [], array $json = []): http_response {
        $headers = $this->headers;
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        $req = request_factory::make(
            method: $method,
            path: $path,
            query: $query,
            post: $post,
            headers: $headers,
            raw_body: $json ? json_encode($json) : '',
            cookies: $this->cookies,
        );

        $app = clone $this->app;

        if ($this->user && class_exists(auth_service::class)) {
            $app->bind(auth_service::class, fn(): auth_fake => new auth_fake($this->user));
            $app->bind('auth', fn(): auth_fake => new auth_fake($this->user));
        }

        if ($this->session) {
            $session = new session_fake();
            foreach ($this->session as $k => $v) {
                $session->set($k, $v);
            }
            $app->bind(session_fake::class, fn(): session_fake => $session);
            $app->bind('session', fn(): session_fake => $session);
            if (class_exists(session::class)) {
                $app->bind(session::class, fn(): session_fake => $session);
            }
        }

        $response = new http_response($app->dispatch($req, new \skim\core\response(), skip_middleware: $this->skip_middleware));

        if ($this->follow_redirects && $response->is_redirect()) {
            return $this->get((string) $response->header('Location'));
        }

        return $response;
    }
}

#AI:class
#AI symbol: skim\testing\pending_request
#AI source_path: src/testing/pending_request.php
#AI title: pending_request
#AI description: Immutable request builder that dispatches through the app with auth, session, and header injection.
#AI role: test request builder
#AI layer: testing
#AI badges: [testing; http; immutable; builder]
#AI intro: `pending_request` is an immutable builder that configures and dispatches in-process HTTP requests. Each configuration method returns a clone, preventing state leakage between test assertions.
#AI lifecycle: created per-request by http_client, cloned on each configuration call, consumed on HTTP method call
#AI fallback: n/a — test-only class
#AI test_seam: use via http_client or instantiate directly with an app instance
#AI invariants: [Configuration methods return clones — original is never mutated; Auth and session are bound to a cloned app, not the original; Redirect following recurses via get()]
#AI core_behaviors: [Builds a request via request_factory; Clones the app and binds auth/session fakes; Dispatches through app::dispatch()]
#AI notes: When $json is non-empty on post(), Content-Type is automatically set to application/json.
#AI owns: configuration state (user, headers, session, cookies, flags)
#AI entry_points: [acting_as; with_headers; with_session; with_cookies; following_redirects; without_middleware; get; post; put; delete]
#AI config_reads: []
#AI non_goals: [Does not open real network connections; Does not test WebSocket or SSE endpoints]
#AI side_effects: [Clones app instance; Binds auth/session fakes into cloned container; Dispatches through router pipeline]
#AI flow: pending_request::config() -> clone -> HTTP method -> send() -> request_factory::make() -> app::dispatch() -> http_response
#AI lifecycle_steps: [http_client::method() -> new pending_request($app); -> acting_as/with_headers/etc() -> clone; -> get/post/etc() -> send(); -> request_factory::make(); -> clone app; -> bind fakes; -> app::dispatch(); -> http_response]
#AI section_order: [Configuration; HTTP Methods; Architecture]
#AI architectural_notes: Immutable builder pattern — each configuration call returns a clone, making it safe to reuse a base pending_request across multiple assertions.

#AI:acting_as
#AI group: Configuration
#AI frequency: high
#AI signature: public function acting_as(object $user): static
#AI contract: Returns a clone configured to authenticate as the given user via auth_fake.
#AI param_details: [{name: $user | type: object | required: true | desc: User object bound to auth_service in the cloned container.}]
#AI return_detail: {type: static | desc: New clone with user configured.}

#AI:with_headers
#AI group: Configuration
#AI frequency: medium
#AI signature: public function with_headers(array $headers): static
#AI contract: Returns a clone with additional request headers merged into existing ones.
#AI param_details: [{name: $headers | type: array | required: true | desc: Key-value header pairs.}]
#AI return_detail: {type: static | desc: New clone with headers merged.}

#AI:with_session
#AI group: Configuration
#AI frequency: medium
#AI signature: public function with_session(array $data): static
#AI contract: Returns a clone with pre-populated session data bound via session_fake.
#AI param_details: [{name: $data | type: array | required: true | desc: Key-value session pairs.}]
#AI return_detail: {type: static | desc: New clone with session data merged.}

#AI:with_cookies
#AI group: Configuration
#AI frequency: low
#AI signature: public function with_cookies(array $cookies): static
#AI contract: Returns a clone with the given cookies.
#AI param_details: [{name: $cookies | type: array | required: true | desc: Key-value cookie pairs.}]
#AI return_detail: {type: static | desc: New clone with cookies set.}

#AI:following_redirects
#AI group: Configuration
#AI frequency: low
#AI signature: public function following_redirects(): static
#AI contract: Returns a clone that automatically follows 3xx redirects via recursive get().
#AI return_detail: {type: static | desc: New clone with redirect following enabled.}

#AI:without_middleware
#AI group: Configuration
#AI frequency: low
#AI signature: public function without_middleware(): static
#AI contract: Returns a clone that skips all middleware during dispatch.
#AI return_detail: {type: static | desc: New clone with middleware skipping enabled.}

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $path, array $query = []): http_response
#AI contract: Dispatches a GET request through the app.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $query | type: array | required: false | desc: Query string parameters.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $path, array $post = [], array $json = []): http_response
#AI contract: Dispatches a POST request. Sets Content-Type to application/json when $json is non-empty.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded POST data.}; {name: $json | type: array | required: false | desc: JSON body data.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $path, array $post = []): http_response
#AI contract: Dispatches a PUT request with form-encoded body data.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded body data.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $path): http_response
#AI contract: Dispatches a DELETE request.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}]
#AI return_detail: {type: http_response | desc: Test response with assertion methods.}
