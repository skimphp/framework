<?php declare(strict_types=1);

namespace skim\core;

use skim\dev\profiler;
use skim\dev\request_trace;
use skim\ext\extension_manager;

/**
 * Singleton container and HTTP kernel: scoped key-value store, DI container, and middleware pipeline.
 *
 * Use as the application entry point (`app::instance()`) or test harness (`app::test_instance()`).
 * Three scopes isolate data: `sys.*` (framework, immutable after boot), `app.*` (config, frozen after boot),
 * `user.*` (per-request, mutable). The DI container resolves bindings as singletons with auto-wiring fallback.
 *
 * Example:
 *   $app = app::instance();
 *   $app->router->get('/', [home_controller::class, 'index']);
 *   $app->run();
 *
 * Testing: Use `app::test_instance(['db.driver' => 'memory'])` for isolated containers without env/config loading.
 *
 * #AI:class
 */
class app {
    // Process-wide singleton; null until first instance() call triggers boot.
    private static ?self $instance = null;

    // DI bindings: abstract → factory callable, resolved lazily on first use.
    private array $bindings = [];

    // Binding priority: higher priority wins service replacement conflicts.
    private array $binding_priorities = [];

    // Decoration chain: abstract → list of decorators sorted by priority.
    private array $decorators = [];

    // Monotonic counter ensures stable insertion order for decorator sorting.
    private int $decorator_order = 0;

    // Resolved singletons cache: abstract → instance (process-lifetime).
    private array $resolved = [];

    // SYS scope: framework internals, immutable after boot.
    private array $sys = [];

    // APP scope: config values from config/*.php, read-only at runtime.
    private array $app_data = [];

    // USER scope: per-request mutable state, reset on each handle().
    private array $user = [];

    // Route registration and dispatch; public read, private write.
    public private(set) router $router;
    // Middleware execution chain built during boot.
    private pipeline   $pipeline;
    // Middleware classes applied to every request before route-specific ones.
    private array      $global_middleware = [];
    // Mutation guard: blocks router and scope writes after freeze().
    private bool       $frozen = false;

    // Active extension context: {name, priority} during DI resolution.
    private ?array $extension_context = null;
    // Discovers, registers, and boots extensions in priority order.
    private ?extension_manager $extension_manager = null;
    // Idempotent boot guard: prevents extension re-boot on repeated calls.
    private bool $extensions_booted = false;
    // Idempotent boot guard: prevents re-running boot() on repeated calls.
    private bool $booted = false;

    /**
     * Private constructor enforces singleton access via instance(). #AI:__construct
     *
     * @param string $root Project root directory used for .env and config resolution.
     */
    private function __construct(private readonly string $root) {
        $this->router   = new router();
        $this->pipeline = new pipeline();
    }

    /**
     * Resets resolved singletons and user scope on clone. #AI:__clone
     *
     * Ensures cloned instances do not share cached services or per-request state
     * with the original.
     */
    public function __clone() {
        $this->resolved = [];
        $this->user = [];
        $this->pipeline = new pipeline();
    }

    /**
     * Returns the process-wide singleton, creating it on first call. #AI:instance
     *
     * Initializes router and pipeline immediately so routes can be registered
     * before `boot()` is called. Full boot (extensions, view layout) is deferred
     * to `run()` or `dispatch()` via `ensureBooted()`.
     *
     * @return static The application instance with router ready for route registration.
     */
    public static function instance(): static {
        if (self::$instance === null) {
            $root = defined('SKIM_ROOT') ? SKIM_ROOT : dirname(__DIR__, 2);
            self::$instance = new static($root);
            self::$instance->router->set_mutation_guard(fn(): bool => !self::$instance->frozen);
            self::$instance->set('sys.router', self::$instance->router);
        }
        return self::$instance;
    }

    /**
     * Returns a fresh isolated container for tests (testing only). #AI:test_instance
     *
     * Skips .env and config/*.php loading. Inject config directly via the `$config`
     * parameter. Never shares state with `app::instance()`.
     *
     * Example:
     *   $app = app::test_instance(['db.driver' => 'memory', 'app.debug' => true]);
     *   $app->router->get('/test', fn() => 'ok');
     *   $res = $app->dispatch(request::make('GET', '/test'), new response());
     *
     * @param array $config Key-value pairs injected into app scope (e.g. `['db.driver' => 'memory']`).
     * @return static A fresh, unbooted container with router and pipeline ready.
     */
    public static function test_instance(array $config = []): static {
        $inst = new static(dirname(__DIR__, 2));
        foreach ($config as $key => $val) {
            $inst->app_data[$key] = $val;
        }
        $inst->router->set_mutation_guard(fn(): bool => !$inst->frozen);
        $inst->set('sys.router', $inst->router);
        return $inst;
    }

    /**
     * Boots framework subsystems in dependency order. #AI:boot
     *
     * Idempotent — subsequent calls after the first are no-ops.
     * Sequence: view layout → router → pipeline → extension discovery and registration.
     * Profiler and request_trace are NOT enabled here; they are enabled in `run()`.
     * env and config are lazy-loaded on first access via get().
     */
    public function boot(): void {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        if ($layout = config::get('app.view.default_layout')) {
            \skim\view\view::set_default_layout((string) $layout);
        }

        $this->extension_manager = extension_manager::discover($this->root, $this);
        $this->extension_manager->register($this);
    }

    /**
     * Emits a 'route_matched' request_trace event with the matched pattern, params,
     * and middleware stack. Read by the error page's Request panel and the toolbar.
     *
     * No-op when request_trace is disabled — never throws.
     */
    private function record_route_trace(array $route): void {
        if (!\skim\dev\request_trace::is_enabled()) {
            return;
        }

        \skim\dev\request_trace::event('route_matched', [
            'pattern'    => (string) ($route['pattern'] ?? ''),
            'params'     => $route['params'] ?? [],
            'middleware' => array_values(array_map(
                static fn(string|array|middleware $entry): string
                    => match (true) {
                        $entry instanceof middleware => $entry::class,
                        is_string($entry)            => $entry,
                        default                      => (string) ($entry['class'] ?? '?'),
                    },
                $route['middleware'] ?? [],
            )),
        ]);
    }

    /**
     * Calls boot() if not yet booted. #AI:ensureBooted
     *
     * Called from run() and dispatch() to guarantee the framework is initialised
     * before any request handling occurs.
     */
    private function ensureBooted(): void {
        if (!$this->booted) {
            $this->boot();
        }
    }

    /**
     * Stores a value in the scope determined by key prefix. #AI:set
     *
     * Routes to `sys`, `app`, or `user` scope based on prefix. Keys without a
     * recognised prefix land in user scope. In production, `sys.*` keys are
     * write-once and throw on duplicate writes.
     *
     * @param string $key   Scoped key: `sys.*`, `app.*`, `user.*`, or bare (→ user scope).
     * @param mixed  $value Value to store.
     * @throws \LogicException If a `sys.*` key is overwritten in production (non-debug).
     */
    public function set(string $key, mixed $value): void {
        if (str_starts_with($key, 'sys.')) {
            $k = substr($key, 4);
            if (isset($this->sys[$k]) && config::get('app.debug') === false) {
                throw new \LogicException("SYS scope key '{$k}' is immutable after boot.");
            }
            $this->sys[$k] = $value;
        } elseif (str_starts_with($key, 'app.')) {
            $this->app_data[substr($key, 4)] = $value;
        } else {
            $prefix = str_starts_with($key, 'user.') ? substr($key, 5) : $key;
            $this->user[$prefix] = $value;
        }
    }

    /**
     * Reads a value from the scope matching the key prefix. #AI:get
     *
     * Falls back to `config::get("app.{$k}")` for `app.*` keys not set directly.
     * Never throws — returns `$default` for missing keys.
     *
     * @param string $key     Scoped key to read.
     * @param mixed  $default Returned when the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed {
        if (str_starts_with($key, 'sys.')) {
            return $this->sys[substr($key, 4)] ?? $default;
        }
        if (str_starts_with($key, 'app.')) {
            $k = substr($key, 4);
            return $this->app_data[$k] ?? config::get("app.{$k}", $default);
        }
        if (str_starts_with($key, 'user.')) {
            return $this->user[substr($key, 5)] ?? $default;
        }
        return $this->user[$key] ?? $default;
    }

    /**
     * Registers a factory callable for DI resolution. #AI:bind
     *
     * Clears the resolved singleton cache for `$abstract` so the new factory takes
     * effect on the next `make()` call. Higher-priority bindings win conflicts;
     * lower-priority calls are silently ignored.
     *
     * @param string         $abstract Abstract type or identifier to bind.
     * @param callable|string $factory  Callable receiving `app`, or class name for auto-wiring.
     * @param int|null       $priority Binding priority (higher wins). Null uses current extension priority.
     * @throws \LogicException If called after `freeze()`.
     */
    public function bind(string $abstract, callable|string $factory, ?int $priority = null): void {
        $this->assert_mutable('bind services');

        $priority ??= $this->current_extension_priority();
        $current_priority = $this->binding_priorities[$abstract] ?? PHP_INT_MIN;

        if (isset($this->bindings[$abstract]) && $priority < $current_priority) {
            return;
        }

        $this->bindings[$abstract] = $this->normalize_factory($factory);
        $this->binding_priorities[$abstract] = $priority;
        unset($this->resolved[$abstract]);
    }

    /**
     * Wraps a resolved service with a decorator in priority order. #AI:decorate
     *
     * Decorators run after the factory produces the service, in ascending priority
     * then registration order. Clears the resolved cache so the next `make()` call
     * applies the full decoration chain.
     *
     * @param string   $abstract  Abstract type to decorate.
     * @param callable $decorator Receives `($service, $app)`, returns decorated service.
     * @param int|null $priority  Decoration priority. Null uses current extension priority.
     * @throws \LogicException If called after `freeze()`.
     */
    public function decorate(string $abstract, callable $decorator, ?int $priority = null): void {
        $this->assert_mutable('decorate services');

        $this->decorators[$abstract][] = [
            'factory'  => $decorator,
            'priority' => $priority ?? $this->current_extension_priority(),
            'order'    => ++$this->decorator_order,
        ];

        unset($this->resolved[$abstract]);
    }

    /**
     * Resolves an abstract to a singleton instance. #AI:make
     *
     * Returns the cached singleton if already resolved. Otherwise invokes the
     * registered factory, or falls back to reflection auto-wiring when no binding
     * exists but the class is loadable. Applies decorators in priority order.
     *
     * Example:
     *   $app->bind(mailer::class, fn($app) => new smtp_mailer($app->get('app.mail')));
     *   $mailer = $app->make(mailer::class); // singleton from here on
     *
     * @param string $abstract Class name or identifier to resolve.
     * @return mixed The resolved (and possibly decorated) singleton instance.
     * @throws \RuntimeException If no binding exists and the class cannot be auto-wired.
     */
    public function make(string $abstract): mixed {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolved[$abstract] = $this->apply_decorators(
                $abstract,
                ($this->bindings[$abstract])($this),
            );
        }

        // auto-wire via reflection
        if (class_exists($abstract)) {
            return $this->resolved[$abstract] = $this->apply_decorators(
                $abstract,
                $this->build($abstract),
            );
        }

        throw new \RuntimeException("No binding registered for '{$abstract}'");
    }

    /**
     * Registers global middleware applied to every HTTP request. #AI:use
     *
     * Middleware runs in registration order before the route handler. Must be
     * called before `run()` or `freeze()`.
     *
     * @param string $class Middleware class name implementing the middleware interface.
     * @param mixed  $args  Constructor arguments passed to the middleware.
     * @throws \LogicException If called after `freeze()`.
     */
    public function use(string $class, mixed ...$args): void {
        $this->assert_mutable('register middleware');
        $this->global_middleware[] = ['class' => $class, 'args' => $args];
    }

    /**
     * Freezes all mutation points: bindings, decorators, middleware, and routes. #AI:freeze
     *
     * Called automatically by `run()` before dispatch. After freezing, any call
     * to `bind()`, `decorate()`, `use()`, or route registration throws.
     *
     * WARNING: Irreversible for this instance. No `thaw()` exists.
     *
     * @throws void Never throws; sets internal frozen flag unconditionally.
     */
    public function freeze(): void {
        $this->frozen = true;
    }

    /**
     * Returns true after mutation points have been frozen. #AI:is_frozen
     *
     * @return bool True if `freeze()` has been called.
     */
    public function is_frozen(): bool {
        return $this->frozen;
    }

    /**
     * Runs a callback with a temporary extension context for priority resolution. #AI:with_extension_context
     *
     * Sets the active extension name and priority so that `bind()`, `decorate()`,
     * and similar calls inside `$callback` inherit the correct priority. Restores
     * the previous context even if the callback throws.
     *
     * Example:
     *   $app->with_extension_context('auth', 50, function() use ($app) {
     *       $app->bind(auth_service::class, fn() => new jwt_auth());
     *   });
     *
     * @param string   $name     Extension identifier for diagnostics.
     * @param int      $priority Priority applied to registrations inside the callback.
     * @param callable $callback Executed with the extension context active.
     * @return mixed Whatever the callback returns.
     */
    public function with_extension_context(string $name, int $priority, callable $callback): mixed {
        $previous = $this->extension_context;
        $this->extension_context = ['name' => $name, 'priority' => $priority];

        try {
            return $callback();
        } finally {
            $this->extension_context = $previous;
        }
    }

    /**
     * Dispatches the HTTP request through middleware and sends the response. #AI:run
     *
     * Calls `ensureBooted()`, enables profiler and request_trace (if debug),
     * installs a global exception handler (error_page in debug, 500 in production),
     * boots extensions, freezes the app, builds the request from globals, dispatches
     * through the middleware pipeline, records request traces, and sends the response.
     * Called once per request from `public/index.php`.
     *
     * WARNING: Not re-entrant. Installs a global exception handler that persists
     * for the process lifetime.
     *
     * Example:
     *   // public/index.php
     *   require __DIR__ . '/../vendor/autoload.php';
     *   app::instance()->run();
     */
    public function run(): void {
        $this->ensureBooted();

        $is_debug = (bool) config::get('app.debug', false);

        if ($is_debug) {
            profiler::enable();
            request_trace::enable();
        }

        set_exception_handler(function(\Throwable $e) use ($is_debug): void {
            if ($is_debug) {
                \skim\dev\error_page::render($e);
            } else {
                http_response_code(500);
                echo 'Internal Server Error';
            }
        });

        $this->boot_extensions();
        $this->freeze();

        $req = request::from_globals();
        $res = new response();

        if (request_trace::is_enabled()) {
            request_trace::start(
                bin2hex(random_bytes(8)),
                $req->method(),
                $req->path(),
            );
            request_trace::set_extensions(
                array_column($this->get('sys.extensions', []), 'name')
            );
        }

        $result = $this->dispatch($req, $res);

        if (request_trace::is_enabled()) {
            $trace = request_trace::finish($result->get_status());
            $this->sys['last_trace'] = $trace;
        } elseif ($result->get_status() >= 500) {
            error_log('[skim][request] ' . $req->method() . ' ' . $req->path() . ' ' . $result->get_status());
        }

        $result->send();
    }

    /**
     * Dispatches a request through the router and middleware pipeline. #AI:dispatch
     *
     * Returns the response without sending headers or body — suitable for tests
     * and embedded runtimes. Does not install exception handlers. Temporarily sets
     * this instance as the global singleton during dispatch and restores the
     * previous instance in a finally block.
     *
     * Example:
     *   $req = request::make('GET', '/users/42');
     *   $res = $app->dispatch($req, new response());
     *   assert($res->get_status() === 200);
     *
     * @param request  $req            The request to dispatch.
     * @param response $res            The response object to populate.
     * @param bool     $skip_middleware When true, bypasses all middleware (useful for tests).
     * @return response The populated response (404 if no route, 405 if method not allowed).
     */
    public function dispatch(request $req, response $res, bool $skip_middleware = false): response {
        $this->ensureBooted();

        $previous = self::$instance;
        self::$instance = $this;

        try {
            $route = $this->router->dispatch($req->method(), $req->path());

            if ($route === null) {
                return $res->status(404)->json(['error' => 'Not Found']);
            }

            if ($route === false) {
                return $res->status(405)->json(['error' => 'Method Not Allowed']);
            }

            if (!empty($route['params'])) {
                $req->set_route_params($route['params']);
            }

            $this->record_route_trace($route);

            $middlewares = $skip_middleware
                ? []
                : array_merge($this->global_middleware, $route['middleware'] ?? []);

            $handler = $route['handler'];
            $result = $this->pipeline->run($req, $res, $middlewares, function(request $req, response $res) use ($handler, $route): mixed {
                if (is_array($handler)) {
                    request_trace::event('controller_called', ['class' => $handler[0], 'method' => $handler[1]]);
                }
                return $this->call_handler($handler, $req, $res, $route['params'] ?? []);
            });

            if ($result instanceof response) {
                return $result;
            }

            if ($result !== null) {
                return $res->json($result);
            }

            return $res;
        } finally {
            self::$instance = $previous;
        }
    }

    /**
     * Resolves controller handler arguments via DI and route params. #AI:call_handler
     *
     * For closures, passes request/response and route params directly. For class-based
     * handlers, resolves the controller via `make()` and injects constructor/method
     * dependencies by type-hint, matching route params by name.
     */
    private function call_handler(array|callable $handler, request $req, response $res, array $params): mixed {
        if (is_callable($handler) && !is_array($handler)) {
            return $handler($req, $res, ...$params);
        }

        [$class, $method] = $handler;
        $controller = $this->make($class);

        $ref    = new \ReflectionMethod($controller, $method);
        $args   = [];

		foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $name = $param->getName();

			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                $args[] = match ($typeName) {
                    request::class  => $req,
                    response::class => $res,
                    default         => $this->make($typeName),
                };

            } elseif (array_key_exists($name, $params)) {
                $args[] = $params[$name];
            }
			elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
			else {
                $args[] = null;
            }
        }

        return $controller->$method(...$args);
    }

    /**
     * Aggregates capability declarations from all loaded extensions. #AI:capabilities
     *
     * Returns a flat map keyed by capability name. Each value includes `provided_by`
     * (extension name) and any extension-declared details. First provider wins for
     * duplicate capability names.
     *
     * @return array<string, array{provided_by: string}> Capability map.
     */
    public function capabilities(): array {
        $caps = [];
        foreach ($this->get('sys.extensions', []) as $ext) {
            $details = $ext['capability_details'] ?? [];
            foreach ($details as $cap => $info) {
                if (!isset($caps[$cap])) {
                    $caps[$cap] = array_merge(['provided_by' => $ext['name']], (array) $info);
                }
            }
            foreach ($ext['capabilities'] as $cap) {
                if (!isset($caps[$cap])) {
                    $caps[$cap] = ['provided_by' => $ext['name']];
                }
            }
        }
        return $caps;
    }

    /**
     * Boots extensions once via the extension manager. #AI:boot_extensions
     *
     * Idempotent — subsequent calls after the first are no-ops.
     */
    private function boot_extensions(): void {
        if ($this->extensions_booted) {
            return;
        }

        $this->extension_manager?->boot($this);
        $this->extensions_booted = true;
    }

    /**
     * Converts a string class name to an auto-wiring factory closure. #AI:normalize_factory
     *
     * @param callable|string $factory Class name or callable.
     * @return callable Always returns a callable accepting `app`.
     */
    private function normalize_factory(callable|string $factory): callable {
        if (is_string($factory)) {
            return fn(self $app): mixed => $app->build($factory);
        }

        return $factory;
    }

    /**
     * Instantiates a class via reflection auto-wiring. #AI:build
     *
     * Resolves constructor dependencies recursively through `make()`. Uses default
     * parameter values when no binding exists for a scalar param.
     *
     * @param string $abstract Fully-qualified class name to instantiate.
     * @throws \RuntimeException If a constructor parameter has no binding and no default.
     */
    private function build(string $abstract): mixed {
        $ref  = new \ReflectionClass($abstract);
        $ctor = $ref->getConstructor();

        if ($ctor === null) {
            return $ref->newInstance();
        }

        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $deps[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException("Cannot auto-wire '{$abstract}': no binding for '{$param->getName()}'");
            }
        }

        return $ref->newInstanceArgs($deps);
    }

    /**
     * Applies registered decorators to a resolved service in priority then registration order. #AI:apply_decorators
     *
     * Returns the service unchanged when no decorators are registered for the abstract.
     */
    private function apply_decorators(string $abstract, mixed $service): mixed {
        if (!isset($this->decorators[$abstract])) {
            return $service;
        }

        $decorators = $this->decorators[$abstract];
        usort($decorators, static fn(array $a, array $b): int => [$a['priority'], $a['order']] <=> [$b['priority'], $b['order']]);

        foreach ($decorators as $decorator) {
            $service = ($decorator['factory'])($service, $this);
        }

        return $service;
    }

    /**
     * Returns the priority from the active extension context, defaulting to 100. #AI:current_extension_priority
     */
    private function current_extension_priority(): int {
        return (int) ($this->extension_context['priority'] ?? 100);
    }

    /**
     * Throws if the app is frozen, preventing post-boot mutation. #AI:assert_mutable
     *
     * @param string $action Human-readable action name for the error message.
     * @throws \LogicException If the app is frozen.
     */
    private function assert_mutable(string $action): void {
        if ($this->frozen) {
            $extension = $this->extension_context['name'] ?? 'unknown';
            error_log("[skim][invariant-violation] extension='{$extension}' attempted '{$action}' after freeze");
            throw new \LogicException("Cannot {$action} after app is frozen.");
        }
    }
}

#AI:class
#AI symbol: skim\core\app
#AI source_path: src/core/app.php
#AI title: app
#AI description: Singleton container and HTTP kernel combining a scoped key-value store, DI container with auto-wiring, and middleware pipeline.
#AI role: application kernel and service container
#AI layer: core
#AI badges: [singleton; container; kernel; di; middleware; scoped-store]
#AI intro: `skim\core\app` is the central application kernel. It combines three responsibilities in one singleton: a scoped key-value store (sys/app/user), a dependency injection container with factory bindings and reflection auto-wiring, and an HTTP kernel with a middleware pipeline. Extensions register services and middleware through `with_extension_context()` during the boot phase.
#AI lifecycle: singleton, created on first `instance()` call, booted lazily via `ensureBooted()` in `run()` or `dispatch()`, frozen before request dispatch
#AI fallback: none — app is the root; subsystems fall back to their own defaults
#AI test_seam: test_instance() for isolated containers without env/config loading
#AI invariants: [instance() returns the same object for the process lifetime; sys.* keys are write-once in production; bind/decorate/use throw after freeze(); make() caches singletons until the container is cloned]
#AI core_behaviors: [Three scopes (sys, app, user) isolate framework internals from config and per-request state; DI resolution caches singletons and falls back to reflection auto-wiring; Middleware runs in registration order before route handlers; Extensions register services with priority-based conflict resolution]
#AI warnings: [`run()` installs a global exception handler and is not re-entrant; `freeze()` is irreversible for the instance lifetime; sys.* writes throw LogicException in production after first set]
#AI notes: The constructor is private — always use `instance()` or `test_instance()`. Cloning resets resolved singletons and user scope but preserves bindings and sys/app data.
#AI scope_items: [{name: sys | mutable: false | desc: Framework internals (router, extensions). Write-once in production, mutable in debug.}; {name: app | mutable: true | desc: Config values from config/*.php or test_instance(). Falls back to config::get on read.}; {name: user | mutable: true | desc: Per-request mutable state. Cleared on clone.}]
#AI owns: singleton instance, DI bindings, resolved singletons, scoped store, middleware stack, router, pipeline, extension context
#AI entry_points: [instance; test_instance; run; dispatch]
#AI config_reads: [app.debug; app.view.default_layout; app.*]
#AI non_goals: [Does not handle HTTP transport (delegates to request/response); Does not manage database connections directly; Does not serialize or persist state across requests]
#AI side_effects: [run() installs global exception handler and sends HTTP response; freeze() permanently locks mutation; set() may throw on sys.* overwrite in production; boot() initialises router, pipeline, and extensions once]
#AI flow: app::instance() -> run() -> ensureBooted() -> boot() [router -> extensions] -> profiler/request_trace -> boot_extensions() -> freeze() -> dispatch() -> pipeline -> call_handler() -> response
#AI lifecycle_steps: [app::instance(); -> run(); -> ensureBooted(); -> boot() [view layout + router + pipeline + extension_manager::discover + register]; -> profiler/request_trace enable (if debug); -> boot_extensions(); -> freeze(); -> request::from_globals(); -> dispatch(); -> pipeline::run(); -> call_handler(); -> response::send()]
#AI section_order: [Lifecycle; Scoped Store; DI Container; Middleware; Request Dispatch; Extensions; Testing]
#AI architectural_notes: The app class is intentionally a god object combining container, kernel, and store. This keeps the framework surface area small — one class to learn, one singleton to pass around. Extensions interact with app exclusively through `with_extension_context()` during boot, then the app freezes to prevent further mutation.

#AI:instance
#AI group: Lifecycle
#AI frequency: high
#AI signature: public static function instance(): static
#AI contract: Returns the process-wide singleton. On first call, creates the instance with SKIM_ROOT (or auto-detected root) but does NOT call boot(). Boot is deferred to run() or dispatch() via ensureBooted(). Subsequent calls return the cached instance.
#AI return_detail: {type: static | desc: The application instance (not yet booted).}
#AI side_effects: [Creates the singleton on first call; does not trigger boot]

#AI:test_instance
#AI group: Testing
#AI frequency: high
#AI signature: public static function test_instance(array $config = []): static
#AI contract: Creates a fresh isolated container that skips .env and config/*.php loading. Config values are injected directly via the $config array. The returned instance has its own router, pipeline, and mutation guard but shares no state with app::instance().
#AI param_details: [{name: $config | type: array | required: false | desc: Key-value pairs injected into app scope. Keys use dot notation without the app. prefix (e.g. 'db.driver' => 'memory').}]
#AI return_detail: {type: static | desc: A fresh, unbooted container with router and pipeline ready.}
#AI notes: Safe to call multiple times per test. Each call returns an independent instance.

#AI:boot
#AI group: Lifecycle
#AI frequency: medium
#AI signature: public function boot(): void
#AI contract: Initializes framework subsystems in strict dependency order: view layout → router → pipeline → extension discovery and registration. Idempotent — subsequent calls after the first are no-ops. Profiler and request_trace are NOT enabled here; they are enabled in run(). env and config are lazy-loaded on first access.
#AI side_effects: [Sets $booted = true; initialises router, pipeline, extension_manager; registers extensions]

#AI:ensureBooted
#AI group: Lifecycle
#AI frequency: internal
#AI signature: private function ensureBooted(): void
#AI contract: Calls boot() if not yet booted. Called from run() and dispatch() to guarantee the framework is initialised before any request handling occurs.

#AI:set
#AI group: Scoped Store
#AI frequency: high
#AI signature: public function set(string $key, mixed $value): void
#AI contract: Stores a value in the scope determined by the key prefix. Keys prefixed `sys.` go to the immutable-after-boot sys scope, `app.` to config scope, `user.` or bare keys to per-request scope. In production (non-debug), sys.* keys throw LogicException on duplicate writes.
#AI param_details: [{name: $key | type: string | required: true | desc: Scoped key. Prefix determines target scope: sys.*, app.*, user.*, or bare (→ user).}; {name: $value | type: mixed | required: true | desc: Value to store.}]
#AI throws_details: [{type: \LogicException | desc: When overwriting a sys.* key in production (app.debug === false).}]
#AI side_effects: [Mutates the target scope array]

#AI:get
#AI group: Scoped Store
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Reads from the scope matching the key prefix. For app.* keys, falls back to config::get("app.{$k}") when the key is not set directly. Never throws — returns $default for missing keys.
#AI param_details: [{name: $key | type: string | required: true | desc: Scoped key to read. Prefix determines source scope.}; {name: $default | type: mixed | required: false | desc: Returned when the key is absent from the target scope.}]
#AI return_detail: {type: mixed | desc: The stored value, config fallback for app.*, or $default.}

#AI:bind
#AI group: DI Container
#AI frequency: high
#AI signature: public function bind(string $abstract, callable|string $factory, ?int $priority = null): void
#AI contract: Registers a factory callable for DI resolution. Clears the resolved singleton cache for the abstract so the new factory takes effect on the next make() call. Higher-priority bindings replace lower ones; calls with lower priority than the current binding are silently ignored.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type or identifier to bind. Typically a fully-qualified class name.}; {name: $factory | type: callable|string | required: true | desc: Callable receiving the app instance, or a class name string for auto-wiring.}; {name: $priority | type: ?int | required: false | desc: Binding priority (higher wins). Null uses the current extension context priority (default 100).}]
#AI throws_details: [{type: \LogicException | desc: If called after freeze().}]
#AI side_effects: [Clears resolved singleton cache for $abstract; Mutates bindings and binding_priorities arrays]

#AI:decorate
#AI group: DI Container
#AI frequency: medium
#AI signature: public function decorate(string $abstract, callable $decorator, ?int $priority = null): void
#AI contract: Wraps a resolved service with a decorator factory. Decorators are applied in ascending priority order, then by registration order for equal priorities. Clears the resolved cache so the next make() call applies the full decoration chain.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type whose resolved instances should be decorated.}; {name: $decorator | type: callable | required: true | desc: Receives ($service, $app) and returns the decorated service.}; {name: $priority | type: ?int | required: false | desc: Decoration priority. Null uses the current extension context priority.}]
#AI throws_details: [{type: \LogicException | desc: If called after freeze().}]
#AI side_effects: [Clears resolved singleton cache for $abstract; Appends to decorators array]

#AI:make
#AI group: DI Container
#AI frequency: high
#AI signature: public function make(string $abstract): mixed
#AI contract: Resolves an abstract to a singleton instance. Returns the cached singleton if already resolved. Otherwise invokes the registered factory (or auto-wires via reflection when no binding exists but the class is loadable), applies decorators in priority order, and caches the result.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Class name or identifier to resolve. Must have a binding or be an instantiable class.}]
#AI return_detail: {type: mixed | desc: The resolved (and possibly decorated) singleton instance.}
#AI throws_details: [{type: \RuntimeException | desc: If no binding exists and the class cannot be auto-wired (missing constructor dependency with no binding or default).}]
#AI side_effects: [Caches resolved instance in $resolved array]
#AI examples: [{label: Basic resolution | code: $app->bind(mailer::class, fn($app) => new smtp_mailer($app->get('app.mail')));\n$mailer = $app->make(mailer::class);}]

#AI:use
#AI group: Middleware
#AI frequency: medium
#AI signature: public function use(string $class, mixed ...$args): void
#AI contract: Registers a global middleware class applied to every HTTP request in registration order. Must be called before run() or freeze().
#AI param_details: [{name: $class | type: string | required: true | desc: Middleware class name implementing the middleware interface.}; {name: $args | type: mixed | required: false | desc: Constructor arguments passed to the middleware when instantiated.}]
#AI throws_details: [{type: \LogicException | desc: If called after freeze().}]
#AI side_effects: [Appends to global_middleware stack]

#AI:freeze
#AI group: Middleware
#AI frequency: low
#AI signature: public function freeze(): void
#AI contract: Freezes all mutation points: service bindings, decorators, middleware registration, and route mutations. Called automatically by run() before dispatch. Irreversible for this instance.
#AI warnings: [Irreversible — no thaw() method exists. After freeze, bind(), decorate(), use(), and route registration all throw LogicException.]
#AI side_effects: [Sets internal frozen flag to true]

#AI:is_frozen
#AI group: Middleware
#AI frequency: low
#AI signature: public function is_frozen(): bool
#AI contract: Returns true after freeze() has been called.
#AI return_detail: {type: bool | desc: True if the app mutation points are frozen.}

#AI:with_extension_context
#AI group: Extensions
#AI frequency: medium
#AI signature: public function with_extension_context(string $name, int $priority, callable $callback): mixed
#AI contract: Temporarily sets the active extension name and priority so that bind(), decorate(), and similar calls inside $callback inherit the correct priority. The previous extension context is restored in a finally block, even if the callback throws.
#AI param_details: [{name: $name | type: string | required: true | desc: Extension identifier used for diagnostics and assert_mutable error messages.}; {name: $priority | type: int | required: true | desc: Priority applied to registrations (bind, decorate) inside the callback.}; {name: $callback | type: callable | required: true | desc: Executed with the extension context active. Return value is passed through.}]
#AI return_detail: {type: mixed | desc: Whatever the callback returns.}
#AI side_effects: [Temporarily mutates extension_context; restored in finally block]
#AI examples: [{label: Extension registration | code: $app->with_extension_context('auth', 50, function() use ($app) {\n    $app->bind(auth_service::class, fn() => new jwt_auth());\n});}]

#AI:run
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function run(): void
#AI contract: Executes the full HTTP request cycle: calls ensureBooted(), enables profiler and request_trace (if debug), installs a global exception handler, boots extensions, freezes the app, builds the request from PHP globals, dispatches through the middleware pipeline, records request traces, and sends the response. Called once per request from public/index.php.
#AI warnings: [Not re-entrant; Installs a global exception handler that persists for the process lifetime; In production, 500 errors return a bare 'Internal Server Error' string]
#AI side_effects: [Calls ensureBooted(); Enables profiler and request_trace (if debug); Installs global exception handler; Boots extensions; Freezes the app; Sends HTTP response headers and body; Records request trace or error log]
#AI examples: [{label: Entry point | code: // public/index.php\nrequire __DIR__ . '/../vendor/autoload.php';\napp::instance()->run();}]

#AI:dispatch
#AI group: Request Dispatch
#AI frequency: high
#AI signature: public function dispatch(request $req, response $res, bool $skip_middleware = false): response
#AI contract: Dispatches a request through the router and middleware pipeline without sending headers or body. Calls ensureBooted() first to guarantee the framework is initialised. Returns 404 for unmatched routes, 405 for method mismatches. Temporarily sets this instance as the global singleton during dispatch and restores the previous instance in a finally block.
#AI param_details: [{name: $req | type: request | required: true | desc: The request to dispatch.}; {name: $res | type: response | required: true | desc: The response object to populate.}; {name: $skip_middleware | type: bool | required: false | desc: When true, bypasses all global and route middleware. Useful for unit tests.}]
#AI return_detail: {type: response | desc: The populated response. Status 404 if no route matches, 405 if path matches but method does not.}
#AI side_effects: [Temporarily replaces self::$instance during dispatch]
#AI examples: [{label: Test dispatch | code: $req = request::make('GET', '/users/42');\n$res = $app->dispatch($req, new response(), skip_middleware: true);\nassert($res->get_status() === 200);}]

#AI:call_handler
#AI group: Request Dispatch
#AI frequency: internal
#AI signature: private function call_handler(array|callable $handler, request $req, response $res, array $params): mixed
#AI contract: Resolves controller handler arguments via DI and route params. For closures, passes request/response and route params directly. For class-based handlers, resolves the controller via make() and injects method dependencies by type-hint, matching route params by name.
#AI param_details: [{name: $handler | type: array|callable | required: true | desc: Route handler — either a closure or [class, method] array.}; {name: $req | type: request | required: true | desc: Current request.}; {name: $res | type: response | required: true | desc: Current response.}; {name: $params | type: array | required: true | desc: Route parameters matched by the router.}]

#AI:capabilities
#AI group: Extensions
#AI frequency: low
#AI signature: public function capabilities(): array
#AI contract: Aggregates capability declarations from all loaded extensions into a flat map keyed by capability name. Each value includes `provided_by` (extension name) and any extension-declared details. First provider wins for duplicate capability names.
#AI return_detail: {type: array<string, array{provided_by: string}> | desc: Flat map of capability name to provider details.}
#AI notes: Safe to call before or after freeze — reads sys.extensions which is set during extension discovery.

#AI:boot_extensions
#AI group: Extensions
#AI frequency: internal
#AI signature: private function boot_extensions(): void
#AI contract: Boots extensions once via the extension manager. Idempotent — subsequent calls after the first are no-ops.

#AI:normalize_factory
#AI group: DI Container
#AI frequency: internal
#AI signature: private function normalize_factory(callable|string $factory): callable
#AI contract: Converts a string class name to an auto-wiring factory closure that calls build(). Passes callables through unchanged.
#AI param_details: [{name: $factory | type: callable|string | required: true | desc: Class name string or callable.}]
#AI return_detail: {type: callable | desc: Always a callable accepting an app instance.}

#AI:build
#AI group: DI Container
#AI frequency: internal
#AI signature: private function build(string $abstract): mixed
#AI contract: Instantiates a class via reflection auto-wiring. Resolves constructor dependencies recursively through make(). Uses default parameter values for scalar params without bindings.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Fully-qualified class name to instantiate.}]
#AI throws_details: [{type: \RuntimeException | desc: If a constructor parameter has no binding and no default value.}]

#AI:apply_decorators
#AI group: DI Container
#AI frequency: internal
#AI signature: private function apply_decorators(string $abstract, mixed $service): mixed
#AI contract: Applies registered decorators to a resolved service in ascending priority order, then registration order. Returns the service unchanged when no decorators exist for the abstract.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type used to look up decorators.}; {name: $service | type: mixed | required: true | desc: The resolved service to decorate.}]
#AI return_detail: {type: mixed | desc: The decorated (or original) service.}

#AI:current_extension_priority
#AI group: Extensions
#AI frequency: internal
#AI signature: private function current_extension_priority(): int
#AI contract: Returns the priority from the active extension context set by with_extension_context(). Defaults to 100 when no context is active.

#AI:assert_mutable
#AI group: Lifecycle
#AI frequency: internal
#AI signature: private function assert_mutable(string $action): void
#AI contract: Throws LogicException if the app is frozen, preventing post-boot mutation. Logs the violation with the active extension name for diagnostics.
#AI param_details: [{name: $action | type: string | required: true | desc: Human-readable action name included in the error message and log.}]
#AI throws_details: [{type: \LogicException | desc: If the app is frozen.}]

#AI:__construct
#AI group: Lifecycle
#AI frequency: internal
#AI signature: private function __construct(private readonly string $root)
#AI contract: Private constructor enforces singleton access via instance(). Stores the project root for .env and config path resolution.
#AI param_details: [{name: $root | type: string | required: true | desc: Project root directory.}]

#AI:__clone
#AI group: Testing
#AI frequency: internal
#AI signature: public function __clone()
#AI contract: Resets resolved singletons and user scope on clone. Preserves bindings, decorators, sys/app data, and middleware. Creates a fresh pipeline.
