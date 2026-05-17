<?php declare(strict_types=1);

namespace skim\core;

// HTTP response builder — never echo or die in controllers.
// The framework sends headers + body after all middleware finishes.
// Immutable pattern: most methods return $this for fluent chaining,
// but send() is the terminal call that writes to the output buffer.
class response {
    private int    $status_code = 200;
    private array  $headers     = ['Content-Type' => 'text/html; charset=utf-8'];
    private string $body        = '';
    private bool   $sent        = false;

    // --- status ---

    /**
     * @ai-contract sets HTTP status code, returns $this for chaining
     * @ai-contract must call json/view/etc after status() — status() alone does nothing
     */
    #[\NoDiscard]
    public function status(int $code): static {
        $this->status_code = $code;
        return $this;
    }

    // --- response types ---

    /**
     * @ai-contract serializes $data to JSON, sets Content-Type: application/json
     * @ai-contract $status overrides the code set via status(), or uses current code
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
     * @ai-contract sets body to raw HTML string, Content-Type: text/html
     * @ai-contract static variant for use without injected $res
     */
    public static function html(string $content): static {
        $res = new static();
        $res->headers['Content-Type'] = 'text/html; charset=utf-8';
        $res->body                    = $content;
        return $res;
    }

    /**
     * @ai-contract renders a PHP template via view::render(), sets Content-Type: text/html
     * @ai-contract throws view_exception if template file not found
     */
    public function view(string $template, array $data = []): static {
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->body                    = \skim\view\view::render($template, $data);
        return $this;
    }

    /**
     * @ai-contract renders only a named @fragment block from the template
     * @ai-contract smaller response than view() — no layout overhead
     */
    public function fragment(string $template, array $data, string $fragment): static {
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->body                    = \skim\view\view::render($template, $data, $fragment);
        return $this;
    }

    /**
     * @ai-contract auto-selects full vs fragment based on request headers (HX-Target / datastar-target)
     * @ai-contract full render when no partial-request header detected
     */
    public function smart_view(string $template, array $data, request $req): static {
        $fragment = $req->header('HX-Target') ?? $req->header('datastar-target');
        if ($fragment !== null) {
            return $this->fragment($template, $data, $fragment);
        }
        return $this->view($template, $data);
    }

    /**
     * @ai-contract sends 302 Location header, immediately returns $this
     * @ai-contract back() reads Referer header and redirects there, falls back to '/'
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

    public function back(request $req, string $fallback = '/'): static {
        $url = $req->header('Referer') ?? $fallback;
        return $this->redirect($url);
    }

    /**
     * @ai-contract calls $callback with an sse instance, disabling output buffering first
     * @ai-contract sets Content-Type: text/event-stream, Cache-Control: no-cache
     * @ai-contract sends headers immediately before callback executes
     */
    public function stream(callable $callback): static {
        $this->headers['Content-Type']  = 'text/event-stream';
        $this->headers['Cache-Control'] = 'no-cache';
        $this->headers['X-Accel-Buffering'] = 'no';

        // Mark as streaming — send() will handle differently
        $this->body = "\x00stream";   // sentinel value
        $this->sent = false;

        // Execute inline — headers sent, output buffering disabled
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
     * @ai-contract sends file as download, sets Content-Disposition: attachment
     * @ai-contract $filename is the suggested save name shown in the browser dialog
     */
    public function download(string $file_path, string $filename = ''): static {
        if (!is_file($file_path)) {
            throw new \RuntimeException("Download file not found: {$file_path}");
        }
        $filename = $filename ?: basename($file_path);
        $this->headers['Content-Type']        = 'application/octet-stream';
        $this->headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        $this->headers['Content-Length']      = (string) filesize($file_path);
        $this->body = "\x00file:{$file_path}";   // sentinel — send() reads the file
        return $this;
    }

    public function with_header(string $name, string $value): static {
        $this->headers[$name] = $value;
        return $this;
    }

    public function set_body(string $body): static {
        $this->body = $body;
        return $this;
    }

    // --- output ---

    /**
     * @ai-contract sends headers then body to the PHP output buffer
     * @ai-contract no-op if already sent (idempotent)
     */
    public function send(): void {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        // Stream was handled inline in stream()
        if ($this->body === "\x00stream") {
            return;
        }

        // File download
        if (str_starts_with($this->body, "\x00file:")) {
            $file = substr($this->body, 6);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            http_response_code($this->status_code);
            readfile($file);
            return;
        }

        http_response_code($this->status_code);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }

    // --- accessors for tests ---

    public function get_status(): int {
        return $this->status_code;
    }

    public function get_body(): string {
        return $this->body;
    }

    public function get_headers(): array {
        return $this->headers;
    }
}
