<?php declare(strict_types=1);

namespace Skim\Core;

use Skim\Dev\Profiler;
use Skim\Dev\RequestTrace;
use Skim\Ext\ExtensionManager;

/**
 * Singleton container and HTTP kernel: scoped key-value store, DI container, and middleware pipeline.
 *
 * Use as the application entry point (`App::instance()`) or test harness (`App::testInstance()`).
 * Three scopes isolate data: `sys.*` (framework, immutable after boot), `app.*` (config, frozen after boot),
 * `user.*` (per-request, mutable). The DI container resolves bindings as singletons with auto-wiring fallback.
 *
 * Example:
 *   $app = App::instance();
 *   $app->router->get('/', [HomeController::class, 'index']);
 *   $app->run();
 *
 * Testing: Use `App::testInstance(['db.driver' => 'memory'])` for isolated containers without env/config loading.
 *
 * #AI:class
 */
class App {
    // Process-wide singleton; null until first instance() call triggers boot.
    private static ?self $instance = null;

    // DI bindings: abstract → factory callable, resolved lazily on first use.
    private array $bindings = [];

    // Binding priority: higher priority wins service replacement conflicts.
    private array $bindingPriorities = [];

    // Decoration chain: abstract → list of decorators sorted by priority.
    private array $decorators = [];

    // Monotonic counter ensures stable insertion order for decorator sorting.
    private int $decoratorOrder = 0;

    // Resolved singletons cache: abstract → instance (process-lifetime).
    private array $resolved = [];

    // DI tracing — exposes in-flight resolution chain to error_page.
    // Populated by make() when $tracing is on; read by ErrorPage::collectContainer()
    // after a binding fails so the panel can show the exact stack that was being built.
    //
    //   $resolve_stack — chronological list of ['id' => string, 'time' => float]
    //                    for every make() call currently in progress (LIFO).
    //   $failed_at     — last abstract that threw during resolution (or null).
    //   $partial_args  — constructor argument names that were being resolved
    //                    when the failure occurred (frame context).
    //   $bindings_snapshot — captured at boot() so the error page can list every
    //                    registered binding even after the request blew up.
    public array $resolveStack    = [];
    public ?string $failedAt      = null;
    public array $partialArgs     = [];
    public array $bindingsSnapshot = [];
    public bool $tracing           = false;

    // SYS scope: framework internals, immutable after boot.
    private array $sys = [];

    // APP scope: config values from config/*.php, read-only at runtime.
    private array $appData = [];

    // USER scope: per-request mutable state, reset on each handle().
    private array $user = [];

    // Route registration and dispatch; public read, private write.
    public private(set) \Skim\Core\Router $router;
    // Middleware execution chain built during boot.
    private \Skim\Core\Pipeline   $pipeline;
    // Middleware classes applied to every request before route-specific ones.
    private array      $globalMiddleware = [];
    // Mutation guard: blocks router and scope writes after freeze().
    private bool       $frozen = false;

    // Active extension context: {name, priority} during DI resolution.
    private ?array $extensionContext = null;
    // Discovers, registers, and boots extensions in priority order.
    private ?\Skim\Ext\ExtensionManager $extensionManager = null;
    private bool $extensionsBooted = false;
    // Idempotent boot guard: prevents re-running boot() on repeated calls.
    private bool $booted = false;
    private bool $debugMode = false;
    // Snapshot of ob_get_level() at beginRequest() so endRequest() only closes
    // buffers opened during the request, leaving PHPUnit/test buffers intact.
    private int $requestObLevel = 0;
    // Tracks abstracts bound with request lifetime so endRequest() can clear them.
    private array $requestScoped = [];
    // Tracks abstracts bound with transient lifetime so make() skips caching.
    private array $transient = [];

    /**
     * Private constructor enforces singleton access via instance(). #AI:__construct
     *
     * @param string $root Project root directory used for .env and config resolution.
     */
    private function __construct(private readonly string $root) {
        $this->router   = new \Skim\Core\Router();
        $this->pipeline = new \Skim\Core\Pipeline();
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
        $this->pipeline = new \Skim\Core\Pipeline();
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
            self::$instance->router->setMutationGuard(fn(): bool => !self::$instance->frozen);
            self::$instance->set('sys.router', self::$instance->router);
        }
        return self::$instance;
    }

    /**
     * Returns a fresh isolated container for tests (testing only). #AI:testInstance
     *
     * Skips .env and config/*.php loading. Inject config directly via the `$config`
     * parameter. Never shares state with `App::instance()`.
     *
     * Example:
     *   $app = App::testInstance(['db.driver' => 'memory', 'app.debug' => true]);
     *   $app->router->get('/test', fn() => 'ok');
     *   $res = $app->dispatch(Request::make('GET', '/test'), new Response());
     *
     * @param array $config Key-value pairs injected into app scope (e.g. `['db.driver' => 'memory']`).
     * @return static A fresh, unbooted container with router and pipeline ready.
     */
    public static function testInstance(array $config = []): static {
        $inst = new static(dirname(__DIR__, 2));
        $inst->requestObLevel = ob_get_level();
        foreach ($config as $key => $val) {
            if (str_starts_with($key, 'app.')) {
                $inst->appData[substr($key, 4)] = $val;
            } else {
                $inst->appData[$key] = $val;
            }
        }
        $inst->router->setMutationGuard(fn(): bool => !$inst->frozen);
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

        if ($layout = \Skim\Core\Config::get('app.view.default_layout')) {
            \Skim\View\View::setDefaultLayout((string) $layout);
        }

        $this->extensionManager = \Skim\Ext\ExtensionManager::discover($this->root, $this);
        $this->extensionManager->register($this);

        // Capture the bindings snapshot once at boot so the error page can list
        // every registered service even if the request blows up later. Cheap —
        // a single array_keys + a hash for type metadata.
        $this->bindingsSnapshot = $this->snapshotBindings();
    }

    /**
     * Returns a metadata array of every currently-registered binding. #AI:snapshot_bindings
     *
     * Used by ErrorPage::collectContainer() to render the "registered services"
     * list in the Container panel. Captures [abstract, factory_kind, priority]
     * triples; never the factory closure itself (closures don't survive var_export
     * and would leak memory in the error page).
     *
     * @return array<int, array{abstract: string, factory_kind: string, priority: int}>
     */
    public function snapshotBindings(): array {
        $rows = [];
        foreach ($this->bindings as $abstract => $factory) {
            $rows[] = [
                'abstract'     => $abstract,
                'factory_kind' => is_string($factory) ? 'string' : 'closure',
                'priority'     => $this->bindingPriorities[$abstract] ?? 0,
            ];
        }
        return $rows;
    }

    /**
     * Emits a 'route_matched' request_trace event with the matched pattern, params,
     * and middleware stack. Read by the error page's Request panel and the toolbar.
     *
     * No-op when request_trace is disabled — never throws.
     */
    private function recordRouteTrace(array $route): void {
        if (!\Skim\Dev\RequestTrace::isEnabled()) {
            return;
        }

        \Skim\Dev\RequestTrace::event('route_matched', [
            'pattern'    => (string) ($route['pattern'] ?? ''),
            'params'     => $route['params'] ?? [],
            'middleware' => array_values(array_map(
                static fn(string|array|\Skim\Core\Middleware $entry): string
                    => match (true) {
                        $entry instanceof \Skim\Core\Middleware => $entry::class,
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
            if (isset($this->sys[$k]) && \Skim\Core\Config::get('app.debug') === false) {
                throw new \LogicException("SYS scope key '{$k}' is immutable after boot.");
            }
            $this->sys[$k] = $value;
        } elseif (str_starts_with($key, 'app.')) {
            $this->appData[substr($key, 4)] = $value;
        } else {
            $prefix = str_starts_with($key, 'user.') ? substr($key, 5) : $key;
            $this->user[$prefix] = $value;
        }
    }

    /**
     * Reads a value from the scope matching the key prefix. #AI:get
     *
     * Falls back to `Config::get("app.{$k}")` for `app.*` keys not set directly.
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
            return $this->appData[$k] ?? \Skim\Core\Config::get("app.{$k}", $default);
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
     * When `app.strict_di` is true, an explicit lifetime is mandatory.
     *
     * @param string          $abstract  Abstract type or identifier to bind.
     * @param callable|string $factory   Callable receiving `app`, or class name for auto-wiring.
     * @param int|null        $priority  Binding priority (higher wins). Null uses current extension priority.
     * @param \Skim\Core\Lifetime|null $lifetime Lifetime for this binding. Null defaults to singleton; null is rejected when strict_di is enabled.
     * @throws \LogicException If called after `freeze()` or if strict_di is enabled and no lifetime is provided.
     */
    public function bind(string $abstract, callable|string $factory, ?int $priority = null, ?\Skim\Core\Lifetime $lifetime = null): void {
        if ($lifetime === null && (bool) \Skim\Core\Config::get('app.strict_di', false)) {
            throw new \LogicException(
                "Strict DI is enabled: bind('{$abstract}', ...) must declare an explicit lifetime "
                . "(lifetime::Singleton, lifetime::Request or lifetime::Transient)."
            );
        }
        $this->applyBinding($abstract, $factory, $priority, $lifetime ?? \Skim\Core\Lifetime::Singleton);
    }

    /**
     * Registers a request-scoped binding. #AI:bindRequest
     *
     * Behaves like bind() but the resolved singleton is cleared from the
     * container at the end of each request via endRequest().
     *
     * @param string          $abstract Abstract type or identifier.
     * @param callable|string $factory  Callable receiving app, or class name for auto-wiring.
     * @param int|null        $priority Binding priority (higher wins).
     */
    public function bindRequest(string $abstract, callable|string $factory, ?int $priority = null): void {
        $this->applyBinding($abstract, $factory, $priority, \Skim\Core\Lifetime::Request);
    }

    /**
     * Registers a transient binding — built fresh on every make() call. #AI:bindTransient
     *
     * Transient services are never cached in $resolved, so each make()
     * returns a new instance.
     *
     * @param string          $abstract Abstract type or identifier.
     * @param callable|string $factory  Callable receiving app, or class name for auto-wiring.
     * @param int|null        $priority Binding priority (higher wins).
     */
    public function bindTransient(string $abstract, callable|string $factory, ?int $priority = null): void {
        $this->applyBinding($abstract, $factory, $priority, \Skim\Core\Lifetime::Transient);
    }

    /**
     * Performs the actual binding registration for a given lifetime. #AI:applyBinding
     *
     * Shared by bind(), bindRequest() and bindTransient(). Kept private and free
     * of the strict-DI check so the request/transient helpers (which pass an explicit
     * lifetime) are never blocked by app.strict_di.
     *
     * @param string          $abstract Abstract type or identifier to bind.
     * @param callable|string $factory  Callable receiving app, or class name for auto-wiring.
     * @param int|null        $priority Binding priority (higher wins). Null uses current extension priority.
     * @param \Skim\Core\Lifetime $lifetime Resolved lifetime to apply.
     * @throws \LogicException If called after freeze().
     */
    private function applyBinding(string $abstract, callable|string $factory, ?int $priority, \Skim\Core\Lifetime $lifetime): void {
        $this->assertMutable('bind services');

        $priority ??= $this->currentExtensionPriority();
        $currentPriority = $this->bindingPriorities[$abstract] ?? PHP_INT_MIN;

        if (isset($this->bindings[$abstract]) && $priority < $currentPriority) {
            return;
        }

        $this->bindings[$abstract] = $this->normalizeFactory($factory);
        $this->bindingPriorities[$abstract] = $priority;
        $this->clearLifetimeMeta($abstract);

        if ($lifetime === \Skim\Core\Lifetime::Request) {
            $this->requestScoped[$abstract] = true;
        } elseif ($lifetime === \Skim\Core\Lifetime::Transient) {
            $this->transient[$abstract] = true;
        }
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
        $this->assertMutable('decorate services');

        $this->decorators[$abstract][] = [
            'factory'  => $decorator,
            'priority' => $priority ?? $this->currentExtensionPriority(),
            'order'    => ++$this->decoratorOrder,
        ];

        unset($this->resolved[$abstract]);
    }

    /**
     * Resolves an abstract to a singleton instance. #AI:make
     *
     * Returns the cached singleton if already resolved (unless the binding is
     * transient). Otherwise invokes the registered factory, or falls back to
     * reflection auto-wiring when no binding exists but the class is loadable.
     * Applies decorators in priority order.
     *
     * Example:
     *   $app->bind(Mailer::class, fn($app) => new SmtpMailer($app->get('app.mail')));
     *   $mailer = $app->make(Mailer::class); // singleton from here on
     *
     * @param string $abstract Class name or identifier to resolve.
     * @return mixed The resolved (and possibly decorated) singleton instance.
     * @throws \RuntimeException If no binding exists and the class cannot be auto-wired.
     */
    public function make(string $abstract): mixed {
        if (isset($this->resolved[$abstract]) && !isset($this->transient[$abstract])) {
            return $this->resolved[$abstract];
        }

        $tracking = $this->tracing;
        if ($tracking) {
            $this->resolveStack[] = ['id' => $abstract, 'time' => microtime(true)];
        }

        try {
            if (isset($this->bindings[$abstract])) {
                $instance = $this->applyDecorators(
                    $abstract,
                    ($this->bindings[$abstract])($this),
                );
                if (!isset($this->transient[$abstract])) {
                    $this->resolved[$abstract] = $instance;
                }
                return $instance;
            }

            // auto-wire via reflection
            if (class_exists($abstract)) {
                $instance = $this->applyDecorators(
                    $abstract,
                    $this->build($abstract),
                );
                if (!isset($this->transient[$abstract])) {
                    $this->resolved[$abstract] = $instance;
                }
                return $instance;
            }

            throw new \RuntimeException("No binding registered for '{$abstract}'");
        }
        catch (\Throwable $e) {
            if ($tracking) {
                $this->failedAt     = $abstract;
                $this->partialArgs  = $this->currentCtorParams($abstract);
            }
            throw $e;
        }
        finally {
            if ($tracking) {
                array_pop($this->resolveStack);
            }
        }
    }

    /**
     * Resolves a service fresh every time; never caches in $resolved. #AI:makeTransient
     *
     * Mirrors make() but skips the singleton cache entirely, so the resolved
     * instance and its constructor-injected dependencies are rebuilt on every
     * call. Used for class-based controllers in worker mode so request-scoped
     * state cannot leak across requests served by the same process.
     *
     * @param string $abstract Class name or identifier to resolve.
     * @return mixed A freshly built (and possibly decorated) instance.
     * @throws \RuntimeException If no binding exists and the class cannot be auto-wired.
     */
    public function makeTransient(string $abstract): mixed {
        if (isset($this->bindings[$abstract])) {
            return $this->applyDecorators($abstract, ($this->bindings[$abstract])($this));
        }
        if (class_exists($abstract)) {
            return $this->applyDecorators($abstract, $this->build($abstract));
        }
        throw new \RuntimeException("No binding registered for '{$abstract}'");
    }

    /**
     * Returns the constructor parameter names of $class for trace context. #AI:current_ctor_params
     *
     * Used by make()'s catch block so error_page can show "this service needed
     * [Db, Cache, HttpClient] but failed while resolving Cache". Returns [] when
     * the class is missing or has no constructor.
     *
     * @return string[] Constructor parameter names.
     */
    private function currentCtorParams(string $class): array {
        if (!class_exists($class)) {
            return [];
        }
        $ref  = new \ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return [];
        }
        $out = [];
        foreach ($ctor->getParameters() as $p) {
            $out[] = $p->getName();
        }
        return $out;
    }

    /**
     * Enables DI tracing — make() starts populating resolve_stack, failed_at, #AI:enable_tracing
     * and partial_args so the error page can render the in-flight chain when
     * a binding throws.
     *
     * Off by default to keep the hot path allocation-free. Should be turned on
     * by the error handler before render(), not at request time, so normal
     * requests pay nothing.
     */
    public function enableTracing(): void {
        $this->tracing        = true;
        $this->resolveStack  = [];
        $this->failedAt      = null;
        $this->partialArgs   = [];
    }

    /**
     * Disables DI tracing and clears the captured state. #AI:disable_tracing
     *
     * Safe to call between requests — resets the trace fields so a leftover
     * state from a previous request can't leak into a new one.
     */
    public function disableTracing(): void {
        $this->tracing        = false;
        $this->resolveStack  = [];
        $this->failedAt      = null;
        $this->partialArgs   = [];
    }

    /**
     * Returns the list of abstracts that have been resolved during this request. #AI:resolved_services
     *
     * Mirrors Laravel's container->resolved() — used by the error page's
     * Container tab to show which services have been instantiated and which
     * are still pending. Exposes only the abstract names (not the instances
     * themselves) so the list is safe to render.
     *
     * @return string[] Sorted list of resolved abstract identifiers.
     */
    public function resolvedServices(): array {
        $names = array_keys($this->resolved);
        sort($names);
        return $names;
    }

    /**
     * Returns the list of abstracts registered with request lifetime. #AI:requestScopedServices
     *
     * @return string[] Abstract identifiers bound as request-scoped.
     */
    public function requestScopedServices(): array {
        return array_keys($this->requestScoped);
    }

    /**
     * Returns true when the user scope contains no keys. #AI:userScopeEmpty
     *
     * Used by the leak detector to verify endRequest() cleared per-request state.
     */
    public function userScopeEmpty(): bool {
        return $this->user === [];
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
        $this->assertMutable('register middleware');
        $this->globalMiddleware[] = ['class' => $class, 'args' => $args];
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
     * Returns true after mutation points have been frozen. #AI:isFrozen
     *
     * @return bool True if `freeze()` has been called.
     */
    public function isFrozen(): bool {
        return $this->frozen;
    }

    /**
     * Returns whether debug mode is enabled. #AI:isDebugMode
     */
    public function isDebugMode(): bool {
        return $this->debugMode;
    }

    /**
     * Runs a callback with a temporary extension context for priority resolution. #AI:withExtensionContext
     *
     * Sets the active extension name and priority so that `bind()`, `decorate()`,
     * and similar calls inside `$callback` inherit the correct priority. Restores
     * the previous context even if the callback throws.
     *
     * Example:
     *   $app->withExtensionContext('auth', 50, function() use ($app) {
      *       $app->bind(AuthService::class, fn() => new JwtAuth());
     *   });
     *
     * @param string   $name     Extension identifier for diagnostics.
     * @param int      $priority Priority applied to registrations inside the callback.
     * @param callable $callback Executed with the extension context active.
     * @return mixed Whatever the callback returns.
     */
    public function withExtensionContext(string $name, int $priority, callable $callback): mixed {
        $previous = $this->extensionContext;
        $this->extensionContext = ['name' => $name, 'priority' => $priority];

        try {
            return $callback();
        } finally {
            $this->extensionContext = $previous;
        }
    }
    /**
     * Begins a request in worker mode — enables profiler and request_trace if debug. #AI:beginRequest
     *
     * Separated from run() so the worker entrypoint can call it once per request
     * without re-running the full boot sequence.
     */
    public function beginRequest(): void {
        $this->requestObLevel = ob_get_level();
        $this->debugMode = (bool) $this->get('app.debug', false);

        if ($this->debugMode) {
            \Skim\Dev\Profiler::enable();
            \Skim\Dev\RequestTrace::enable();
            \Skim\Dev\RequestTrace::start(
                bin2hex(random_bytes(8)),
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REQUEST_URI'] ?? '/',
            );
            \Skim\Dev\RequestTrace::setExtensions(
                array_column($this->get('sys.extensions', []), 'name')
            );
        }

        if (\Skim\Worker\LeakDetector::isActive()) {
            \Skim\Worker\LeakDetector::begin();
        }
    }

    /**
     * Ends a request in worker mode — resets per-request state. #AI:endRequest
     *
     * Clears user scope and request-scoped DI bindings, then runs the global
     * WorkerReset orchestrator to clear static facades and output buffers.
     */
    public function endRequest(): void {
        $this->user = [];

        foreach (array_keys($this->requestScoped) as $abstract) {
            unset($this->resolved[$abstract]);
        }

        $this->disableTracing();
        \Skim\Worker\WorkerReset::apply($this->requestObLevel);

        if (\Skim\Worker\LeakDetector::isActive()) {
            \Skim\Worker\LeakDetector::check($this);
        }
    }

    /**
     * Handles an uncaught exception. Used by the global exception handler. #AI:handleException
     */
    public function handleException(\Throwable $e): void {
        if ($this->debugMode) {
            \Skim\Dev\ErrorPage::render($e);
        } else {
            http_response_code(500);
            echo 'Internal Server Error';
        }
    }

    /**
     * Emits a controller result through the response object. #AI:emit
     *
     * Normalises mixed return values (response, array, string, null, false)
     * into a proper HTTP response and sends it.
     *
     * @param mixed    $result   Controller return value.
     * @param \Skim\Core\Response $fallback Response object used as fallback for non-response types.
     */
    public function emit(mixed $result, \Skim\Core\Response $fallback): void {
        match (true) {
            $result instanceof \Skim\Core\Response => $result->send(),
            is_array($result) || is_object($result) => $fallback->json($result)->send(),
            is_string($result) => $fallback->setBody($result)->send(),
            $result === null => $fallback->send(),
            $result === false => $fallback->status(405)->send(),
            default => $fallback->send(),
        };
    }

    /**
     * Dispatches the HTTP request through middleware and sends the response. #AI:run
     *
     * Runs the full one-shot lifecycle for non-worker deployments. In worker mode,
     * callers should instead call boot() once, then loop over beginRequest(),
     * dispatch(), and endRequest().
     *
     * Example:
     *   // public/index.php
     *   require __DIR__ . '/../vendor/autoload.php';
     *   App::instance()->run();
     */
    public function run(): void {
        $this->ensureBooted();
        $this->bootExtensions();
        $this->freeze();

        set_exception_handler(function(\Throwable $e): void {
            $this->handleException($e);
        });

        $this->beginRequest();
        $req = \Skim\Core\Request::fromGlobals();
        $res = new \Skim\Core\Response();

        try {
            $result = $this->dispatch($req, $res);

            if ($this->debugMode) {
                $trace = \Skim\Dev\RequestTrace::finish($result->getStatus());
                $this->sys['last_trace'] = $trace;
            } elseif ($result->getStatus() >= 500) {
                error_log('[skim][request] ' . $req->method() . ' ' . $req->path() . ' ' . $result->getStatus());
            }

            $this->emit($result, $res);
        } finally {
            $this->endRequest();
        }
    }

    /**
     * Dispatches a request through the router and middleware pipeline. #AI:dispatch
     * @return \Skim\Core\Response The populated response (404 if no route, 405 if method not allowed).
     */
    public function dispatch(\Skim\Core\Request $req, \Skim\Core\Response $res, bool $skipMiddleware = false): \Skim\Core\Response {
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
                $req->setRouteParams($route['params']);
            }

            if ($this->debugMode) {
                $this->recordRouteTrace($route);
            }

            $middlewares = $skipMiddleware
                ? []
                : array_merge($this->globalMiddleware, $route['middleware'] ?? []);

            $handler = $route['handler'];
            $result = $this->pipeline->run($req, $res, $middlewares, function(\Skim\Core\Request $req, \Skim\Core\Response $res) use ($handler, $route): mixed {
                if (is_array($handler) && $this->debugMode) {
                    \Skim\Dev\RequestTrace::event('controller_called', ['class' => $handler[0], 'method' => $handler[1]]);
                }
                return $this->callHandler($handler, $req, $res, $route['params'] ?? []);
            });

            if ($this->debugMode) {
                $this->pipeline->recordPipelineTrace($req, $middlewares);
            }

            if ($result instanceof \Skim\Core\Response) {
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
     * Resolves controller handler arguments via DI and route params. #AI:callHandler
     *
     * For closures, passes request/response and route params directly. For class-based
     * handlers, resolves the controller via `makeTransient()` so a fresh instance is
     * built per request (worker-mode safe — no cross-request state leakage) and injects
     * constructor/method dependencies by type-hint, matching route params by name.
     */
    private function callHandler(array|callable $handler, \Skim\Core\Request $req, \Skim\Core\Response $res, array $params): mixed {
        if (is_callable($handler) && !is_array($handler)) {
            return $handler($req, $res, ...$params);
        }

        [$class, $method] = $handler;
        $controller = $this->makeTransient($class);

        $ref    = new \ReflectionMethod($controller, $method);
        $args   = [];

		foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $name = $param->getName();

			if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                $args[] = match ($typeName) {
                    \Skim\Core\Request::class  => $req,
                    \Skim\Core\Response::class => $res,
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
     * Boots extensions once via the extension manager. #AI:bootExtensions
     *
     * Idempotent — subsequent calls after the first are no-ops.
     */
    public function bootExtensions(): void {
        if ($this->extensionsBooted) {
            return;
        }

        $this->extensionManager?->boot($this);
        $this->extensionsBooted = true;
    }

    /**
     * Shuts down process-scoped resources before the worker exits. #AI:shutdown
     *
     * Closes all pooled database connections and clears the cache facade.
     */
    public function shutdown(): void {
        \Skim\Db\Db::reset();
        \Skim\Cache\Cache::reset();
    }

    /**
     * Clears resolved cache and all lifetime flags for an abstract. #AI:clearLifetimeMeta
     *
     * Centralizes the lifetime transition so bind(), bindRequest(), and
     * bindTransient() cannot leave stale request_scoped/transient flags
     * behind when an abstract is rebound with a different lifetime.
     */
    private function clearLifetimeMeta(string $abstract): void {
        unset($this->resolved[$abstract], $this->requestScoped[$abstract], $this->transient[$abstract]);
    }

    /**
     * Converts a string class name to an auto-wiring factory closure. #AI:normalizeFactory
     *
     * @param callable|string $factory Class name or callable.
     * @return callable Always returns a callable accepting `app`.
     */
    private function normalizeFactory(callable|string $factory): callable {
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
    public function build(string $abstract): mixed {
        $ref  = new \ReflectionClass($abstract);
        $ctor = $ref->getConstructor();

        if ($ctor === null) {
            return $ref->newInstance();
        }

        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                // Record which params are being resolved so the error page can
                // show "stuck on param #2" even if the make() throws.
                if ($this->tracing) {
                    $this->partialArgs[] = $param->getName();
                }
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
     * Applies registered decorators to a resolved service in priority then registration order. #AI:applyDecorators
     *
     * Returns the service unchanged when no decorators are registered for the abstract.
     */
    private function applyDecorators(string $abstract, mixed $service): mixed {
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
     * Returns the priority from the active extension context, defaulting to 100. #AI:currentExtensionPriority
     */
    private function currentExtensionPriority(): int {
        return (int) ($this->extensionContext['priority'] ?? 100);
    }

    /**
     * Throws if the app is frozen, preventing post-boot mutation. #AI:assertMutable
     *
     * @param string $action Human-readable action name for the error message.
     * @throws \LogicException If the app is frozen.
     */
    private function assertMutable(string $action): void {
        if ($this->frozen) {
            $extension = $this->extensionContext['name'] ?? 'unknown';
            error_log("[skim][invariant-violation] extension='{$extension}' attempted '{$action}' after freeze");
            throw new \LogicException("Cannot {$action} after app is frozen.");
        }
    }
}

#AI:class
#AI symbol: Skim\Core\App
#AI source_path: src/Core/App.php
#AI title: app
#AI description: Singleton container and HTTP kernel combining a scoped key-value store, DI container with auto-wiring, and middleware pipeline.
#AI role: application kernel and service container
#AI layer: core
#AI badges: [singleton; container; kernel; di; middleware; scoped-store]
#AI intro: `Skim\Core\App` is the central application kernel. It combines three responsibilities in one singleton: a scoped key-value store (sys/app/user), a dependency injection container with factory bindings and reflection auto-wiring, and an HTTP kernel with a middleware pipeline. Extensions register services and middleware through `withExtensionContext()` during the boot phase.
#AI lifecycle: singleton, created on first `instance()` call, booted lazily via `ensureBooted()` in `run()` or `dispatch()`, frozen before request dispatch
#AI fallback: none — app is the root; subsystems fall back to their own defaults
#AI test_seam: testInstance() for isolated containers without env/config loading
#AI invariants: [instance() returns the same object for the process lifetime; sys.* keys are write-once in production; bind/decorate/use throw after freeze(); make() caches singletons until the container is cloned]
#AI core_behaviors: [Three scopes (sys, app, user) isolate framework internals from config and per-request state; DI resolution caches singletons and falls back to reflection auto-wiring; Middleware runs in registration order before route handlers; Extensions register services with priority-based conflict resolution]
#AI warnings: [`run()` installs a global exception handler and is not re-entrant; `freeze()` is irreversible for the instance lifetime; sys.* writes throw LogicException in production after first set]
#AI notes: The constructor is private — always use `instance()` or `testInstance()`. Cloning resets resolved singletons and user scope but preserves bindings and sys/app data.
#AI scope_items: [{name: sys | mutable: false | desc: Framework internals (router, extensions). Write-once in production, mutable in debug.}; {name: app | mutable: true | desc: Config values from config/*.php or testInstance(). Falls back to Config::get on read.}; {name: user | mutable: true | desc: Per-request mutable state. Cleared on clone.}]
#AI owns: singleton instance, DI bindings, resolved singletons, scoped store, middleware stack, router, pipeline, extension context
#AI entry_points: [instance; testInstance; run; dispatch]
#AI config_reads: [app.debug; app.view.default_layout; app.*]
#AI non_goals: [Does not handle HTTP transport (delegates to request/response); Does not manage database connections directly; Does not serialize or persist state across requests]
#AI side_effects: [run() installs global exception handler and sends HTTP response; freeze() permanently locks mutation; set() may throw on sys.* overwrite in production; boot() initialises router, pipeline, and extensions once]
#AI flow: App::instance() -> run() -> ensureBooted() -> boot() [router -> extensions] -> profiler/RequestTrace -> bootExtensions() -> freeze() -> dispatch() -> pipeline -> callHandler() -> response
#AI lifecycle_steps: [App::instance(); -> run(); -> ensureBooted(); -> boot() [view layout + router + pipeline + extensionManager::discover + register]; -> profiler/RequestTrace enable (if debug); -> bootExtensions(); -> freeze(); -> Request::fromGlobals(); -> dispatch(); -> Pipeline::run(); -> callHandler(); -> Response::send()]
#AI section_order: [Lifecycle; Scoped Store; DI Container; Middleware; Request Dispatch; Extensions; Testing]
#AI architectural_notes: The app class is intentionally a god object combining container, kernel, and store. This keeps the framework surface area small — one class to learn, one singleton to pass around. Extensions interact with app exclusively through `withExtensionContext()` during boot, then the app freezes to prevent further mutation.

#AI:instance
#AI group: Lifecycle
#AI frequency: high
#AI signature: public static function instance(): static
#AI contract: Returns the process-wide singleton. On first call, creates the instance with SKIM_ROOT (or auto-detected root) but does NOT call boot(). Boot is deferred to run() or dispatch() via ensureBooted(). Subsequent calls return the cached instance.
#AI return_detail: {type: static | desc: The application instance (not yet booted).}
#AI side_effects: [Creates the singleton on first call; does not trigger boot]

#AI:testInstance
#AI group: Testing
#AI frequency: high
#AI signature: public static function testInstance(array $config = []): static
#AI contract: Creates a fresh isolated container that skips .env and config/*.php loading. Config values are injected directly via the $config array. The returned instance has its own router, pipeline, and mutation guard but shares no state with App::instance().
#AI param_details: [{name: $config | type: array | required: false | desc: Key-value pairs injected into app scope. Keys use dot notation without the app. prefix (e.g. 'db.driver' => 'memory').}]
#AI return_detail: {type: static | desc: A fresh, unbooted container with router and pipeline ready.}
#AI notes: Safe to call multiple times per test. Each call returns an independent instance.

#AI:boot
#AI group: Lifecycle
#AI frequency: medium
#AI signature: public function boot(): void
#AI contract: Initializes framework subsystems in strict dependency order: view layout → router → pipeline → extension discovery and registration. Idempotent — subsequent calls after the first are no-ops. Profiler and RequestTrace are NOT enabled here; they are enabled in run(). env and config are lazy-loaded on first access.
#AI side_effects: [Sets $booted = true; initialises router, pipeline, extensionManager; registers extensions]

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
#AI contract: Reads from the scope matching the key prefix. For app.* keys, falls back to Config::get("app.{$k}") when the key is not set directly. Never throws — returns $default for missing keys.
#AI param_details: [{name: $key | type: string | required: true | desc: Scoped key to read. Prefix determines source scope.}; {name: $default | type: mixed | required: false | desc: Returned when the key is absent from the target scope.}]
#AI return_detail: {type: mixed | desc: The stored value, config fallback for app.*, or $default.}

#AI:bind
#AI group: DI Container
#AI frequency: high
#AI signature: public function bind(string $abstract, callable|string $factory, ?int $priority = null, ?Lifetime $lifetime = null): void
#AI contract: Registers a factory callable for DI resolution. Clears the resolved singleton cache for the abstract so the new factory takes effect on the next make() call. Higher-priority bindings replace lower ones; calls with lower priority than the current binding are silently ignored. When app.strict_di is true, an explicit lifetime is mandatory.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type or identifier to bind. Typically a fully-qualified class name.}; {name: $factory | type: callable|string | required: true | desc: Callable receiving the app instance, or a class name string for auto-wiring.}; {name: $priority | type: ?int | required: false | desc: Binding priority (higher wins). Null uses the current extension context priority (default 100).}; {name: $lifetime | type: ?lifetime | required: false | desc: Binding lifetime. Null defaults to singleton; null is rejected when strict_di is enabled.}]
#AI throws_details: [{type: \LogicException | desc: If called after freeze().}; {type: \LogicException | desc: If strict_di is enabled and no lifetime is provided.}]
#AI side_effects: [Clears resolved singleton cache for $abstract; Mutates bindings and bindingPriorities arrays]

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
#AI examples: [{label: Basic resolution | code: $app->bind(Mailer::class, fn($app) => new SmtpMailer($app->get('app.mail')));\n$mailer = $app->make(Mailer::class);}]

#AI:makeTransient
#AI group: DI Container
#AI frequency: medium
#AI signature: public function makeTransient(string $abstract): mixed
#AI contract: Resolves a service fresh every time; never caches in $resolved. Mirrors make() but skips the singleton cache entirely, so the resolved instance and its constructor-injected dependencies are rebuilt on every call. Used for class-based controllers in worker mode so request-scoped state cannot leak across requests served by the same process.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Class name or identifier to resolve.}]
#AI return_detail: {type: mixed | desc: A freshly built (and possibly decorated) instance.}
#AI throws_details: [{type: \RuntimeException | desc: If no binding exists and the class cannot be auto-wired.}]
#AI side_effects: [Never writes to $resolved array]

#AI:requestScopedServices
#AI group: DI Container
#AI frequency: low
#AI signature: public function requestScopedServices(): array
#AI contract: Returns the list of abstracts registered with request lifetime. Used by the leak detector and tests to verify that request-scoped bindings are correctly tracked.
#AI return_detail: {type: string[] | desc: Abstract identifiers bound as request-scoped.}

#AI:userScopeEmpty
#AI group: Scoped Store
#AI frequency: low
#AI signature: public function userScopeEmpty(): bool
#AI contract: Returns true when the user scope contains no keys. Used by the leak detector to verify endRequest() cleared per-request state.
#AI return_detail: {type: bool | desc: True when $user array is empty.}

#AI:use
#AI group: Middleware
#AI frequency: medium
#AI signature: public function use(string $class, mixed ...$args): void
#AI contract: Registers a global middleware class applied to every HTTP request in registration order. Must be called before run() or freeze().
#AI param_details: [{name: $class | type: string | required: true | desc: Middleware class name implementing the middleware interface.}; {name: $args | type: mixed | required: false | desc: Constructor arguments passed to the middleware when instantiated.}]
#AI throws_details: [{type: \LogicException | desc: If called after freeze().}]
#AI side_effects: [Appends to globalMiddleware stack]

#AI:freeze
#AI group: Middleware
#AI frequency: low
#AI signature: public function freeze(): void
#AI contract: Freezes all mutation points: service bindings, decorators, middleware registration, and route mutations. Called automatically by run() before dispatch. Irreversible for this instance.
#AI warnings: [Irreversible — no thaw() method exists. After freeze, bind(), decorate(), use(), and route registration all throw LogicException.]
#AI side_effects: [Sets internal frozen flag to true]

#AI:isFrozen
#AI group: Middleware
#AI frequency: low
#AI signature: public function isFrozen(): bool
#AI contract: Returns true after freeze() has been called.
#AI return_detail: {type: bool | desc: True if the app mutation points are frozen.}

#AI:withExtensionContext
#AI group: Extensions
#AI frequency: medium
#AI signature: public function withExtensionContext(string $name, int $priority, callable $callback): mixed
#AI contract: Temporarily sets the active extension name and priority so that bind(), decorate(), and similar calls inside $callback inherit the correct priority. The previous extension context is restored in a finally block, even if the callback throws.
#AI param_details: [{name: $name | type: string | required: true | desc: Extension identifier used for diagnostics and assertMutable error messages.}; {name: $priority | type: int | required: true | desc: Priority applied to registrations (bind, decorate) inside the callback.}; {name: $callback | type: callable | required: true | desc: Executed with the extension context active. Return value is passed through.}]
#AI return_detail: {type: mixed | desc: Whatever the callback returns.}
#AI side_effects: [Temporarily mutates extensionContext; restored in finally block]
#AI examples: [{label: Extension registration | code: $app->withExtensionContext('auth', 50, function() use ($app) {\n    $app->bind(AuthService::class, fn() => new JwtAuth());\n});}]

#AI:run
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function run(): void
#AI contract: Executes the full HTTP request cycle: calls ensureBooted(), enables profiler and RequestTrace (if debug), installs a global exception handler, boots extensions, freezes the app, builds the request from PHP globals, dispatches through the middleware pipeline, records request traces, and sends the response. Called once per request from public/index.php.
#AI warnings: [Not re-entrant; Installs a global exception handler that persists for the process lifetime; In production, 500 errors return a bare 'Internal Server Error' string]
#AI side_effects: [Calls ensureBooted(); Enables profiler and RequestTrace (if debug); Installs global exception handler; Boots extensions; Freezes the app; Sends HTTP response headers and body; Records request trace or error log]
#AI examples: [{label: Entry point | code: // public/index.php\nrequire __DIR__ . '/../vendor/autoload.php';\napp::instance()->run();}]

#AI:dispatch
#AI group: Request Dispatch
#AI frequency: high
#AI signature: public function dispatch(Request $req, Response $res, bool $skipMiddleware = false): response
#AI contract: Dispatches a request through the router and middleware pipeline without sending headers or body. Calls ensureBooted() first to guarantee the framework is initialised. Returns 404 for unmatched routes, 405 for method mismatches. Temporarily sets this instance as the global singleton during dispatch and restores the previous instance in a finally block.
#AI param_details: [{name: $req | type: request | required: true | desc: The request to dispatch.}; {name: $res | type: response | required: true | desc: The response object to populate.}; {name: $skipMiddleware | type: bool | required: false | desc: When true, bypasses all global and route middleware. Useful for unit tests.}]
#AI return_detail: {type: response | desc: The populated response. Status 404 if no route matches, 405 if path matches but method does not.}
#AI side_effects: [Temporarily replaces self::$instance during dispatch]
#AI examples: [{label: Test dispatch | code: $req = Request::make('GET', '/users/42');\n$res = $app->dispatch($req, new Response(), skipMiddleware: true);\nassert($res->getStatus() === 200);}]

#AI:callHandler
#AI group: Request Dispatch
#AI frequency: internal
#AI signature: private function callHandler(array|callable $handler, Request $req, Response $res, array $params): mixed
#AI contract: Resolves controller handler arguments via DI and route params. For closures, passes request/response and route params directly. For class-based handlers, resolves the controller via make() and injects method dependencies by type-hint, matching route params by name.
#AI param_details: [{name: $handler | type: array|callable | required: true | desc: Route handler — either a closure or [class, method] array.}; {name: $req | type: request | required: true | desc: Current request.}; {name: $res | type: response | required: true | desc: Current response.}; {name: $params | type: array | required: true | desc: Route parameters matched by the router.}]

#AI:capabilities
#AI group: Extensions
#AI frequency: low
#AI signature: public function capabilities(): array
#AI contract: Aggregates capability declarations from all loaded extensions into a flat map keyed by capability name. Each value includes `provided_by` (extension name) and any extension-declared details. First provider wins for duplicate capability names.
#AI return_detail: {type: array<string, array{provided_by: string}> | desc: Flat map of capability name to provider details.}
#AI notes: Safe to call before or after freeze — reads sys.extensions which is set during extension discovery.

#AI:bootExtensions
#AI group: Extensions
#AI frequency: internal
#AI signature: public function bootExtensions(): void
#AI contract: Boots extensions once via the extension manager. Idempotent — subsequent calls after the first are no-ops.

#AI:bindRequest
#AI group: DI Container
#AI frequency: medium
#AI signature: public function bindRequest(string $abstract, callable|string $factory, ?int $priority = null): void
#AI contract: Registers a request-scoped binding. Behaves like bind() but the resolved singleton is cleared from the container at the end of each request via endRequest().
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type or identifier to bind.}; {name: $factory | type: callable|string | required: true | desc: Callable receiving app, or class name for auto-wiring.}; {name: $priority | type: ?int | required: false | desc: Binding priority (higher wins).}]
#AI side_effects: [Registers binding and marks it as request-scoped]

#AI:bindTransient
#AI group: DI Container
#AI frequency: medium
#AI signature: public function bindTransient(string $abstract, callable|string $factory, ?int $priority = null): void
#AI contract: Registers a transient binding — built fresh on every make() call. Never cached in $resolved.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type or identifier to bind.}; {name: $factory | type: callable|string | required: true | desc: Callable receiving app, or class name for auto-wiring.}; {name: $priority | type: ?int | required: false | desc: Binding priority (higher wins).}]
#AI side_effects: [Registers binding and marks it as transient]

#AI:applyBinding
#AI group: DI Container
#AI frequency: internal
#AI signature: private function applyBinding(string $abstract, callable|string $factory, ?int $priority, Lifetime $lifetime): void
#AI contract: Performs the actual binding registration for a given lifetime. Shared by bind(), bindRequest() and bindTransient(). Keeps the strict-DI check out of the request/transient helpers so they are never blocked by app.strict_di.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type or identifier to bind.}; {name: $factory | type: callable|string | required: true | desc: Callable receiving app, or class name for auto-wiring.}; {name: $priority | type: ?int | required: false | desc: Binding priority (higher wins). Null uses current extension priority.}; {name: $lifetime | type: lifetime | required: true | desc: Resolved lifetime to apply.}]
#AI throws_details: [{type: \LogicException | desc: If called after freeze().}]
#AI side_effects: [Mutates bindings and bindingPriorities arrays; Clears lifetime meta for the abstract]

#AI:clearLifetimeMeta
#AI group: DI Container
#AI frequency: internal
#AI signature: private function clearLifetimeMeta(string $abstract): void
#AI contract: Clears resolved cache and all lifetime flags for an abstract. Centralizes lifetime transition so bind(), bindRequest(), and bindTransient() cannot leave stale requestScoped/transient flags behind when an abstract is rebound with a different lifetime.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract whose lifetime metadata should be cleared.}]
#AI side_effects: [Unsets entries in $resolved, $requestScoped, and $transient arrays]

#AI:beginRequest
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function beginRequest(): void
#AI contract: Begins a request in worker mode — enables profiler and RequestTrace if debug. Separated from run() so the worker entrypoint can call it once per request without re-running the full boot sequence.
#AI side_effects: [Enables profiler and RequestTrace when app.debug is true]

#AI:endRequest
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function endRequest(): void
#AI contract: Ends a request in worker mode — resets per-request state. Clears user scope and request-scoped DI bindings, then runs the global WorkerReset orchestrator.
#AI side_effects: [Clears user scope; clears request-scoped resolved singletons; disables tracing; runs WorkerReset::apply()]

#AI:handleException
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function handleException(\Throwable $e): void
#AI contract: Handles an uncaught exception. Used by the global exception handler installed in run(). Renders debug error page when debugMode is true, otherwise returns 500.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The uncaught exception to handle.}]
#AI side_effects: [Renders ErrorPage or sends HTTP 500 response]

#AI:emit
#AI group: Request Dispatch
#AI frequency: internal
#AI signature: public function emit(mixed $result, Response $fallback): void
#AI contract: Emits a controller result through the response object. Normalises mixed return values into a proper HTTP response and sends it.
#AI param_details: [{name: $result | type: mixed | required: true | desc: Controller return value.}; {name: $fallback | type: response | required: true | desc: Response object used as fallback for non-response types.}]
#AI side_effects: [Sends HTTP response]

#AI:shutdown
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function shutdown(): void
#AI contract: Shuts down process-scoped resources before the worker exits. Closes all pooled database connections and clears the cache facade.
#AI side_effects: [Resets db connection pool and cache driver]

#AI:isDebugMode
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function isDebugMode(): bool
#AI contract: Returns whether debug mode is enabled.
#AI return_detail: {type: bool | desc: True when app.debug config is enabled.}

#AI:normalizeFactory
#AI group: DI Container
#AI frequency: internal
#AI signature: private function normalizeFactory(callable|string $factory): callable
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

#AI:applyDecorators
#AI group: DI Container
#AI frequency: internal
#AI signature: private function applyDecorators(string $abstract, mixed $service): mixed
#AI contract: Applies registered decorators to a resolved service in ascending priority order, then registration order. Returns the service unchanged when no decorators exist for the abstract.
#AI param_details: [{name: $abstract | type: string | required: true | desc: Abstract type used to look up decorators.}; {name: $service | type: mixed | required: true | desc: The resolved service to decorate.}]
#AI return_detail: {type: mixed | desc: The decorated (or original) service.}

#AI:currentExtensionPriority
#AI group: Extensions
#AI frequency: internal
#AI signature: private function currentExtensionPriority(): int
#AI contract: Returns the priority from the active extension context set by withExtensionContext(). Defaults to 100 when no context is active.

#AI:assertMutable
#AI group: Lifecycle
#AI frequency: internal
#AI signature: private function assertMutable(string $action): void
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
