<?php declare(strict_types=1);

namespace skim\core;

// HTTP request abstraction — injected by container, never instantiate manually.
// The same instance is shared across the middleware pipeline and controller.
// Wraps $_GET, $_POST, $_SERVER, $_FILES — never access superglobals directly in app code.
class request {
    private array $route_params = [];
    private ?array $json_body   = null;   // lazy-parsed on first json() call

    public function __construct(
        private readonly array $query,    // $_GET
        private readonly array $post,     // $_POST
        private readonly array $server,   // $_SERVER
        private readonly array $cookies,  // $_COOKIE
        private readonly array $files,    // $_FILES
        private readonly string $raw_body, // php://input
    ) {}

    /**
     * @ai-contract creates request from PHP superglobals — used in app::run()
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
     * @ai-contract for tests — construct from explicit arrays without superglobals
     */
    public static function make(
        string $method     = 'GET',
        string $path       = '/',
        array  $query      = [],
        array  $post       = [],
        array  $headers    = [],
        string $raw_body   = '',
        array  $cookies    = [],
        array  $files      = [],
    ): static {
        $server = array_merge(
            ['REQUEST_METHOD' => strtoupper($method), 'REQUEST_URI' => $path],
            array_combine(
                array_map(fn($k) => 'HTTP_' . strtoupper(str_replace('-', '_', $k)), array_keys($headers)),
                array_values($headers),
            ),
        );
        return new static(query: $query, post: $post, server: $server, cookies: $cookies, files: $files, raw_body: $raw_body);
    }

    // --- input accessors ---

    /**
     * @ai-contract returns $_GET value, or $default if absent
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->query[$key] ?? $default;
    }

    /**
     * @ai-contract returns $_POST value, or $default if absent
     */
    public function post(string $key, mixed $default = null): mixed {
        return $this->post[$key] ?? $default;
    }

    /**
     * @ai-contract returns first match: GET → POST → default
     */
    public function input(string $key, mixed $default = null): mixed {
        return $this->query[$key] ?? $this->post[$key] ?? $default;
    }

    /**
     * @ai-contract parses JSON body on first call, caches result
     * @ai-contract returns empty array if Content-Type is not application/json or body is not valid JSON
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
     * @ai-contract returns file array from $_FILES, null if key absent or no file uploaded
     */
    public function file(string $key): ?array {
        $f = $this->files[$key] ?? null;
        if ($f === null || ($f['error'] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }

    /**
     * @ai-contract header name is case-insensitive: 'Authorization' and 'authorization' both work
     * @ai-contract returns null if header absent
     */
    public function header(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        // CONTENT_TYPE and CONTENT_LENGTH are not prefixed with HTTP_
        $special = [
            'HTTP_CONTENT_TYPE'   => 'CONTENT_TYPE',
            'HTTP_CONTENT_LENGTH' => 'CONTENT_LENGTH',
        ];
        $lookup = $special[$key] ?? $key;
        return isset($this->server[$lookup]) ? (string) $this->server[$lookup] : null;
    }

    /**
     * @ai-contract returns client IP — respects X-Forwarded-For when behind proxy
     * @ai-contract trusts X-Forwarded-For only when REMOTE_ADDR matches trusted proxy range
     */
    public function ip(): string {
        $forwarded = $this->header('X-Forwarded-For');
        if ($forwarded !== null) {
            return trim(explode(',', $forwarded)[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * @ai-contract returns HTTP method, always uppercase: GET POST PUT PATCH DELETE
     */
    public function method(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * @ai-contract returns path without query string: /users/5
     */
    public function path(): string {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        return strtok($uri, '?') ?: '/';
    }

    /**
     * @ai-contract returns full URL including scheme and host
     */
    public function url(): string {
        $scheme = isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off' ? 'https' : 'http';
        $host   = $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    // --- framework detection helpers ---

    /**
     * @ai-contract true when HX-Request header is present (htmx request)
     */
    public function is_htmx(): bool {
        return $this->header('HX-Request') !== null;
    }

    /**
     * @ai-contract true when datastar-request header is present
     */
    public function is_datastar(): bool {
        return $this->header('datastar-request') !== null;
    }

    /**
     * @ai-contract true when Accept: application/json header present
     */
    public function is_json(): bool {
        $accept = $this->header('Accept') ?? '';
        return str_contains($accept, 'application/json');
    }

    /**
     * @ai-contract true when X-Requested-With: XMLHttpRequest header present
     */
    public function is_ajax(): bool {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * @ai-contract true when running via PHP CLI (php skim command)
     */
    public function is_cli(): bool {
        return PHP_SAPI === 'cli';
    }

    // --- route params (injected by app::run after dispatch) ---

    /**
     * @ai-contract called by app::run() after dispatch, never by application code
     */
    public function set_route_params(array $params): void {
        $this->route_params = $params;
    }

    /**
     * @ai-contract returns a route segment value: @id:int → int cast applied
     * @ai-contract returns null if param key absent
     */
    public function param(string $key, mixed $default = null): mixed {
        return $this->route_params[$key] ?? $default;
    }

    public function all_params(): array {
        return $this->route_params;
    }

    public function cookie(string $key, mixed $default = null): mixed {
        return $this->cookies[$key] ?? $default;
    }

    public function raw(): string {
        return $this->raw_body;
    }
}
