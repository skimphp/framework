<?php declare(strict_types=1);

namespace Skim\Testing;

use Skim\Auth\AuthService;
use Skim\Core\App;
use Skim\Session\Session;

/**
 * Immutable request builder that dispatches through the app without a real server. #AI:class
 *
 * Use as the core dispatch mechanism in HTTP tests. Each configuration method
 * (actingAs, withHeaders, etc.) returns a clone, so the original is never mutated.
 * Dispatches through App::dispatch() with optional middleware skipping.
 *
 * Example:
 *   $req = new PendingRequest($app);
 *   $req->actingAs($user)
 *       ->withHeaders(['Accept' => 'application/json'])
 *       ->post('/api/posts', json: ['title' => 'Test'])
 *       ->assertCreated();
 *
 * Testing: This class IS the test dispatch mechanism — use via HttpClient.
 *
 * #AI:class
 */
class PendingRequest {
    private ?object $user = null;
    private array $headers = [];
    private array $session = [];
    private array $cookies = [];
    private bool $followRedirects = false;
    private bool $skipMiddleware = false;

    public function __construct(private readonly \Skim\Core\App $app) {}

    /**
     * Returns a clone configured to authenticate as the given user. #AI:actingAs
     *
     * @param object $user User object bound to AuthService in the container.
     */
    public function actingAs(object $user): static {
        $clone = clone $this;
        $clone->user = $user;
        return $clone;
    }

    /**
     * Returns a clone with additional request headers. #AI:withHeaders
     *
     * @param array $headers Key-value header pairs merged with existing headers.
     */
    public function withHeaders(array $headers): static {
        $clone = clone $this;
        $clone->headers = array_merge($this->headers, $headers);
        return $clone;
    }

    /**
     * Returns a clone with pre-populated session data. #AI:withSession
     *
     * @param array $data Key-value session pairs bound via SessionFake.
     */
    public function withSession(array $data): static {
        $clone = clone $this;
        $clone->session = array_merge($this->session, $data);
        return $clone;
    }

    /**
     * Returns a clone with the given cookies. #AI:withCookies
     *
     * @param array $cookies Key-value cookie pairs.
     */
    public function withCookies(array $cookies): static {
        $clone = clone $this;
        $clone->cookies = $cookies;
        return $clone;
    }

    /**
     * Returns a clone that follows redirects automatically. #AI:followingRedirects
     */
    public function followingRedirects(): static {
        $clone = clone $this;
        $clone->followRedirects = true;
        return $clone;
    }

    /**
     * Returns a clone that skips all middleware during dispatch. #AI:withoutMiddleware
     */
    public function withoutMiddleware(): static {
        $clone = clone $this;
        $clone->skipMiddleware = true;
        return $clone;
    }

    /**
     * Dispatches a GET request. #AI:get
     *
     * @param string $path  Request URI path.
     * @param array  $query Query string parameters.
     */
    public function get(string $path, array $query = []): \Skim\Testing\HttpResponse {
        return $this->send('GET', $path, query: $query);
    }

    /**
     * Dispatches a POST request. #AI:post
     *
     * @param string $path Request URI path.
     * @param array  $post Form-encoded POST data.
     * @param array  $json JSON body (sets Content-Type automatically).
     */
    public function post(string $path, array $post = [], array $json = []): \Skim\Testing\HttpResponse {
        return $this->send('POST', $path, post: $post, json: $json);
    }

    /**
     * Dispatches a PUT request. #AI:put
     *
     * @param string $path Request URI path.
     * @param array  $post Form-encoded body data.
     */
    public function put(string $path, array $post = []): \Skim\Testing\HttpResponse {
        return $this->send('PUT', $path, post: $post);
    }

    /**
     * Dispatches a DELETE request. #AI:delete
     *
     * @param string $path Request URI path.
     */
    public function delete(string $path): \Skim\Testing\HttpResponse {
        return $this->send('DELETE', $path);
    }

    private function send(string $method, string $path, array $query = [], array $post = [], array $json = []): \Skim\Testing\HttpResponse {
        $headers = $this->headers;
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        $req = \Skim\Testing\RequestFactory::make(
            method: $method,
            path: $path,
            query: $query,
            post: $post,
            headers: $headers,
            rawBody: $json ? json_encode($json) : '',
            cookies: $this->cookies,
        );

        $app = clone $this->app;

        if ($this->user && class_exists(AuthService::class)) {
            $app->bind(AuthService::class, fn(): \Skim\Testing\AuthFake => new \Skim\Testing\AuthFake($this->user));
            $app->bind('auth', fn(): \Skim\Testing\AuthFake => new \Skim\Testing\AuthFake($this->user));
        }

        if ($this->session) {
            $session = new \Skim\Testing\SessionFake();
            foreach ($this->session as $k => $v) {
                $session->set($k, $v);
            }
            $app->bind(\Skim\Testing\SessionFake::class, fn(): \Skim\Testing\SessionFake => $session);
            $app->bind('session', fn(): \Skim\Testing\SessionFake => $session);
            if (class_exists(\Skim\Session\Session::class)) {
                $app->bind(\Skim\Session\Session::class, fn(): \Skim\Testing\SessionFake => $session);
            }
        }

        $response = new \Skim\Testing\HttpResponse($app->dispatch($req, new \Skim\Core\Response(), skipMiddleware: $this->skipMiddleware));

        if ($this->followRedirects && $response->isRedirect()) {
            return $this->get((string) $response->header('Location'));
        }

        return $response;
    }
}

#AI:class
#AI symbol: Skim\Testing\PendingRequest
#AI source_path: src/Testing/PendingRequest.php
#AI title: PendingRequest
#AI description: Immutable request builder that dispatches through the app with auth, session, and header injection.
#AI role: test request builder
#AI layer: testing
#AI badges: [testing; http; immutable; builder]
#AI intro: `PendingRequest` is an immutable builder that configures and dispatches in-process HTTP requests. Each configuration method returns a clone, preventing state leakage between test assertions.
#AI lifecycle: created per-request by HttpClient, cloned on each configuration call, consumed on HTTP method call
#AI fallback: n/a — test-only class
#AI test_seam: use via HttpClient or instantiate directly with an app instance
#AI invariants: [Configuration methods return clones — original is never mutated; Auth and session are bound to a cloned app, not the original; Redirect following recurses via get()]
#AI core_behaviors: [Builds a request via RequestFactory; Clones the app and binds auth/session fakes; Dispatches through App::dispatch()]
#AI notes: When $json is non-empty on post(), Content-Type is automatically set to application/json.
#AI owns: configuration state (user, headers, session, cookies, flags)
#AI entry_points: [actingAs; withHeaders; withSession; withCookies; followingRedirects; withoutMiddleware; get; post; put; delete]
#AI config_reads: []
#AI non_goals: [Does not open real network connections; Does not test WebSocket or SSE endpoints]
#AI side_effects: [Clones app instance; Binds auth/session fakes into cloned container; Dispatches through router pipeline]
#AI flow: PendingRequest::config() -> clone -> HTTP method -> send() -> RequestFactory::make() -> App::dispatch() -> HttpResponse
#AI lifecycle_steps: [HttpClient::method() -> new PendingRequest($app); -> actingAs/withHeaders/etc() -> clone; -> get/post/etc() -> send(); -> RequestFactory::make(); -> clone app; -> bind fakes; -> App::dispatch(); -> HttpResponse]
#AI section_order: [Configuration; HTTP Methods; Architecture]
#AI architectural_notes: Immutable builder pattern — each configuration call returns a clone, making it safe to reuse a base PendingRequest across multiple assertions.

#AI:actingAs
#AI group: Configuration
#AI frequency: high
#AI signature: public function actingAs(object $user): static
#AI contract: Returns a clone configured to authenticate as the given user via AuthFake.
#AI param_details: [{name: $user | type: object | required: true | desc: User object bound to AuthService in the cloned container.}]
#AI return_detail: {type: static | desc: New clone with user configured.}

#AI:withHeaders
#AI group: Configuration
#AI frequency: medium
#AI signature: public function withHeaders(array $headers): static
#AI contract: Returns a clone with additional request headers merged into existing ones.
#AI param_details: [{name: $headers | type: array | required: true | desc: Key-value header pairs.}]
#AI return_detail: {type: static | desc: New clone with headers merged.}

#AI:withSession
#AI group: Configuration
#AI frequency: medium
#AI signature: public function withSession(array $data): static
#AI contract: Returns a clone with pre-populated session data bound via SessionFake.
#AI param_details: [{name: $data | type: array | required: true | desc: Key-value session pairs.}]
#AI return_detail: {type: static | desc: New clone with session data merged.}

#AI:withCookies
#AI group: Configuration
#AI frequency: low
#AI signature: public function withCookies(array $cookies): static
#AI contract: Returns a clone with the given cookies.
#AI param_details: [{name: $cookies | type: array | required: true | desc: Key-value cookie pairs.}]
#AI return_detail: {type: static | desc: New clone with cookies set.}

#AI:followingRedirects
#AI group: Configuration
#AI frequency: low
#AI signature: public function followingRedirects(): static
#AI contract: Returns a clone that automatically follows 3xx redirects via recursive get().
#AI return_detail: {type: static | desc: New clone with redirect following enabled.}

#AI:withoutMiddleware
#AI group: Configuration
#AI frequency: low
#AI signature: public function withoutMiddleware(): static
#AI contract: Returns a clone that skips all middleware during dispatch.
#AI return_detail: {type: static | desc: New clone with middleware skipping enabled.}

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $path, array $query = []): HttpResponse
#AI contract: Dispatches a GET request through the app.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $query | type: array | required: false | desc: Query string parameters.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $path, array $post = [], array $json = []): HttpResponse
#AI contract: Dispatches a POST request. Sets Content-Type to application/json when $json is non-empty.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded POST data.}; {name: $json | type: array | required: false | desc: JSON body data.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $path, array $post = []): HttpResponse
#AI contract: Dispatches a PUT request with form-encoded body data.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}; {name: $post | type: array | required: false | desc: Form-encoded body data.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $path): HttpResponse
#AI contract: Dispatches a DELETE request.
#AI param_details: [{name: $path | type: string | required: true | desc: Request URI path.}]
#AI return_detail: {type: HttpResponse | desc: Test response with assertion methods.}
