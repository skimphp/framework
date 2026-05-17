<?php declare(strict_types=1);

namespace skim\core;

use skim\dev\profiler;

// PSR-11 inspired container. Three scopes prevent accidental overwrites:
//   SYS  — framework internals registered at boot, immutable after that
//   APP  — config loaded from config/*.php, frozen after boot
//   USER — per-request mutable state, cleared at request boundaries
//
// Singleton pattern: app::instance() returns the same object across the request.
// Tests use app::test_instance() to get a fresh isolated container.
class app {
    private static ?self $instance = null;

    private array $bindings = [];    // DI bindings: abstract → factory callable
    private array $resolved = [];    // resolved singletons cache
    private array $sys      = [];    // SYS scope
    private array $app_data = [];    // APP scope (config values)
    private array $user     = [];    // USER scope

    public private(set) router $router;
    private pipeline   $pipeline;
    private array      $global_middleware = [];

    private function __construct(private readonly string $root) {}

    /**
     * @ai-contract returns singleton app instance
     * @ai-contract creates instance on first call, re-uses on subsequent calls
     */
    public static function instance(): static {
        if (self::$instance === null) {
            $root = defined('SKIM_ROOT') ? SKIM_ROOT : dirname(__DIR__, 2);
            self::$instance = new static($root);
            self::$instance->boot();
        }
        return self::$instance;
    }

    /**
     * @ai-contract for tests only — fresh container with optional config overrides
     * @ai-contract does NOT load .env or config/*.php — pass config array directly
     */
    public static function test_instance(array $config = []): static {
        $inst = new static(dirname(__DIR__, 2));
        foreach ($config as $key => $val) {
            $inst->app_data[$key] = $val;
        }
        $inst->router   = new router();
        $inst->pipeline = new pipeline();
        $inst->set('sys.router', $inst->router);
        return $inst;
    }

    // Boot sequence: env → config → profiler → router → pipeline
    // Order matters: config depends on env, profiler depends on config.
    private function boot(): void {
        env::load($this->root . '/.env');
        config::load($this->root . '/config');

        $this->router   = new router();
        $this->pipeline = new pipeline();
        $this->set('sys.router', $this->router);
    }

    // --- scoped key/value store ---

    /**
     * @ai-contract sets value in scope determined by prefix: sys.X, app.X, user.X
     * @ai-contract sys.* and app.* are write-once — throws on duplicate set in production
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
     * @ai-contract resolves from scope: sys.X → SYS, app.X → APP, user.X → USER
     * @ai-contract falls back to APP config via config::get when app.* key absent
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

    // --- DI container ---

    /**
     * @ai-contract binds an abstract type to a factory callable
     * @ai-contract factory receives the app instance as argument
     */
    public function bind(string $abstract, callable $factory): void {
        $this->bindings[$abstract] = $factory;
        unset($this->resolved[$abstract]);
    }

    /**
     * @ai-contract resolves a binding, caches result as singleton
     * @ai-contract uses PHP reflection to auto-wire constructor dependencies when no binding
     */
    public function make(string $abstract): mixed {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolved[$abstract] = ($this->bindings[$abstract])($this);
        }

        // Auto-wire via reflection
        if (class_exists($abstract)) {
            $ref    = new \ReflectionClass($abstract);
            $ctor   = $ref->getConstructor();
            if ($ctor === null) {
                return $this->resolved[$abstract] = $ref->newInstance();
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
            return $this->resolved[$abstract] = $ref->newInstanceArgs($deps);
        }

        throw new \RuntimeException("No binding registered for '{$abstract}'");
    }

    // --- middleware ---

    /**
     * @ai-contract registers a global middleware applied to every HTTP request
     * @ai-contract order of registration = order of execution (first registered, first run)
     */
    public function use(string $class, mixed ...$args): void {
        $this->global_middleware[] = ['class' => $class, 'args' => $args];
    }

    // --- lifecycle ---

    /**
     * @ai-contract dispatches CLI or HTTP request, sends response
     * @ai-contract registers exception handler: error_page (debug) or generic 500 (production)
     */
    public function run(): void {
        $is_debug = (bool) config::get('app.debug', false);
        set_exception_handler(function(\Throwable $e) use ($is_debug): void {
            if ($is_debug) {
                \skim\dev\error_page::render($e);
            } else {
                http_response_code(500);
                echo 'Internal Server Error';
            }
        });

        $req = request::from_globals();
        $res = new response();

        $route = $this->router->dispatch($req->method(), $req->path());

        if ($route === null) {
            $res->status(404)->json(['error' => 'Not Found'])->send();
            return;
        }

        if ($route === false) {
            // Method not allowed
            $res->status(405)->json(['error' => 'Method Not Allowed'])->send();
            return;
        }

        // Inject route params into request
        if (!empty($route['params'])) {
            $req->set_route_params($route['params']);
        }

        // Build middleware stack: global → group → route
        $middlewares = array_merge(
            $this->global_middleware,
            $route['middleware'] ?? [],
        );

        $handler  = $route['handler'];
        $pipeline = new pipeline();

        $result = $pipeline->run($req, $res, $middlewares, function(request $req, response $res) use ($handler): mixed {
            return $this->call_handler($handler, $req, $res, $route['params'] ?? []);
        });

        if ($result instanceof response) {
            $result->send();
        } elseif ($result !== null) {
            $res->json($result)->send();
        }
    }

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
                // Route param matching by name
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
}
