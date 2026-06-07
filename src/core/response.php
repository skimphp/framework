<?php declare(strict_types=1);

namespace skim\core;

/**
 * HTTP response builder — fluent chainable methods to compose status, headers, and body.
 *
 * Use in controllers to build the HTTP response. Never echo or die — the framework
 * sends headers and body after all middleware finishes via send(). Most methods
 * return $this for fluent chaining. The response object is created fresh per request.
 *
 * Example:
 *   return $res->status(201)->json(['id' => $user->id]);
 *   return $res->view('users/show', ['user' => $user]);
 *   return $res->redirect('/login');
 *
 * Testing: inspect via get_status(), get_body(), get_headers(), get_json().
 *
 * #AI:class
 */
class response {
    private int    $status_code = 200;
    private array  $headers     = ['Content-Type' => 'text/html; charset=utf-8'];
    private string $body        = '';
    private bool   $sent        = false;

    /**
     * Sets the HTTP status code. #AI:status
     *
     * Does not send headers — call json(), view(), etc. after status().
     *
     * @param int $code HTTP status code (200, 201, 404, 500, etc.).
     */
    #[\NoDiscard]
    public function status(int $code): static {
        $this->status_code = $code;
        return $this;
    }

    /**
     * Serializes data to JSON and sets Content-Type. #AI:json
     *
     * Uses JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES for clean output.
     * Optional $status overrides the code set via status().
     *
     * @param mixed $data   Data to serialize.
     * @param int   $status Optional status code override (0 = use current).
     */
    public function json(mixed $data, int $status = 0): static {
        if ($status > 0) {
            $this->status_code = $status;
        }
        $this->headers['Content-Type'] = 'application/json';
        $this->body                    = (string) json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        return $this;
    }

    /**
     * Creates a new response with raw HTML content. #AI:html
     *
     * Static factory — returns a new instance, does not modify the current one.
     *
     * @param string $content Raw HTML string.
     */
    public static function html(string $content): static {
        $res = new static();
        $res->headers['Content-Type'] = 'text/html; charset=utf-8';
        $res->body                    = $content;
        return $res;
    }

    /**
     * Renders a PHP template via view::render(). #AI:view
     *
     * Sets Content-Type to text/html. Delegates to the view engine.
     *
     * Example:
     *   return $res->view('users/show', ['user' => $user]);
     *
     * @param string $template Template path relative to views directory.
     * @param array  $data     Variables extracted into the template scope.
     * @throws \skim\view\view_exception If template file not found.
     */
    public function view(string $template, array $data = []): static {
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->body                    = \skim\view\view::render($template, $data);
        return $this;
    }

    /**
     * Renders only a named @fragment block from the template. #AI:fragment
     *
     * Smaller response than view() — no layout overhead. Use for htmx/datastar
     * partial updates.
     *
     * Example:
     *   return $res->fragment('users/show', ['user' => $user], 'user-card');
     *
     * @param string $template Template path.
     * @param array  $data     Template variables.
     * @param string $fragment Fragment name bounded by @fragment/@end markers.
     */
    public function fragment(string $template, array $data, string $fragment): static {
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->body                    = \skim\view\view::render($template, $data, $fragment);
        return $this;
    }

    /**
     * Auto-selects full view vs fragment based on request headers. #AI:smart_view
     *
     * Renders a fragment when HX-Target or datastar-target header is present,
     * otherwise renders the full view with layout.
     *
     * Example:
     *   return $res->smart_view('users/show', ['user' => $user], $req);
     *
     * @param string  $template Template path.
     * @param array   $data     Template variables.
     * @param request $req      Current request for header inspection.
     */
    public function smart_view(string $template, array $data, request $req): static {
        $fragment = $req->header('HX-Target') ?? $req->header('datastar-target');
        if ($fragment !== null) {
            return $this->fragment($template, $data, $fragment);
        }
        return $this->view($template, $data);
    }

    /**
     * Sends a 302 redirect to the given URL. #AI:redirect
     *
     * Sets Location header. Does not send headers immediately — send() must
     * be called afterwards (or returned from controller for framework to send).
     *
     * @param string $url Target URL. Empty string is a no-op.
     */
    public function redirect(string $url = ''): static {
        if ($url === '') {
            return $this;
        }
        $this->status_code = $this->status_code === 200 ? 302 : $this->status_code;
        $this->headers['Location'] = $url;
        $this->body                = '';
        return $this;
    }

    /**
     * Redirects to the Referer header, falling back to a default URL. #AI:back
     *
     * @param request $req      Current request to read Referer from.
     * @param string  $fallback URL used when Referer is absent.
     */
    public function back(request $req, string $fallback = '/'): static {
        $url = $req->header('Referer') ?? $fallback;
        return $this->redirect($url);
    }

    /**
     * Streams Server-Sent Events via a callback. #AI:stream
     *
     * Sends headers immediately, disables output buffering, and passes an SSE
     * instance to the callback. The callback writes events directly.
     *
     * WARNING: Headers are sent inline — middleware response modifications after
     * this call have no effect.
     *
     * Example:
     *   return $res->stream(function(sse $sse) {
     *       $sse->send('message', 'Hello');
     *   });
     *
     * @param callable $callback Receives an sse instance to emit events.
     */
    public function stream(callable $callback): static {
        $this->headers['Content-Type']  = 'text/event-stream';
        $this->headers['Cache-Control'] = 'no-cache';
        $this->headers['X-Accel-Buffering'] = 'no';

        $this->body = "\x00stream";
        $this->sent = false;

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        http_response_code($this->status_code);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $sse = new \skim\realtime\sse();
        $callback($sse);
        $this->sent = true;

        return $this;
    }

    /**
     * Sends a file as a download attachment. #AI:download
     *
     * Sets Content-Disposition: attachment. The file is read and sent by send().
     *
     * @param string $file_path Absolute path to the file on disk.
     * @param string $filename  Suggested save name. Defaults to basename.
     * @throws \RuntimeException If file_path does not exist.
     */
    public function download(string $file_path, string $filename = ''): static {
        if (!is_file($file_path)) {
            throw new \RuntimeException("Download file not found: {$file_path}");
        }
        $filename = $filename ?: basename($file_path);
        $this->headers['Content-Type']        = 'application/octet-stream';
        $this->headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        $this->headers['Content-Length']      = (string) filesize($file_path);
        $this->body = "\x00file:{$file_path}";
        return $this;
    }

    /**
     * Adds or overwrites a response header. #AI:with_header
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     */
    public function with_header(string $name, string $value): static {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Replaces the response body. #AI:set_body
     *
     * @param string $body Raw body content.
     */
    public function set_body(string $body): static {
        $this->body = $body;
        return $this;
    }

    /**
     * Sends headers and body to the output buffer. #AI:send
     *
     * Idempotent — no-op if already sent. Handles stream and file download
     * sentinel values internally. Called by app::run() after middleware completes.
     */
    public function send(): void {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        if ($this->body === "\x00stream") {
            return;
        }

        if (str_starts_with($this->body, "\x00file:")) {
            $file = substr($this->body, 6);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            http_response_code($this->status_code);
            readfile($file);
            return;
        }

        if (!headers_sent()) {
            http_response_code($this->status_code);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->body;
    }

    /**
     * Returns the current HTTP status code. #AI:get_status
     */
    public function get_status(): int {
        return $this->status_code;
    }

    /**
     * Returns the raw response body. #AI:get_body
     */
    public function get_body(): string {
        return $this->body;
    }

    /**
     * Returns all response headers. #AI:get_headers
     *
     * @return array Associative array of header name => value.
     */
    public function get_headers(): array {
        return $this->headers;
    }

    /**
     * Returns a response header value by name. #AI:get_header
     *
     * Case-insensitive lookup.
     *
     * @param string $key Header name.
     */
    public function get_header(string $key): ?string {
        foreach ($this->headers as $name => $value) {
            if (strcasecmp($name, $key) === 0) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Decodes the JSON response body as an array. #AI:get_json
     *
     * Returns empty array when the body is not valid JSON.
     */
    public function get_json(): array {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}

#AI:class
#AI symbol: skim\core\response
#AI source_path: src/core/response.php
#AI title: response
#AI description: HTTP response builder with fluent chaining for status, headers, JSON, views, redirects, streaming, and file downloads.
#AI role: HTTP response builder
#AI layer: core
#AI badges: [response; http; fluent; builder]
#AI intro: `skim\core\response` builds the HTTP response through fluent method chaining. Controllers return the response object; the framework calls send() after all middleware completes. Supports JSON, HTML views, fragments, redirects, SSE streaming, and file downloads.
#AI lifecycle: created fresh per request by app::run(), populated by controller, sent after middleware
#AI test_seam: inspect via get_status(), get_body(), get_headers(), get_json() without calling send()
#AI invariants: [send() is idempotent — no-op if already sent; stream() sends headers immediately; download() uses sentinel body detected by send(); status() returns $this for chaining; json() uses JSON_UNESCAPED_UNICODE]
#AI warnings: [stream() sends headers inline — middleware response modifications after stream() have no effect; Never echo or die in controllers — always return $res->...]
#AI notes: Most methods return $this for fluent chaining. The #[\NoDiscard] attribute on status() prevents silently losing the status code.
#AI owns: status_code, headers, body, sent flag
#AI entry_points: [status; json; view; fragment; smart_view; redirect; stream; download; send]
#AI non_goals: [Does not handle HTTP transport (PHP sends headers); Does not compress output; Does not manage cookies directly]
#AI side_effects: [send() writes headers and body to output buffer; stream() sends headers immediately and disables output buffering]
#AI flow: controller -> $res->status()->json()/view()/redirect() -> return $res -> middleware post-processing -> send()
#AI section_order: [Status; JSON & HTML; Views; Redirects; Streaming & Downloads; Headers & Body; Output; Test Accessors]
#AI architectural_notes: The response is a mutable builder object. send() handles three body types: normal string, stream sentinel (\x00stream), and file sentinel (\x00file:path). This avoids buffering large files in memory.

#AI:status
#AI group: Status
#AI frequency: high
#AI signature: public function status(int $code): static
#AI contract: Sets the HTTP status code. Returns $this for chaining. Does not send headers.
#AI param_details: [{name: $code | type: int | required: true | desc: HTTP status code (200, 201, 404, 500, etc.).}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:json
#AI group: JSON & HTML
#AI frequency: high
#AI signature: public function json(mixed $data, int $status = 0): static
#AI contract: Serializes $data to JSON with JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES. Sets Content-Type to application/json. Optional $status overrides the current status code.
#AI param_details: [{name: $data | type: mixed | required: true | desc: Data to serialize to JSON.}; {name: $status | type: int | required: false | desc: Optional status code override. 0 means use current.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:html
#AI group: JSON & HTML
#AI frequency: low
#AI signature: public static function html(string $content): static
#AI contract: Static factory that creates a new response with raw HTML content and text/html Content-Type.
#AI param_details: [{name: $content | type: string | required: true | desc: Raw HTML string.}]
#AI return_detail: {type: static | desc: New response instance with HTML body.}

#AI:view
#AI group: Views
#AI frequency: high
#AI signature: public function view(string $template, array $data = []): static
#AI contract: Renders a PHP template via view::render() and sets Content-Type to text/html.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views directory.}; {name: $data | type: array | required: false | desc: Variables extracted into template scope.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}
#AI throws_details: [{type: \skim\view\view_exception | desc: If template file not found.}]

#AI:fragment
#AI group: Views
#AI frequency: medium
#AI signature: public function fragment(string $template, array $data, string $fragment): static
#AI contract: Renders only a named @fragment block from the template. Smaller response than view() — no layout overhead.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path.}; {name: $data | type: array | required: true | desc: Template variables.}; {name: $fragment | type: string | required: true | desc: Fragment name.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:smart_view
#AI group: Views
#AI frequency: medium
#AI signature: public function smart_view(string $template, array $data, request $req): static
#AI contract: Auto-selects full view vs fragment based on HX-Target or datastar-target request headers.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path.}; {name: $data | type: array | required: true | desc: Template variables.}; {name: $req | type: request | required: true | desc: Current request for header inspection.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:redirect
#AI group: Redirects
#AI frequency: high
#AI signature: public function redirect(string $url = ''): static
#AI contract: Sets 302 status and Location header. Empty URL is a no-op. Does not send headers immediately.
#AI param_details: [{name: $url | type: string | required: false | desc: Target URL. Empty string is a no-op.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:back
#AI group: Redirects
#AI frequency: medium
#AI signature: public function back(request $req, string $fallback = '/'): static
#AI contract: Redirects to the Referer header value, falling back to $fallback when absent.
#AI param_details: [{name: $req | type: request | required: true | desc: Current request to read Referer from.}; {name: $fallback | type: string | required: false | desc: URL used when Referer is absent.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:stream
#AI group: Streaming & Downloads
#AI frequency: medium
#AI signature: public function stream(callable $callback): static
#AI contract: Sends SSE headers immediately, disables output buffering, and passes an sse instance to the callback.
#AI param_details: [{name: $callback | type: callable | required: true | desc: Receives an sse instance to emit events.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}
#AI warnings: [Headers are sent inline — middleware response modifications after this call have no effect]
#AI side_effects: [Sends HTTP headers immediately; Disables output buffering]

#AI:download
#AI group: Streaming & Downloads
#AI frequency: low
#AI signature: public function download(string $file_path, string $filename = ''): static
#AI contract: Sets Content-Disposition: attachment headers. File is read and sent by send().
#AI param_details: [{name: $file_path | type: string | required: true | desc: Absolute path to the file on disk.}; {name: $filename | type: string | required: false | desc: Suggested save name. Defaults to basename.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}
#AI throws_details: [{type: \RuntimeException | desc: If file_path does not exist.}]

#AI:with_header
#AI group: Headers & Body
#AI frequency: medium
#AI signature: public function with_header(string $name, string $value): static
#AI contract: Adds or overwrites a response header.
#AI param_details: [{name: $name | type: string | required: true | desc: Header name.}; {name: $value | type: string | required: true | desc: Header value.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:set_body
#AI group: Headers & Body
#AI frequency: low
#AI signature: public function set_body(string $body): static
#AI contract: Replaces the response body.
#AI param_details: [{name: $body | type: string | required: true | desc: Raw body content.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}

#AI:send
#AI group: Output
#AI frequency: internal
#AI signature: public function send(): void
#AI contract: Sends headers and body to the PHP output buffer. Idempotent — no-op if already sent. Handles stream and file download sentinels internally.
#AI side_effects: [Writes HTTP headers and body to output buffer]

#AI:get_status
#AI group: Test Accessors
#AI frequency: medium
#AI signature: public function get_status(): int
#AI contract: Returns the current HTTP status code.
#AI return_detail: {type: int | desc: HTTP status code.}

#AI:get_body
#AI group: Test Accessors
#AI frequency: medium
#AI signature: public function get_body(): string
#AI contract: Returns the raw response body string.
#AI return_detail: {type: string | desc: Response body.}

#AI:get_headers
#AI group: Test Accessors
#AI frequency: low
#AI signature: public function get_headers(): array
#AI contract: Returns all response headers as an associative array.
#AI return_detail: {type: array | desc: Header name => value pairs.}

#AI:get_header
#AI group: Test Accessors
#AI frequency: low
#AI signature: public function get_header(string $key): ?string
#AI contract: Returns a response header value by name. Case-insensitive lookup.
#AI param_details: [{name: $key | type: string | required: true | desc: Header name.}]
#AI return_detail: {type: ?string | desc: Header value or null if absent.}

#AI:get_json
#AI group: Test Accessors
#AI frequency: medium
#AI signature: public function get_json(): array
#AI contract: Decodes the JSON response body as an array. Returns empty array when invalid.
#AI return_detail: {type: array | desc: Decoded JSON or empty array.}
