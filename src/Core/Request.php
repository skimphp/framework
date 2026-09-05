<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * HTTP request abstraction — injected by the container, never instantiated manually.
 *
 * Use in controllers and middleware to read query params, POST data, headers,
 * JSON bodies, uploaded files, and route segments. The same instance is shared
 * across the middleware pipeline and controller. Wraps $_GET, $_POST, $_SERVER,
 * $_FILES, and php://input — never access superglobals directly in app code.
 *
 * Example:
 *   public function store(request $req, response $res): mixed {
 *       $email = $req->post('email');
 *       $data  = $req->json();
 *       return $res->json(['ip' => $req->ip()]);
 *   }
 *
 * Testing: Use request::make() to fabricate requests without superglobals.
 *
 * #AI:class
 */
class Request {
    private array $route_params = [];
    private ?array $json_body   = null;

    public function __construct(
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files,
        private readonly string $raw_body,
    ) {}

    /**
     * Creates a request from PHP superglobals. #AI:from_globals
     *
     * Called once by app::run() at the start of the request. Reads superglobals
     * at call time; mutations to $_GET after this point are not reflected.
     *
     * Example:
     *   // Called internally by app::run()
     *   $req = request::from_globals();
     */
    public static function from_globals(): static {
        return new static(
            query:    $_GET    ?? [],
            post:     $_POST   ?? [],
            server:   $_SERVER ?? [],
            cookies:  $_COOKIE ?? [],
            files:    $_FILES  ?? [],
            raw_body: (string) file_get_contents('php://input'),
        );
    }

    /**
     * Fabricates a request from explicit arrays (testing only). #AI:make
     *
     * Delegates to request_factory for building requests without superglobals.
     * Use in Pest/PHPUnit tests to simulate HTTP input.
     *
     * Example:
     *   $req = request::make('POST', '/users', headers: ['Content-Type' => 'application/json'], raw_body: '{"name":"John"}');
     *
     * @param mixed ...$args Arguments forwarded to request_factory::make().
     */
    public static function make(...$args): static {
        return \Skim\Testing\RequestFactory::make(...$args);
    }

    /**
     * Returns a query string value. #AI:get
     *
     * Reads $_GET only — does not fall through to POST body or JSON.
     *
     * @param string $key     Query parameter name.
     * @param mixed  $default Returned when the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->query[$key] ?? $default;
    }

    /**
     * Returns a POST body value. #AI:post
     *
     * Reads $_POST only — does not include JSON body or query string.
     *
     * @param string $key     POST field name.
     * @param mixed  $default Returned when the key is absent.
     */
    public function post(string $key, mixed $default = null): mixed {
        return $this->post[$key] ?? $default;
    }

    /**
     * Returns the first match from GET then POST. #AI:input
     *
     * Use for form fields that may arrive via either method. Avoid for JSON APIs
     * — use json() instead.
     *
     * @param string $key     Field name to search.
     * @param mixed  $default Returned when absent from both sources.
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->query[$key] ?? $this->post[$key] ?? $default;
    }

    /**
     * Parses and caches the JSON request body. #AI:json
     *
     * Parses on first call, caches the result for subsequent calls. Returns an
     * empty array when Content-Type is not application/json or the body is not
     * valid JSON — never throws on malformed input.
     *
     * Example:
     *   $data = $req->json(); // ['name' => 'John', 'age' => 30]
     */
    public function json(): array {
        if ($this->json_body !== null) {
            return $this->json_body;
        }
        $ct = $this->header('Content-Type') ?? '';
        if (!str_contains($ct, 'application/json')) {
            return $this->json_body = [];
        }
        $decoded = json_decode($this->raw_body, true);
        return $this->json_body = is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns an uploaded file array. #AI:file
     *
     * Returns null when no file was uploaded, including when the field exists
     * but the upload is empty (UPLOAD_ERR_NO_FILE).
     *
     * @param string $key File input field name.
     */
    public function file(string $key): ?array {
        $f = $this->files[$key] ?? null;
        if ($f === null || ($f['error'] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }

    /**
     * Returns a request header value. #AI:header
     *
     * Header name is case-insensitive. Normalizes to $_SERVER format:
     * 'Authorization' → HTTP_AUTHORIZATION. Content-Type and Content-Length
     * are exceptions (not prefixed with HTTP_).
     *
     * @param string $name Header name (case-insensitive).
     */
    public function header(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $special = [
            'HTTP_CONTENT_TYPE'   => 'CONTENT_TYPE',
            'HTTP_CONTENT_LENGTH' => 'CONTENT_LENGTH',
        ];
        $lookup = $special[$key] ?? $key;
        return isset($this->server[$lookup]) ? (string) $this->server[$lookup] : null;
    }

    /**
     * Returns the client IP address. #AI:ip
     *
     * Returns the first IP from X-Forwarded-For when present, falls back to
     * REMOTE_ADDR. Does not validate against a trusted proxy list — do not
     * use as a security boundary.
     */
    public function ip(): string {
        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded !== null) {
            return trim(explode(',', $forwarded)[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Returns the HTTP method, always uppercase. #AI:method
     *
     * @return string GET, POST, PUT, PATCH, DELETE, etc.
     */
    public function method(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Returns the request path without query string. #AI:path
     *
     * @return string Path like '/users/5'.
     */
    public function path(): string {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return strtok($uri, '?') ?: '/';
    }

    /**
     * Returns the full URL including scheme and host. #AI:url
     */
    public function url(): string {
        $scheme = isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off' ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    /**
     * Returns true when HX-Request header is present. #AI:is_htmx
     */
    public function is_htmx(): bool {
        return $this->header('HX-Request') !== null;
    }

    /**
     * Returns true when datastar-request header is present. #AI:is_datastar
     */
    public function is_datastar(): bool {
        return $this->header('datastar-request') !== null;
    }

    /**
     * Returns true when Accept header contains application/json. #AI:is_json
     */
    public function is_json(): bool {
        $accept = $this->header('Accept') ?? '';
        return str_contains($accept, 'application/json');
    }

    /**
     * Returns true when X-Requested-With is XMLHttpRequest. #AI:is_ajax
     */
    public function is_ajax(): bool {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Returns true when running via PHP CLI. #AI:is_cli
     */
    public function is_cli(): bool {
        return PHP_SAPI === 'cli';
    }

    /**
     * Injects route parameters after dispatch (framework internal). #AI:set_route_params
     *
     * Called by app::run() after routing resolves. Never call from application code.
     *
     * @param array $params Route parameter key-value pairs.
     */
    public function set_route_params(array $params): void {
        $this->route_params = $params;
    }

    /**
     * Returns a route segment value. #AI:param
     *
     * Route params are set after dispatch. Type casting (e.g., @id:int) is
     * applied by the router before injection.
     *
     * @param string $key     Route parameter name.
     * @param mixed  $default Returned when the param is absent.
     */
    public function param(string $key, mixed $default = null): mixed {
        return $this->route_params[$key] ?? $default;
    }

    /**
     * Returns all route parameters as a flat array. #AI:all_params
     */
    public function all_params(): array {
        return $this->route_params;
    }

    /**
     * Returns a cookie value. #AI:cookie
     *
     * @param string $key     Cookie name.
     * @param mixed  $default Returned when the cookie is absent.
     */
    public function cookie(string $key, mixed $default = null): mixed {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Returns the raw php://input body. #AI:raw
     *
     * Useful for non-form payloads (JSON, binary, webhooks).
     */
    public function raw(): string {
        return $this->raw_body;
    }
}

#AI:class
#AI symbol: Skim\Core\Request
#AI source_path: src/core/request.php
#AI title: request
#AI description: HTTP request abstraction wrapping superglobals with typed accessors for query, POST, JSON, headers, files, and route params.
#AI role: HTTP request value object
#AI layer: core
#AI badges: [request; http; value-object; superglobal-wrapper]
#AI intro: `Skim\Core\Request` wraps PHP superglobals into a typed, testable object. It is injected by the container into controllers and middleware — never instantiated manually in application code. The same instance is shared across the entire request lifecycle.
#AI lifecycle: created once by app::run() via from_globals(), shared across middleware and controller
#AI test_seam: request::make() fabricates requests from explicit arrays without superglobals
#AI invariants: [from_globals() reads superglobals at call time; json() parses once and caches; header() is case-insensitive; ip() reads X-Forwarded-For first; method() always returns uppercase; set_route_params() is called by framework after dispatch]
#AI warnings: [X-Forwarded-For is trusted without proxy validation — do not use ip() as a security boundary; Mutations to $_GET after from_globals() are not reflected]
#AI notes: Wraps $_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, and php://input. Never access superglobals directly in app code.
#AI owns: route_params, json_body cache
#AI entry_points: [from_globals; make; get; post; input; json; file; header; ip; method; path]
#AI non_goals: [Does not validate input; Does not sanitize data; Does not handle file uploads beyond $_FILES passthrough]
#AI side_effects: [json() caches parsed body on first call; set_route_params() mutates internal state]
#AI flow: app::run() -> request::from_globals() -> middleware pipeline -> controller(request $req)
#AI section_order: [Construction; Input Access; Headers & IP; URL & Method; Detection Helpers; Route Params; Raw Access]
#AI architectural_notes: The request is a value object created once per HTTP cycle. Route params are injected after dispatch by the framework. The make() factory delegates to request_factory for test fabrication.

#AI:from_globals
#AI group: Construction
#AI frequency: internal
#AI signature: public static function from_globals(): static
#AI contract: Creates a request from PHP superglobals. Called once by app::run(). Reads superglobals at call time.

#AI:make
#AI group: Construction
#AI frequency: high
#AI signature: public static function make(mixed ...$args): static
#AI contract: Fabricates a request from explicit arrays for testing. Delegates to request_factory::make().
#AI param_details: [{name: $args | type: mixed | required: false | desc: Arguments forwarded to request_factory::make().}]
#AI return_detail: {type: static | desc: A fabricated request instance.}
#AI notes: For test use only — not for production request creation.

#AI:get
#AI group: Input Access
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns a query string value from $_GET. Does not fall through to POST or JSON.
#AI param_details: [{name: $key | type: string | required: true | desc: Query parameter name.}; {name: $default | type: mixed | required: false | desc: Returned when the key is absent.}]
#AI return_detail: {type: mixed | desc: The query value or $default.}

#AI:post
#AI group: Input Access
#AI frequency: high
#AI signature: public function post(string $key, mixed $default = null): mixed
#AI contract: Returns a POST body value from $_POST. Does not include JSON body or query string.
#AI param_details: [{name: $key | type: string | required: true | desc: POST field name.}; {name: $default | type: mixed | required: false | desc: Returned when the key is absent.}]
#AI return_detail: {type: mixed | desc: The POST value or $default.}

#AI:input
#AI group: Input Access
#AI frequency: medium
#AI signature: public function input(string $key, mixed $default = null): mixed
#AI contract: Searches GET first, then POST. Returns the first match.
#AI param_details: [{name: $key | type: string | required: true | desc: Field name to search.}; {name: $default | type: mixed | required: false | desc: Returned when absent from both sources.}]
#AI return_detail: {type: mixed | desc: First match from GET then POST, or $default.}

#AI:json
#AI group: Input Access
#AI frequency: high
#AI signature: public function json(): array
#AI contract: Parses the JSON body on first call and caches the result. Returns empty array when Content-Type is not application/json or body is invalid JSON.
#AI return_detail: {type: array | desc: Parsed JSON body or empty array.}
#AI notes: Never throws on malformed JSON. Safe to call multiple times per request.

#AI:file
#AI group: Input Access
#AI frequency: medium
#AI signature: public function file(string $key): ?array
#AI contract: Returns the uploaded file array from $_FILES, or null if absent or no file uploaded.
#AI param_details: [{name: $key | type: string | required: true | desc: File input field name.}]
#AI return_detail: {type: ?array | desc: File array or null.}

#AI:header
#AI group: Headers & IP
#AI frequency: high
#AI signature: public function header(string $name): ?string
#AI contract: Returns a request header value. Name is case-insensitive. Normalizes to $_SERVER HTTP_ format.
#AI param_details: [{name: $name | type: string | required: true | desc: Header name (case-insensitive).}]
#AI return_detail: {type: ?string | desc: Header value or null if absent.}

#AI:ip
#AI group: Headers & IP
#AI frequency: medium
#AI signature: public function ip(): string
#AI contract: Returns the client IP. Reads X-Forwarded-For first, falls back to REMOTE_ADDR.
#AI return_detail: {type: string | desc: Client IP address.}
#AI warnings: [Does not validate X-Forwarded-For against trusted proxies — not a security boundary]

#AI:method
#AI group: URL & Method
#AI frequency: high
#AI signature: public function method(): string
#AI contract: Returns the HTTP method, always uppercase.
#AI return_detail: {type: string | desc: GET, POST, PUT, PATCH, DELETE, etc.}

#AI:path
#AI group: URL & Method
#AI frequency: high
#AI signature: public function path(): string
#AI contract: Returns the request path without query string.
#AI return_detail: {type: string | desc: Path like '/users/5'.}

#AI:url
#AI group: URL & Method
#AI frequency: medium
#AI signature: public function url(): string
#AI contract: Returns the full URL including scheme and host.
#AI return_detail: {type: string | desc: Full URL.}

#AI:is_htmx
#AI group: Detection Helpers
#AI frequency: medium
#AI signature: public function is_htmx(): bool
#AI contract: Returns true when HX-Request header is present.
#AI return_detail: {type: bool | desc: True for htmx requests.}

#AI:is_datastar
#AI group: Detection Helpers
#AI frequency: medium
#AI signature: public function is_datastar(): bool
#AI contract: Returns true when datastar-request header is present.
#AI return_detail: {type: bool | desc: True for datastar requests.}

#AI:is_json
#AI group: Detection Helpers
#AI frequency: medium
#AI signature: public function is_json(): bool
#AI contract: Returns true when Accept header contains application/json.
#AI return_detail: {type: bool | desc: True for JSON-accepting requests.}

#AI:is_ajax
#AI group: Detection Helpers
#AI frequency: low
#AI signature: public function is_ajax(): bool
#AI contract: Returns true when X-Requested-With is XMLHttpRequest.
#AI return_detail: {type: bool | desc: True for XHR requests.}

#AI:is_cli
#AI group: Detection Helpers
#AI frequency: low
#AI signature: public function is_cli(): bool
#AI contract: Returns true when running via PHP CLI.
#AI return_detail: {type: bool | desc: True for CLI SAPI.}

#AI:set_route_params
#AI group: Route Params
#AI frequency: internal
#AI signature: public function set_route_params(array $params): void
#AI contract: Injects route parameters after dispatch. Called by framework, never by application code.
#AI param_details: [{name: $params | type: array | required: true | desc: Route parameter key-value pairs.}]

#AI:param
#AI group: Route Params
#AI frequency: high
#AI signature: public function param(string $key, mixed $default = null): mixed
#AI contract: Returns a route segment value. Type casting is applied by the router before injection.
#AI param_details: [{name: $key | type: string | required: true | desc: Route parameter name.}; {name: $default | type: mixed | required: false | desc: Returned when the param is absent.}]
#AI return_detail: {type: mixed | desc: Route param value or $default.}

#AI:all_params
#AI group: Route Params
#AI frequency: low
#AI signature: public function all_params(): array
#AI contract: Returns all route parameters as a flat key-value array.
#AI return_detail: {type: array | desc: All route params.}

#AI:cookie
#AI group: Raw Access
#AI frequency: medium
#AI signature: public function cookie(string $key, mixed $default = null): mixed
#AI contract: Returns a cookie value from $_COOKIE.
#AI param_details: [{name: $key | type: string | required: true | desc: Cookie name.}; {name: $default | type: mixed | required: false | desc: Returned when absent.}]
#AI return_detail: {type: mixed | desc: Cookie value or $default.}

#AI:raw
#AI group: Raw Access
#AI frequency: medium
#AI signature: public function raw(): string
#AI contract: Returns the raw php://input body. Useful for non-form payloads.
#AI return_detail: {type: string | desc: Raw request body.}
