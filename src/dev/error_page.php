<?php declare(strict_types=1);

namespace skim\dev;

/**
 * Developer-friendly error page rendered when APP_DEBUG=true. #AI:class
 *
 * Use only via the exception handler registered in app::run(). Delegates
 * HTML generation to dev_view templates with a fallback to inline HTML
 * if the template system itself fails. Never expose in production.
 *
 * Example:
 *   // Registered by app::run() when APP_DEBUG=true:
 *   set_exception_handler(fn(\Throwable $e) => error_page::render($e));
 *
 * Testing: Call render() directly with a test Throwable; output goes to stdout.
 *
 * #AI:class
 */
final class error_page {
    private const CONTEXT_LINES = 10;

    private const SECRET_KEYS = [
        'password', 'secret', 'key', 'token', 'api_key', 'apikey',
        'auth', 'credentials', 'private', 'app_key', 'db_password',
        'redis_password', 'aws_secret', 'aws_access_key_id',
    ];

    /**
     * Renders a full HTML error page to stdout using dev_view templates. #AI:render
     *
     * Falls back to minimal inline HTML if template rendering fails, ensuring
     * the developer always sees the error even when the template system breaks.
     *
     * @param \Throwable $e The exception to render.
     */
    public static function render(\Throwable $e): void {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        try {
            $data = self::collect($e);
            echo dev_view::render('error_page', $data);
        }
        catch (\Throwable $renderError) {
            self::fallback($e, $renderError);
        }
    }

    /**
     * Collects all data needed by the error page template. #AI:collect
     *
     * Extracts exception details, code context, parsed stack frames,
     * environment variables, request info, and solution suggestions.
     *
     * @param \Throwable $e The exception to collect data from.
     */
    private static function collect(\Throwable $e): array {
        $class   = get_class($e);
        $message = $e->getMessage();
        $file    = $e->getFile();
        $line    = $e->getLine();

        return [
            'page_title'  => "Error — {$class}",
            'class'       => $class,
            'message'     => $message,
            'file'        => $file,
            'line'        => $line,
            'php_version' => PHP_VERSION,
            'code_lines'  => self::code_context($file, $line),
            'line_start'  => max(1, $line - self::CONTEXT_LINES),
            'line_end'    => $line + self::CONTEXT_LINES,
            'frames'      => self::parse_frames($e),
            'env_server'  => self::collect_server_env(),
            'env_vars'    => self::collect_env_vars(),
            'request'     => self::collect_request(),
            'solutions'   => self::suggest_solutions($e),
            'trace_text'  => $e->getTraceAsString(),
        ];
    }

    /**
     * Reads ±N lines around the error with basic PHP syntax highlighting. #AI:code_context
     *
     * Returns pre-escaped HTML spans with syntax classes. The error line
     * is marked with hl=true for the template to add visual emphasis.
     *
     * @param string $file    Absolute path to the source file.
     * @param int    $line    Error line number (1-based).
     * @param int    $context Number of lines to show above and below.
     */
    private static function code_context(string $file, int $line, int $context = self::CONTEXT_LINES): array {
        if (!is_file($file)) {
            return [];
        }

        $raw   = file($file) ?: [];
        $start = max(0, $line - $context - 1);
        $end   = min(count($raw) - 1, $line + $context - 1);
        $out   = [];

        for ($i = $start; $i <= $end; $i++) {
            $code = rtrim($raw[$i] ?? '');
            $out[] = [
                'num'  => $i + 1,
                'code' => self::highlight_php($code),
                'hl'   => ($i + 1) === $line,
            ];
        }

        return $out;
    }

    /**
     * Parses exception trace into structured frame data for the template. #AI:parse_frames
     *
     * Each frame includes file, line, class, function, type, and a noise flag
     * indicating whether it's a framework/vendor internal frame.
     *
     * @param \Throwable $e The exception whose trace to parse.
     */
    private static function parse_frames(\Throwable $e): array {
        $frames = [];

        $frames[] = [
            'idx'      => 0,
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'class'    => '',
            'function' => '',
            'call'     => self::format_error_call($e),
            'noise'    => false,
            'search'   => strtolower($e->getFile() . ' ' . get_class($e)),
            'args'     => [],
        ];

        foreach ($e->getTrace() as $i => $t) {
            $file  = $t['file'] ?? '';
            $line  = $t['line'] ?? 0;
            $class = $t['class'] ?? '';
            $func  = $t['function'] ?? '';
            $type  = $t['type'] ?? '';
            $noise = self::is_noise($file, $class, $func);

            $call = '';
            if ($class !== '') {
                $call = $class . $type . $func . '()';
            }
            elseif ($func !== '') {
                $call = $func . '()';
            }

            $args = [];
            if (!empty($t['args'])) {
                foreach ($t['args'] as $j => $arg) {
                    $args[] = [
                        'idx'   => $j,
                        'type'  => self::arg_type($arg),
                        'value' => self::arg_value($arg),
                    ];
                }
            }

            $frames[] = [
                'idx'      => $i + 1,
                'file'     => $file,
                'line'     => $line,
                'class'    => $class,
                'function' => $func,
                'call'     => $call,
                'noise'    => $noise,
                'search'   => strtolower(($file ?: '') . ' ' . $class . ' ' . $func),
                'args'     => $args,
            ];
        }

        return $frames;
    }

    /**
     * Collects server/PHP environment data for the Environment panel. #AI:collect_server_env
     */
    private static function collect_server_env(): array {
        $s = $_SERVER;
        return [
            ['key' => 'php version',        'value' => PHP_VERSION,                              'class' => 'ok'],
            ['key' => 'sapi',               'value' => PHP_SAPI,                                 'class' => ''],
            ['key' => 'server',             'value' => $s['SERVER_SOFTWARE'] ?? '—',             'class' => ''],
            ['key' => 'os',                 'value' => PHP_OS . ' ' . php_uname('r'),            'class' => ''],
            ['key' => 'memory_limit',       'value' => ini_get('memory_limit') ?: '—',           'class' => ''],
            ['key' => 'max_execution_time', 'value' => (ini_get('max_execution_time') ?: '—') . 's', 'class' => ''],
            ['key' => 'opcache',            'value' => function_exists('opcache_get_status') ? 'enabled' : 'disabled', 'class' => function_exists('opcache_get_status') ? 'ok' : 'warn'],
            ['key' => 'xdebug',             'value' => extension_loaded('xdebug') ? 'enabled' : 'disabled', 'class' => extension_loaded('xdebug') ? 'ok' : 'warn'],
            ['key' => 'document_root',      'value' => $s['DOCUMENT_ROOT'] ?? '—',               'class' => 'blue'],
            ['key' => 'script_filename',    'value' => basename($s['SCRIPT_FILENAME'] ?? '—'),   'class' => ''],
        ];
    }

    /**
     * Collects environment variables with secret masking. #AI:collect_env_vars
     *
     * Keys matching SECRET_KEYS are masked with bullet characters and
     * marked as secret for click-to-reveal in the template.
     */
    private static function collect_env_vars(): array {
        $items = [];
        $env   = getenv();

        $show_keys = [
            'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_KEY',
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'CACHE_DRIVER', 'REDIS_HOST', 'REDIS_PASSWORD',
            'QUEUE_DRIVER', 'SESSION_DRIVER', 'LOG_CHANNEL',
            'MAIL_MAILER', 'MAIL_HOST',
        ];

        foreach ($show_keys as $key) {
            $val = $env[$key] ?? null;
            if ($val === null || $val === false) {
                continue;
            }

            $is_secret = self::is_secret_key($key);
            $items[] = [
                'key'          => $key,
                'value'        => $is_secret ? str_repeat('•', min(strlen($val), 28)) : (string)$val,
                'class'        => $is_secret ? '' : self::env_value_class($key, (string)$val),
                'secret'       => $is_secret,
                'secret_value' => $is_secret ? (string)$val : '',
            ];
        }

        return $items;
    }

    /**
     * Collects HTTP request data for the Request panel. #AI:collect_request
     */
    private static function collect_request(): array {
        $s      = $_SERVER;
        $scheme = (!empty($s['HTTPS']) && $s['HTTPS'] !== 'off') ? 'https' : 'http';

        $sections = [];

        $sections['request_info'] = [
            'title' => 'Request info',
            'items' => [
                ['key' => 'method',         'value' => $s['REQUEST_METHOD'] ?? 'GET',         'class' => 'blue'],
                ['key' => 'scheme',         'value' => $scheme,                                'class' => $scheme === 'https' ? 'ok' : 'warn'],
                ['key' => 'host',           'value' => $s['HTTP_HOST'] ?? $s['SERVER_NAME'] ?? '—', 'class' => ''],
                ['key' => 'uri',            'value' => $s['REQUEST_URI'] ?? '—',               'class' => 'blue'],
                ['key' => 'query string',   'value' => $s['QUERY_STRING'] ?? '',               'class' => ($s['QUERY_STRING'] ?? '') !== '' ? '' : 'muted'],
                ['key' => 'protocol',       'value' => $s['SERVER_PROTOCOL'] ?? 'HTTP/1.1',    'class' => ''],
                ['key' => 'remote addr',    'value' => $s['REMOTE_ADDR'] ?? '—',               'class' => ''],
                ['key' => 'forwarded for',  'value' => $s['HTTP_X_FORWARDED_FOR'] ?? '—',      'class' => 'muted'],
            ],
        ];

        $header_items = [];
        $header_keys  = [
            'HTTP_ACCEPT' => 'accept', 'HTTP_ACCEPT_ENCODING' => 'accept-encoding',
            'HTTP_ACCEPT_LANGUAGE' => 'accept-language', 'HTTP_USER_AGENT' => 'user-agent',
            'HTTP_REFERER' => 'referer', 'CONTENT_TYPE' => 'content-type',
            'HTTP_X_REQUESTED_WITH' => 'x-requested-with',
        ];
        foreach ($header_keys as $server_key => $label) {
            $val = $s[$server_key] ?? null;
            $header_items[] = [
                'key'   => $label,
                'value' => $val !== null ? (string)$val : '—',
                'class' => $val !== null ? ($label === 'referer' ? 'blue' : '') : 'muted',
            ];
        }
        if (isset($s['HTTP_AUTHORIZATION'])) {
            $header_items[] = [
                'key'          => 'authorization',
                'value'        => str_repeat('•', 36),
                'class'        => '',
                'secret'       => true,
                'secret_value' => $s['HTTP_AUTHORIZATION'],
            ];
        }
        $sections['headers'] = ['title' => 'Headers', 'items' => $header_items];

        $sections['get']  = self::superglobal_section('$_GET',  $_GET);
        $sections['post'] = self::superglobal_section('$_POST', $_POST);

        $cookie_items = [];
        foreach ($_COOKIE as $k => $v) {
            $tail = mb_substr((string)$v, -4);
            $cookie_items[] = [
                'key'          => (string)$k,
                'value'        => '••••' . $tail,
                'class'        => '',
                'secret'       => true,
                'secret_value' => (string)$v,
            ];
        }
        $sections['cookies'] = [
            'title' => '$_COOKIE',
            'items' => $cookie_items,
            'empty_note' => 'no cookies',
        ];

        return $sections;
    }

    /**
     * Suggests fixes based on exception type — e.g. similar method names. #AI:suggest_solutions
     *
     * Currently handles "undefined method" errors by using reflection to find
     * methods with close Levenshtein distance on the target class.
     */
    private static function suggest_solutions(\Throwable $e): array {
        $solutions = [];
        $msg = $e->getMessage();

        if (preg_match('/Call to undefined method\s+(.+?)::(\w+)\(\)/', $msg, $m)) {
            $class_name  = $m[1];
            $called_name = $m[2];

            if (class_exists($class_name)) {
                try {
                    $ref     = new \ReflectionClass($class_name);
                    $methods = array_map(fn(\ReflectionMethod $rm) => $rm->getName(), $ref->getMethods());

                    $similar = [];
                    foreach ($methods as $method) {
                        $dist = levenshtein($called_name, $method);
                        if ($dist <= 5 && $dist > 0) {
                            $similar[] = ['name' => $method, 'distance' => $dist];
                        }
                    }
                    usort($similar, fn($a, $b) => $a['distance'] <=> $b['distance']);

                    if ($similar !== []) {
                        $best = $similar[0]['name'];
                        $solutions[] = [
                            'type'    => 'suggestion',
                            'title'   => "Did you mean {$best}()?",
                            'body'    => "Class {$class_name} doesn't have a {$called_name}() method, "
                                       . "but there is a method with a similar name: {$best}() "
                                       . "(Levenshtein distance: {$similar[0]['distance']}).",
                            'similar' => $similar,
                            'methods' => $methods,
                            'class'   => $class_name,
                            'called'  => $called_name,
                        ];
                    }
                }
                catch (\Throwable) {
                }
            }
        }

        return $solutions;
    }

    /**
     * Minimal fallback HTML when template rendering itself fails. #AI:fallback
     *
     * Ensures the developer always sees the error, even if dev_view or
     * the template files are broken.
     */
    private static function fallback(\Throwable $e, ?\Throwable $renderError = null): void {
        $class   = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file    = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line    = $e->getLine();
        $trace   = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        $render_error_html = '';
        if ($renderError !== null) {
            $re_class   = htmlspecialchars(get_class($renderError), ENT_QUOTES, 'UTF-8');
            $re_message = htmlspecialchars($renderError->getMessage(), ENT_QUOTES, 'UTF-8');
            $re_file    = htmlspecialchars($renderError->getFile(), ENT_QUOTES, 'UTF-8');
            $re_line    = $renderError->getLine();
            $render_error_html = "<h3>Render error</h3><pre><strong style='color:#f38ba8'>{$re_class}</strong>: {$re_message}\nat {$re_file}:{$re_line}</pre>";
        }

        echo <<<HTML
        <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error — {$class}</title>
        <style>body{font-family:monospace;background:#1e1e2e;color:#cdd6f4;margin:0;padding:2rem}
        .h{background:#313244;border-left:4px solid #f38ba8;padding:1rem;border-radius:6px;margin-bottom:1rem}
        pre{background:#181825;padding:1rem;border-radius:4px;overflow-x:auto;font-size:.85em}</style></head>
        <body><div class="h"><strong style="color:#f38ba8">{$class}</strong><br>{$message}<br>
        <small style="color:#a6e3a1">{$file}:{$line}</small></div>
        <h3>Stack trace</h3><pre>{$trace}</pre>
        {$render_error_html}
        <p style="color:#64748b;font-size:12px">⚠ Error page template failed — showing fallback</p>
        </body></html>
        HTML;
    }

    private static function format_error_call(\Throwable $e): string {
        $class = get_class($e);
        $short = basename(str_replace('\\', '/', $class));
        return $short;
    }

    /**
     * Determines whether a stack frame is framework/vendor noise. #AI:is_noise
     *
     * Checks the frame's file path AND its class/function name because PHP's
     * trace reports the file of the caller, not the file where a closure
     * was defined. A closure `{closure:skim\core\app::dispatch():483}` may
     * have `file` = `middleware/toolbar_middleware.php` even though it
     * belongs to the framework.
     *
     * @param string      $file Frame file path (caller's file).
     * @param string      $class Frame class name (may be empty).
     * @param string      $function Frame function name (may be empty).
     */
    private static function is_noise(string $file, string $class = '', string $function = ''): bool {
        if ($file === '' && $class === '' && $function === '') {
            return true;
        }

        $normalized = str_replace('\\', '/', $file);
        if (str_contains($normalized, '/vendor/')
            || str_contains($normalized, '/src/dev/')
            || str_contains($normalized, '/src/core/')
        ) {
            return true;
        }

        if ($class !== '') {
            $class_norm = ltrim(str_replace('\\', '/', $class), '/');
            if (str_starts_with($class_norm, 'skim/')
                || str_starts_with($class_norm, 'skim\\')
            ) {
                return true;
            }
            if (str_contains($class_norm, '/middleware/')
                || str_contains($class_norm, 'middleware\\')
            ) {
                return true;
            }
        }

        if (str_starts_with($function, '{closure:')) {
            if ($class !== '' && (
                str_starts_with($class, 'skim\\')
                || str_contains($class, '\\middleware\\')
            )) {
                return true;
            }
            if (str_contains($function, 'skim\\core\\')
                || str_contains($function, 'skim\\\\core\\\\')
            ) {
                return true;
            }
        }

        return false;
    }

    private static function arg_type(mixed $arg): string {
        return match (true) {
            is_object($arg) => get_class($arg),
            is_array($arg)  => 'array',
            is_string($arg) => 'string',
            is_int($arg)    => 'int',
            is_float($arg)  => 'float',
            is_bool($arg)   => 'bool',
            is_null($arg)   => 'null',
            default         => gettype($arg),
        };
    }

    private static function arg_value(mixed $arg): string {
        return match (true) {
            is_object($arg) => '#' . spl_object_id($arg),
            is_array($arg)  => '[' . count($arg) . ']',
            is_string($arg) => mb_strlen($arg) > 60 ? mb_substr($arg, 0, 57) . '…' : $arg,
            is_bool($arg)   => $arg ? 'true' : 'false',
            is_null($arg)   => 'null',
            default         => (string)$arg,
        };
    }

    private static function is_secret_key(string $key): bool {
        $lower = strtolower($key);
        foreach (self::SECRET_KEYS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private static function env_value_class(string $key, string $val): string {
        $lower = strtolower($val);
        if (in_array($lower, ['true', '1', 'yes', 'on', 'development', 'local'], true)) {
            return 'ok';
        }
        if (str_starts_with($val, 'http')) {
            return 'blue';
        }
        return '';
    }

    private static function superglobal_section(string $label, array $data): array {
        $items = [];
        foreach ($data as $k => $v) {
            $items[] = [
                'key'   => (string)$k,
                'value' => is_array($v) ? json_encode($v) : (string)$v,
                'class' => '',
            ];
        }
        return [
            'title'      => $label,
            'items'      => $items,
            'empty_note' => 'no ' . mb_strtolower($label) . ' params',
        ];
    }

    /**
     * Applies basic PHP syntax highlighting to a single source line. #AI:highlight_php
     *
     * Single-pass tokenizer that walks the line character by character,
     * emitting HTML-escaped output with <span> wrappers for comments,
     * strings, variables, numbers, and keywords. Avoids the multi-regex
     * trap where one span's content gets re-matched by a later regex.
     *
     * @param string $code Raw source code line (not HTML-escaped).
     */
    private static function highlight_php(string $code): string {
        if (trim($code) === '') {
            return '';
        }

        static $kw_set = null;
        if ($kw_set === null) {
            $kw_set = [
                'abstract', 'and', 'as', 'break', 'callable', 'case', 'catch', 'class',
                'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else',
                'elseif', 'enum', 'extends', 'final', 'finally', 'fn', 'for', 'foreach',
                'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
                'instanceof', 'interface', 'match', 'namespace', 'new', 'or', 'private',
                'protected', 'public', 'readonly', 'require', 'require_once', 'return',
                'static', 'switch', 'throw', 'trait', 'try', 'use', 'var', 'while', 'xor',
                'yield', 'int', 'string', 'float', 'bool', 'array', 'void', 'never',
                'null', 'true', 'false', 'self', 'parent',
            ];
        }

        $out  = '';
        $len  = strlen($code);
        $i    = 0;

        while ($i < $len) {
            $c = $code[$i];

            if ($c === '/' && $i + 1 < $len && $code[$i + 1] === '/') {
                $rest = substr($code, $i);
                $out .= '<span class="com">' . htmlspecialchars($rest, ENT_QUOTES, 'UTF-8') . '</span>';
                break;
            }

            if ($c === '\'' || $c === '"') {
                $quote = $c;
                $j     = $i + 1;
                while ($j < $len && $code[$j] !== $quote) {
                    if ($code[$j] === '\\' && $j + 1 < $len) {
                        $j += 2;
                        continue;
                    }
                    $j++;
                }
                $lit = substr($code, $i, $j - $i + 1);
                $out .= '<span class="str">' . htmlspecialchars($lit, ENT_QUOTES, 'UTF-8') . '</span>';
                $i = $j + 1;
                continue;
            }

            if ($c === '$' && $i + 1 < $len && (ctype_alpha($code[$i + 1]) || $code[$i + 1] === '_')) {
                $j = $i + 1;
                while ($j < $len && (ctype_alnum($code[$j]) || $code[$j] === '_')) {
                    $j++;
                }
                $var = substr($code, $i, $j - $i);
                $out .= '<span class="var">' . htmlspecialchars($var, ENT_QUOTES, 'UTF-8') . '</span>';
                $i = $j;
                continue;
            }

            if (ctype_digit($c)) {
                $j = $i;
                while ($j < $len && (ctype_digit($code[$j]) || $code[$j] === '.')) {
                    $j++;
                }
                $num = substr($code, $i, $j - $i);
                $out .= '<span class="num">' . htmlspecialchars($num, ENT_QUOTES, 'UTF-8') . '</span>';
                $i = $j;
                continue;
            }

            if (ctype_alpha($c) || $c === '_') {
                $j = $i;
                while ($j < $len && (ctype_alnum($code[$j]) || $code[$j] === '_')) {
                    $j++;
                }
                $word = substr($code, $i, $j - $i);
                $lower = strtolower($word);
                if (in_array($lower, $kw_set, true)) {
                    $out .= '<span class="kw">' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                else {
                    $out .= htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
                }
                $i = $j;
                continue;
            }

            $out .= htmlspecialchars($c, ENT_QUOTES, 'UTF-8');
            $i++;
        }

        return $out;
    }
}

#AI:class
#AI symbol: skim\dev\error_page
#AI source_path: src/dev/error_page.php
#AI title: error_page
#AI description: Developer-friendly HTML error page with code context, stack trace, env/request panels, and solution suggestions.
#AI role: debug error renderer
#AI layer: dev
#AI badges: [dev; debug; error-page; html; templates]
#AI intro: `error_page` renders a styled HTML error page when APP_DEBUG=true. It delegates rendering to dev_view templates with shared CSS from dev_theme, falling back to minimal inline HTML if templates fail. Shows exception details, syntax-highlighted code context, parsed stack trace with noise filtering, environment variables with secret masking, request info, and method-name suggestions for undefined method errors.
#AI lifecycle: called by exception handler registered in app::run(), outputs directly to stdout
#AI fallback: fallback() renders minimal inline HTML when template system fails
#AI test_seam: call render() directly with a test Throwable
#AI invariants: [only called when APP_DEBUG=true; outputs HTTP 500 status; all user-facing output is HTML-escaped; fallback always renders even when templates break]
#AI core_behaviors: [Sets HTTP 500 status code; Collects exception details, code context, stack frames, env, request data; Suggests similar method names via reflection + Levenshtein for undefined method errors; Masks secret env vars with click-to-reveal; Marks framework/vendor frames as noise for trace filtering; Applies basic PHP syntax highlighting to code context]
#AI owns: none — stateless
#AI entry_points: [render]
#AI config_reads: []
#AI non_goals: [Does not log errors; Does not handle production error pages; Does not format JSON error responses; Does not persist error data]
#AI side_effects: [sets HTTP response code to 500; writes HTML to stdout]
#AI flow: render(Throwable) → collect() → dev_view::render() → stdout; on failure → fallback()
#AI lifecycle_steps: [render($e); → http_response_code(500); → collect($e); → dev_view::render('error_page', $data); → echo HTML; on Throwable → fallback($e)]
#AI section_order: [Rendering; Data Collection; Helpers; Architecture]
#AI architectural_notes: Uses dev_view (standalone template engine) and dev_theme (shared CSS) for rendering. The fallback() method ensures error visibility even when the template system itself throws.

#AI:render
#AI group: Rendering
#AI frequency: low
#AI signature: public static function render(\Throwable $e): void
#AI contract: Renders a full HTML error page to stdout using dev_view templates. Falls back to minimal inline HTML if template rendering fails.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception to render.}]
#AI side_effects: [sets HTTP response code to 500; writes HTML to stdout]

#AI:collect
#AI group: Data Collection
#AI frequency: internal
#AI signature: private static function collect(\Throwable $e): array
#AI contract: Extracts all data needed by the error page template from the exception and runtime environment.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception to collect data from.}]
#AI return_detail: {type: array | desc: Associative array with class, message, file, line, code_lines, frames, env_server, env_vars, request, solutions, trace_text.}

#AI:code_context
#AI group: Data Collection
#AI frequency: internal
#AI signature: private static function code_context(string $file, int $line, int $context = 10): array
#AI contract: Reads ±N lines around the error line with basic PHP syntax highlighting. Returns pre-formatted line data for the code_box component.
#AI param_details: [{name: $file | type: string | required: true | desc: Absolute path to the source file.}; {name: $line | type: int | required: true | desc: Error line number (1-based).}; {name: $context | type: int | required: false | desc: Number of lines above and below the error line.}]
#AI return_detail: {type: array | desc: Array of [num => int, code => string (HTML), hl => bool].}

#AI:parse_frames
#AI group: Data Collection
#AI frequency: internal
#AI signature: private static function parse_frames(\Throwable $e): array
#AI contract: Parses the exception trace into structured frame data with file, line, class, function, noise flag, and argument info.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception whose trace to parse.}]
#AI return_detail: {type: array | desc: Array of frame arrays with idx, file, line, class, function, call, noise, search, args.}

#AI:suggest_solutions
#AI group: Data Collection
#AI frequency: internal
#AI signature: private static function suggest_solutions(\Throwable $e): array
#AI contract: Analyzes the exception to suggest fixes. For undefined method errors, uses reflection + Levenshtein distance to find similar method names.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception to analyze.}]
#AI return_detail: {type: array | desc: Array of solution arrays with type, title, body, similar, methods.}

#AI:fallback
#AI group: Rendering
#AI frequency: low
#AI signature: private static function fallback(\Throwable $e): void
#AI contract: Renders minimal inline HTML error page when template rendering fails. Ensures the developer always sees the error.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception to render in fallback mode.}]
#AI side_effects: [writes HTML to stdout]

#AI:highlight_php
#AI group: Helpers
#AI frequency: internal
#AI signature: private static function highlight_php(string $code): string
#AI contract: Applies basic PHP syntax highlighting to a single source line using regex-based token coloring.
#AI param_details: [{name: $code | type: string | required: true | desc: Raw source code line (not HTML-escaped).}]
#AI return_detail: {type: string | desc: HTML string with <span> wrappers using syntax color classes.}
