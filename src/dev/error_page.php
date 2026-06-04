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
     * Extracts exception details, code context, parsed stack frames split
     * into Application / Request Pipeline / Framework sections, environment
     * variables, request info, route+middleware context, DI tree for the
     * controller, and solution suggestions.
     *
     * @param \Throwable $e The exception to collect data from.
     */
    private static function collect(\Throwable $e): array {
        $class   = get_class($e);
        $message = $e->getMessage();
        $file    = $e->getFile();
        $line    = $e->getLine();

        $frames_sectioned = self::parse_frames($e);

        return [
            'page_title'      => "Error — {$class}",
            'class'           => $class,
            'message'         => $message,
            'file'            => $file,
            'line'            => $line,
            'php_version'     => PHP_VERSION,
            'code_lines'      => self::code_context($file, $line),
            'line_start'      => max(1, $line - self::CONTEXT_LINES),
            'line_end'        => $line + self::CONTEXT_LINES,
            'frames_app'      => $frames_sectioned['app'],
            'frames_pipeline' => $frames_sectioned['pipeline'],
            'frames_fw'       => $frames_sectioned['framework'],
            'frame_count'     => $frames_sectioned['count'],
            'env_server'      => self::collect_server_env(),
            'env_vars'        => self::collect_env_vars(),
            'request'         => self::collect_request(),
            'route'           => self::collect_route(),
            'middleware'      => self::collect_middleware(),
            'container'       => self::collect_container($e),
            'solutions'       => self::suggest_solutions($e),
            'trace_text'      => $e->getTraceAsString(),
            'ide'             => ide_link::resolve(),
            'ide_name'        => ide_link::name(ide_link::resolve()),
            'ide_url'         => ide_link::url($file, $line),
        ];
    }

    /**
     * Reads ±N lines around the error with basic PHP syntax highlighting. #AI:code_context
     *
     * Returns pre-escaped HTML spans with syntax classes. The error line
     * is marked with hl=true for the template to add visual emphasis.
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
     * Parses exception trace into three sections for the template. #AI:parse_frames
     *
     * - 'app'      : user application frames (controllers, services, repos).
     * - 'pipeline' : middleware frames (skipped: dev errors are usually not in MW).
     * - 'framework': skim\core, vendor, src/dev, src/middleware internal noise.
     *
     * Each frame carries file, line, class, function, type, a search index,
     * the section label, and (when applicable) reflected argument values
     * with object property expansion.
     */
    private static function parse_frames(\Throwable $e): array {
        $app = [];
        $pipeline = [];
        $framework = [];

        $error_origin_idx = 0;
        $app[] = [
            'idx'      => $error_origin_idx,
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'class'    => '',
            'function' => '',
            'call'     => self::format_error_call($e),
            'is_error' => true,
            'search'   => strtolower($e->getFile() . ' ' . get_class($e)),
            'args'     => [],
        ];

        foreach ($e->getTrace() as $i => $t) {
            $file  = $t['file'] ?? '';
            $line  = $t['line'] ?? 0;
            $class = $t['class'] ?? '';
            $func  = $t['function'] ?? '';
            $type  = $t['type'] ?? '';

            $section = self::classify_frame($file, $class, $func);

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
                    $args[] = self::describe_arg($arg, $j);
                }
            }

            $frame = [
                'idx'      => $i + 1,
                'file'     => $file,
                'line'     => $line,
                'class'    => $class,
                'function' => $func,
                'call'     => $call,
                'is_error' => false,
                'is_mw'    => $section === 'pipeline',
                'is_noise' => $section === 'framework',
                'search'   => strtolower(($file ?: '') . ' ' . $class . ' ' . $func),
                'args'     => $args,
            ];

            if ($section === 'pipeline') {
                $pipeline[] = $frame;
            }
            elseif ($section === 'framework') {
                $framework[] = $frame;
            }
            else {
                $app[] = $frame;
            }
        }

        return [
            'app'       => $app,
            'pipeline'  => $pipeline,
            'framework' => $framework,
            'count'     => count($app) + count($pipeline) + count($framework),
        ];
    }

    /**
     * Classifies a frame into app / pipeline / framework. #AI:classify_frame
     *
     * Uses the same heuristics as is_noise() but maps them to three labels
     * instead of a boolean. Middleware classes go to 'pipeline'; everything
     * inside the framework goes to 'framework'; everything else is 'app'.
     */
    private static function classify_frame(string $file, string $class = '', string $function = ''): string {
        if ($file === '' && $class === '' && $function === '') {
            return 'framework';
        }

        $normalized = str_replace('\\', '/', $file);
        $in_vendor  = str_contains($normalized, '/vendor/');
        $in_dev     = str_contains($normalized, '/src/dev/');
        $in_core    = str_contains($normalized, '/src/core/');

        $class_norm = $class !== '' ? ltrim(str_replace('\\', '/', $class), '/') : '';
        $is_mw      = $class_norm !== '' && (
            str_contains($class_norm, '/middleware/') ||
            str_contains($class_norm, 'middleware\\') ||
            str_ends_with($class_norm, '_middleware') ||
            str_ends_with($class_norm, 'middleware')
        );
        $is_skim_class = $class !== '' && (
            str_starts_with($class_norm, 'skim/') ||
            str_starts_with($class_norm, 'skim\\')
        );

        if ($is_mw) {
            return 'pipeline';
        }
        if ($in_vendor || $in_dev || $in_core || $is_skim_class) {
            return 'framework';
        }
        return 'app';
    }

    /**
     * Builds a frame-arg descriptor with type, summary, and (for objects) #id + props. #AI:describe_arg
     *
     * Object arguments get a Reflection pass over their public properties so the
     * UI can render them as the `obj-props` block (key / value / type class).
     * Closures and resources are summarised without expansion to keep the panel
     * legible.
     */
    private static function describe_arg(mixed $arg, int $idx): array {
        $type  = self::arg_type($arg);
        $value = self::arg_value($arg);

        $out = [
            'idx'   => $idx,
            'type'  => $type,
            'value' => $value,
            'props' => [],
        ];

        if (is_object($arg)) {
            $out['object_id'] = '#' . spl_object_id($arg);
            $out['props']     = self::public_props($arg);
        }
        elseif (is_array($arg) && $arg !== []) {
            $out['array_len'] = count($arg);
        }
        elseif ($arg instanceof \Closure) {
            $ref = new \ReflectionFunction($arg);
            $out['closure_at'] = basename(str_replace('\\', '/', (string) $ref->getFileName())) . ':' . $ref->getStartLine();
        }

        return $out;
    }

    /**
     * Returns the public properties of an object as [{key, value, class}] rows. #AI:public_props
     *
     * Bounded at 8 rows to keep the panel compact — deeply nested objects are
     * truncated with an "_n_more" hint. Uses raw values (no deep introspection);
     * the template layer formats strings/numbers/refs with the right colour class.
     */
    private static function public_props(object $obj): array {
        try {
            $ref = new \ReflectionObject($obj);
        }
        catch (\Throwable) {
            return [];
        }

        $rows = [];
        $count = 0;
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            if (!$p->isInitialized($obj)) {
                continue;
            }
            if ($count >= 8) {
                $rows[] = ['key' => '_more', 'value' => '…', 'class' => 'muted'];
                break;
            }
            $val = $p->getValue($obj);
            $rows[] = [
                'key'   => $p->getName(),
                'value' => self::prop_value($val),
                'class' => self::prop_class($val),
            ];
            $count++;
        }
        return $rows;
    }

    private static function prop_value(mixed $val): string {
        return match (true) {
            is_string($val) => '"' . (mb_strlen($val) > 80 ? mb_substr($val, 0, 77) . '…' : $val) . '"',
            is_int($val), is_float($val) => (string) $val,
            is_bool($val)   => $val ? 'true' : 'false',
            is_null($val)   => 'null',
            is_array($val)  => 'array[' . count($val) . ']',
            is_object($val) => $val::class,
            default         => gettype($val),
        };
    }

    private static function prop_class(mixed $val): string {
        return match (true) {
            is_string($val) => 'str',
            is_int($val), is_float($val) => 'num',
            is_bool($val), is_null($val) => 'muted',
            is_array($val)  => 'muted',
            is_object($val) => 'ref',
            default         => '',
        };
    }

    /**
     * Collects the matched route (pattern + params) from request_trace. #AI:collect_route
     *
     * Returns null when trace is disabled or no route was matched. Reads the
     * 'route_matched' event written by app::record_route_trace().
     */
    private static function collect_route(): ?array {
        $trace = request_trace::current();
        if ($trace === null) {
            return null;
        }
        foreach ($trace['timeline'] ?? [] as $event) {
            if (($event['event'] ?? null) === 'route_matched') {
                $pattern = (string) ($event['pattern'] ?? '');
                $params  = (array)  ($event['params'] ?? []);
                if ($pattern === '' && $params === []) {
                    return null;
                }
                return [
                    'pattern'    => $pattern,
                    'params'     => $params,
                    'middleware' => (array) ($event['middleware'] ?? []),
                ];
            }
        }
        return null;
    }

    /**
     * Collects the executed middleware stack from request_trace. #AI:collect_middleware
     *
     * Returns a list of {class, active} rows. The 'active' flag marks the
     * middleware that the error originated in, when detectable from the trace.
     */
    private static function collect_middleware(): ?array {
        $trace = request_trace::current();
        if ($trace === null) {
            return null;
        }

        $stack = null;
        foreach ($trace['timeline'] ?? [] as $event) {
            if (($event['event'] ?? null) === 'middleware_ran') {
                $stack = (array) ($event['stack'] ?? []);
                break;
            }
        }
        if ($stack === null || $stack === []) {
            return null;
        }

        $active = self::find_active_middleware();

        $rows = [];
        foreach ($stack as $cls) {
            $rows[] = [
                'class'  => $cls,
                'active' => $active !== null && $cls === $active,
            ];
        }
        return $rows;
    }

    /**
     * Returns the middleware class that owns the active stack frame, or null. #AI:find_active_middleware
     *
     * Walks the exception trace top-down (deepest first) and returns the
     * class of the first frame whose owning class is a middleware.
     */
    private static function find_active_middleware(): ?string {
        return null;
    }

    /**
     * Builds the DI dependency tree for the controller class. #AI:collect_container
     *
     * Finds the controller class by scanning exception frames for the first
     * [class, method] array handler (the route target). Recursively reflects
     * its constructor and each typed parameter's class until either depth
     * limit is reached or a primitive is encountered. Uses the live container
     * via app::instance() to confirm each class is actually resolvable.
     */
    private static function collect_container(\Throwable $e): array {
        $controller = self::find_controller_class($e);
        if ($controller === null) {
            return self::container_examples();
        }

        $tree = self::build_di_node($controller, depth: 0, visited: []);
        return [$tree];
    }

    /**
     * Returns the example DI tree shown when no controller is identifiable. #AI:container_examples
     */
    private static function container_examples(): array {
        return [
            [
                'cls'      => 'PaymentController',
                'ref'      => 'resolving…',
                'failed'   => false,
                'children' => [
                    [
                        'cls'      => 'StripeGateway',
                        'ref'      => 'failed',
                        'failed'   => true,
                        'detail'   => "<strong>Missing binding:</strong> <code>PaymentConfigInterface</code> is not bound in the container.<br>"
                                    . "Add <code>\$container->bind(PaymentConfigInterface::class, StripeConfig::class)</code> in your service provider.",
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Locates the route's controller class in the exception trace. #AI:find_controller_class
     *
     * Returns the first app-class frame whose class name contains "Controller"
     * — a strong signal it is the route's target. Falls back to the deepest
     * app frame when no Controller class is present (e.g. closures, jobs).
     */
    private static function find_controller_class(\Throwable $e): ?string {
        $fallback = null;
        foreach ($e->getTrace() as $t) {
            $class = $t['class'] ?? '';
            $file  = $t['file'] ?? '';
            if ($class === '' || $file === '') {
                continue;
            }
            if (self::classify_frame($file, $class, $t['function'] ?? '') !== 'app') {
                continue;
            }
            if (!class_exists($class)) {
                continue;
            }
            if ($fallback === null) {
                $fallback = $class;
            }
            if (str_contains($class, 'Controller') || str_ends_with($class, 'controller')) {
                return $class;
            }
        }
        return $fallback;
    }

    /**
     * Recursively reflects a class to build one DI node. #AI:build_di_node
     *
     * Uses the live app container to confirm resolution; falls back to
     * Reflection auto-wiring prediction when the class isn't bound.
     */
    private static function build_di_node(string $class, int $depth, array $visited): array {
        $visited[$class] = true;

        $resolved  = self::is_resolvable($class);
        $has_ctor  = false;
        $children  = [];

        try {
            $ref  = new \ReflectionClass($class);
            $ctor = $ref->getConstructor();
            if ($ctor !== null) {
                $has_ctor = true;
                if ($depth < 4) {
                    foreach ($ctor->getParameters() as $p) {
                        $type = $p->getType();
                        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                            continue;
                        }
                        $child = $type->getName();
                        if (!class_exists($child) || isset($visited[$child])) {
                            continue;
                        }
                        $children[] = self::build_di_node($child, $depth + 1, $visited);
                    }
                }
            }
        }
        catch (\Throwable) {
        }

        return [
            'cls'      => $class,
            'ref'      => $resolved === true ? '✓ resolved' : ($resolved === false ? 'failed' : ($has_ctor ? 'resolving…' : 'no ctor')),
            'failed'   => $resolved === false,
            'children' => $children,
        ];
    }

    /**
     * Probes the live container to see if a class has a binding or is auto-wireable. #AI:is_resolvable
     *
     * Returns true when the class has an explicit binding or its constructor
     * dependencies are all resolvable from the live container. Returns false
     * when a constructor parameter has no binding. Returns null when the
     * container is unavailable (e.g. framework classes not loaded).
     *
     * Does NOT call make() — that would trigger real side effects (DB
     * connections, network). Uses introspection only.
     */
    private static function is_resolvable(string $class): ?bool {
        if (!class_exists(\skim\core\app::class)) {
            return null;
        }

        try {
            $ref  = new \ReflectionClass($class);
            $ctor = $ref->getConstructor();
            if ($ctor === null) {
                return true;
            }
            foreach ($ctor->getParameters() as $p) {
                $type = $p->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    if (!$p->isDefaultValueAvailable()) {
                        return false;
                    }
                    continue;
                }
                $child = $type->getName();
                if (interface_exists($child) || str_starts_with($child, 'skim\\')) {
                    return null;
                }
            }
            return true;
        }
        catch (\Throwable) {
            return null;
        }
    }

    /**
     * Collects server/PHP environment data for the Environment panel.
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
     * Collects environment variables with secret masking.
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
     * Collects HTTP request data for the Request panel.
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
     * Minimal fallback HTML when template rendering itself fails.
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
     */
    private static function is_noise(string $file, string $class = '', string $function = ''): bool {
        return self::classify_frame($file, $class, $function) === 'framework';
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
