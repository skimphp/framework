<?php declare(strict_types=1);

namespace Skim\Core;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

/**
 * HTTP and CLI router wrapping nikic/fast-route for compiled regex dispatch.
 *
 * Use to register routes, groups, middleware, and CLI commands. All routes are
 * compiled into a single regex on first dispatch — orders of magnitude faster
 * than per-route string matching. Supports F3-compatible @param token syntax
 * (@id, @id:int, @slug:str, @any) converted to fast-route regex groups.
 *
 * Per-method shortcuts: get(), post(), put(), patch(), delete() for single
 * methods; any() for all five. map() is a Slim/Laravel-compatible alias for
 * add() that accepts either a single method string or an array of methods —
 * use it for shared routes (e.g. GET + HEAD on the same handler) or when
 * porting framework-style code; for new code prefer the explicit shortcuts
 * above or the generic add() directly.
 *
 * Example:
 *   $router->get('/users/@id:int', [UserController::class, 'show'])
 *          ->name('user.show')
 *          ->middleware(AuthMiddleware::class);
 *   $router->map(['GET', 'HEAD'], '/ping', [health_controller::class, 'ping']);
 *   $router->group('/api/v1', function(Router $r) {
 *       $r->get('/posts', [PostController::class, 'index']);
 *   }, middleware: [AuthMiddleware::class]);
 *
 * Testing: use App::testInstance() which creates a fresh router.
 *
 * #AI:class
 */
class Router {
    private array $routes       = [];
    private array $named        = [];
    private array $commands     = [];
    private array $groupStack  = [];

    private ?Dispatcher $dispatcher = null;
    private mixed $mutationGuard = null;

    private static function convertParams(string $pattern): string {
        return (string) preg_replace_callback(
            '/@([a-zA-Z_][a-zA-Z0-9_]*)(?::([a-z]+))?/',
            function(array $m): string {
                $name  = $m[1];
                $token = $m[2] ?? '';
                $regex = match ($token) {
                    'int' => '\d+',
                    'str' => '[a-zA-Z0-9\-]+',
                    'any' => '.+',
                    default => '[^/]+',
                };
                return "{{$name}:{$regex}}";
            },
            $pattern,
        );
    }

    /**
     * Registers a GET route. #AI:get
     *
     * @param string         $pattern    URL pattern with optional @param tokens.
     * @param array|callable $handler    Controller reference or closure.
     * @param array          $middleware Route-level middleware classes.
     */
    public function get(string $pattern, array|callable $handler, array $middleware = []): \Skim\Core\RouteEntry {
        return $this->add('GET', $pattern, $handler)->middleware(...$middleware);
    }

    /**
     * Registers a POST route. #AI:post
     *
     * @param string         $pattern    URL pattern with optional @param tokens.
     * @param array|callable $handler    Controller reference or closure.
     * @param array          $middleware Route-level middleware classes.
     */
    public function post(string $pattern, array|callable $handler, array $middleware = []): \Skim\Core\RouteEntry {
        return $this->add('POST', $pattern, $handler)->middleware(...$middleware);
    }

    /**
     * Registers a PUT route. #AI:put
     *
     * @param string         $pattern    URL pattern with optional @param tokens.
     * @param array|callable $handler    Controller reference or closure.
     * @param array          $middleware Route-level middleware classes.
     */
    public function put(string $pattern, array|callable $handler, array $middleware = []): \Skim\Core\RouteEntry {
        return $this->add('PUT', $pattern, $handler)->middleware(...$middleware);
    }

    /**
     * Registers a PATCH route. #AI:patch
     *
     * @param string         $pattern    URL pattern with optional @param tokens.
     * @param array|callable $handler    Controller reference or closure.
     * @param array          $middleware Route-level middleware classes.
     */
    public function patch(string $pattern, array|callable $handler, array $middleware = []): \Skim\Core\RouteEntry {
        return $this->add('PATCH', $pattern, $handler)->middleware(...$middleware);
    }

    /**
     * Registers a DELETE route. #AI:delete
     *
     * @param string         $pattern    URL pattern with optional @param tokens.
     * @param array|callable $handler    Controller reference or closure.
     * @param array          $middleware Route-level middleware classes.
     */
    public function delete(string $pattern, array|callable $handler, array $middleware = []): \Skim\Core\RouteEntry {
        return $this->add('DELETE', $pattern, $handler)->middleware(...$middleware);
    }

    /**
     * Registers a route for all HTTP methods. #AI:any
     *
     * @param string         $pattern    URL pattern with optional @param tokens.
     * @param array|callable $handler    Controller reference or closure.
     * @param array          $middleware Route-level middleware classes.
     */
    public function any(string $pattern, array|callable $handler, array $middleware = []): \Skim\Core\RouteEntry {
        return $this->add(['GET','POST','PUT','PATCH','DELETE'], $pattern, $handler)->middleware(...$middleware);
    }

    /**
     * Registers one or more HTTP methods on a single pattern. #AI:map
     *
     * Slim/Laravel-style alias for {@see self::add()}. Use when mirroring
     * framework-style code (e.g. GET + HEAD sharing one handler) or when the
     * method set is dynamic. For single-method routes prefer the explicit
     * get()/post()/put()/patch()/delete() helpers; for "all methods" use
     * the hard-coded {@see self::any()}.
     *
     * Example:
     *   $router->map(['GET', 'HEAD'], '/ping', [health_controller::class, 'ping']);
     *
     * @param string|array   $methods HTTP method(s) — one method ('GET') or a list (['GET','POST']).
     * @param string         $pattern URL pattern with optional @param tokens.
     * @param array|callable $handler Controller reference or closure.
     * @throws \LogicException If the mutation guard blocks the call.
     */
    public function map(string|array $methods, string $pattern, array|callable $handler): \Skim\Core\RouteEntry {
        return $this->add($methods, $pattern, $handler);
    }

    /**
     * Installs a mutation guard callback. #AI:setMutationGuard
     *
     * The guard returns false when route mutation should be blocked (after freeze).
     *
     * @param callable $guard Returns true when mutation is allowed.
     */
    public function setMutationGuard(callable $guard): void {
        $this->mutationGuard = $guard;
    }

    /**
     * Registers a route for one or more HTTP methods. #AI:add
     *
     * Invalidates the compiled dispatcher — next dispatch() recompiles. Returns
     * a route_entry for fluent chaining (.name(), .middleware()).
     *
     * Example:
     *   $router->add(['GET', 'POST'], '/submit', [form_controller::class, 'handle']);
     *
     * @param string|array   $methods HTTP method(s).
     * @param string         $pattern URL pattern with optional @param tokens.
     * @param array|callable $handler Controller reference or closure.
     * @throws \LogicException If the mutation guard blocks the call.
     */
    public function add(string|array $methods, string $pattern, array|callable $handler): \Skim\Core\RouteEntry {
        $this->assertMutable();

        $currentPrefix     = $this->currentPrefix();
        $currentMiddleware = $this->currentGroupMiddleware();

        $fullPattern   = $currentPrefix . self::convertParams($pattern);
        $methods        = (array) $methods;
        $entry          = new \Skim\Core\RouteEntry(implode('|', $methods), $fullPattern, $handler, $this);

        $this->routes[] = [
            'methods'    => $methods,
            'pattern'    => $fullPattern,
            'handler'    => $handler,
            'middleware' => $currentMiddleware,
            'entry'      => $entry,
        ];

        $this->dispatcher = null;

        return $entry;
    }

    /**
     * Groups routes under a shared prefix and middleware stack. #AI:group
     *
     * Groups can be nested — prefixes and middleware stack additively. The
     * callback receives the router instance for registering routes inside.
     *
     * Example:
     *   $router->group('/api/v1', function(Router $r) {
     *       $r->get('/users', [UserController::class, 'index']);
     *   }, middleware: [AuthMiddleware::class]);
     *
     * @param string   $prefix     URL prefix prepended to all routes in the group.
     * @param callable $callback   Receives the router for route registration.
     * @param array    $middleware Middleware classes applied to all group routes.
     * @throws \LogicException If the mutation guard blocks the call.
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void {
        $this->assertMutable();

        $this->groupStack[] = [
            'prefix'     => $this->currentPrefix() . $prefix,
            'middleware' => array_merge($this->currentGroupMiddleware(), $middleware),
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Registers a CLI command route. #AI:command
     *
     * Dispatched by bin/skim when argv[1] matches $name.
     *
     * @param string         $name    Command name (e.g. 'migrate', 'cache:clear').
     * @param array|callable $handler Command handler.
     * @throws \LogicException If the mutation guard blocks the call.
     */
    public function command(string $name, array|callable $handler): void {
        $this->assertMutable();
        $this->commands[$name] = $handler;
    }

    /**
     * Registers a name-to-pattern mapping for URL generation. #AI:registerName
     *
     * Called automatically by RouteEntry::name(). Overwrites previous mappings.
     *
     * @param string $name    Route name.
     * @param string $pattern URL pattern with {param:regex} placeholders.
     * @throws \LogicException If the mutation guard blocks the call.
     */
    public function registerName(string $name, string $pattern): void {
        $this->assertMutable();
        $this->named[$name] = $pattern;
    }

    /**
     * Generates a URL from a named route and parameter values. #AI:url
     *
     * Static entry point that resolves the router from the app singleton.
     * Replaces {name:regex} segments with values from $params.
     *
     * Example:
     *   Router::url('user.show', ['id' => 5]) // → /users/5
     *
     * @param string $name   Registered route name.
     * @param array  $params Key-value pairs for route placeholders.
     * @throws \InvalidArgumentException If name is not registered or params are missing.
     */
    public static function url(string $name, array $params = []): string {
        $instance = \Skim\Core\App::instance()->get('sys.router');
        if (!$instance instanceof self) {
            throw new \RuntimeException('Router not available in container.');
        }
        return $instance->buildUrl($name, $params);
    }

    /**
     * Builds a URL from a named route (instance method). #AI:buildUrl
     *
     * Replaces {param:regex} and {param} segments with values from $params.
     *
     * @param string $name   Registered route name.
     * @param array  $params Key-value pairs for route placeholders.
     * @throws \InvalidArgumentException If name is not registered or params are missing.
     */
    public function buildUrl(string $name, array $params = []): string {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("Route '{$name}' not found.");
        }
        $pattern = $this->named[$name];
        $url = (string) preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/',
            function(array $m) use ($params): string {
                $key = $m[1];
                if (!array_key_exists($key, $params)) {
                    throw new \InvalidArgumentException("Missing route param '{$key}'.");
                }
                return (string) $params[$key];
            },
            $pattern,
        );
        return $url;
    }

    /**
     * Dispatches method + path against the compiled route table. #AI:dispatch
     *
     * Compiles routes on first call. Returns route info on match, null on 404,
     * false on 405 (method not allowed).
     *
     * @param string $method HTTP method.
     * @param string $path   Request path.
     * @return array|null|false Route info array, null (404), or false (405).
     */
    public function dispatch(string $method, string $path): array|null|false {
        $this->compile();

        $info = $this->dispatcher->dispatch($method, $path);

        return match ($info[0]) {
            Dispatcher::FOUND     => [
                'handler'    => $info[1]['handler'],
                'params'     => $info[2],
                'middleware' => $info[1]['middleware'] ?? [],
                'pattern'    => null,
            ],
            Dispatcher::NOT_FOUND         => null,
            Dispatcher::METHOD_NOT_ALLOWED => false,
            default                        => null,
        };
    }

    /**
     * Returns the original (un-compiled) pattern registered for a handler+method. #AI:pattern_for_handler
     *
     * Used by the trace recorder to publish the readable pattern like `/users/@id:int`
     * rather than the compiled fast-route regex form. Returns null when no matching
     * route is found in the registration list.
     */
    private function patternForHandler(mixed $handler, string $method): ?string {
        foreach ($this->routes as $r) {
            $methods = (array) $r['methods'];
            if ($r['handler'] === $handler && in_array($method, $methods, true)) {
                return $r['entry']->pattern;
            }
        }
        return null;
    }

    /**
     * Dispatches a CLI command by name. #AI:dispatchCommand
     *
     * Returns handler info on match, null if the command is not registered.
     *
     * @param string $name Command name from argv[1].
     */
    public function dispatchCommand(string $name): array|null {
        if (!isset($this->commands[$name])) {
            return null;
        }
        return ['handler' => $this->commands[$name], 'params' => [], 'middleware' => []];
    }

    private function compile(): void {
        if ($this->dispatcher !== null) {
            return;
        }

        $routes = $this->routes;

        $hasClosures = false;
        foreach ($routes as $route) {
            if ($route['handler'] instanceof \Closure) {
                $hasClosures = true;
                break;
            }
        }

        $cacheFile = null;
        if (!$hasClosures && \Skim\Core\Config::get('app.env') === 'production') {
            $cacheFile = storagePath('cache/routes.php');
            if (!is_dir(dirname($cacheFile))) {
                $cacheFile = null;
            }
        }

        $dispatcherCallback = function(RouteCollector $r) use ($routes): void {
            foreach ($routes as $route) {
                foreach ($route['methods'] as $method) {
                    $r->addRoute($method, $route['pattern'], [
                        'handler'    => $route['handler'],
                        'middleware' => array_merge(
                            $route['middleware'],
                            $route['entry']->getMiddleware(),
                        ),
                    ]);
                }
            }
        };

        if ($cacheFile) {
            $this->dispatcher = cachedDispatcher($dispatcherCallback, ['cacheFile' => $cacheFile]);
        } else {
            $this->dispatcher = simpleDispatcher($dispatcherCallback);
        }
    }

    private function currentPrefix(): string {
        return empty($this->groupStack) ? '' : end($this->groupStack)['prefix'];
    }

    private function currentGroupMiddleware(): array {
        return empty($this->groupStack) ? [] : end($this->groupStack)['middleware'];
    }

    private function assertMutable(): void {
        if ($this->mutationGuard !== null && !($this->mutationGuard)()) {
            throw new \LogicException('Cannot modify routes after app is frozen.');
        }
    }
}

#AI:class
#AI symbol: Skim\Core\Router
#AI source_path: src/Core/Router.php
#AI title: router
#AI description: HTTP and CLI router wrapping nikic/fast-route with compiled regex dispatch, F3-compatible @param tokens, and route groups.
#AI role: route registrar and dispatcher
#AI layer: core
#AI badges: [router; fast-route; dispatch; named-routes; groups; cli]
#AI intro: `Skim\Core\Router` wraps nikic/fast-route to compile all routes into a single regex on first dispatch. It supports F3-compatible @param token syntax (@id, @id:int, @slug:str, @any), route groups with additive prefix and middleware, named routes for reverse URL generation, and CLI command routing.
#AI lifecycle: created during app boot, routes registered before freeze(), compiled on first dispatch
#AI test_seam: App::testInstance() creates a fresh router; dispatch() can be called directly in tests
#AI invariants: [routes compiled lazily on first dispatch(); add() invalidates compiled dispatcher; groups nest additively; mutation guard blocks changes after freeze(); @param tokens converted to fast-route regex]
#AI warnings: [Route registration after freeze() throws LogicException; url() requires the app singleton to be available]
#AI notes: Why fast-route over F3's router: compiles all routes into one regex, orders of magnitude faster than per-route string matching.
#AI owns: routes, named routes, CLI commands, group stack, compiled dispatcher, mutation guard
#AI entry_points: [get; post; put; patch; delete; any; map; add; group; command; dispatch; url]
#AI config_reads: []
#AI non_goals: [Does not handle middleware execution (delegated to pipeline); Does not resolve controller dependencies (delegated to App::callHandler)]
#AI side_effects: [add() invalidates compiled dispatcher; group() pushes/pops group stack; compile() creates fast-route dispatcher]
#AI flow: register routes -> compile() on first dispatch() -> fast-route regex match -> return handler/params/middleware
#AI section_order: [Route Registration; HTTP Methods; Groups & Commands; Named Routes; Dispatch; Internals]
#AI architectural_notes: The router is intentionally a thin registration layer over fast-route. All @param token conversion happens at registration time. The compiled dispatcher is cached and invalidated on any route mutation. Groups use a stack to support arbitrary nesting depth.

#AI:get
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function get(string $pattern, array|callable $handler, array $middleware = []): RouteEntry
#AI contract: Registers a GET route. Delegates to add().
#AI param_details: [{name: $pattern | type: string | required: true | desc: URL pattern with optional @param tokens.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}; {name: $middleware | type: array | required: false | desc: Route-level middleware classes.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}

#AI:post
#AI group: HTTP Methods
#AI frequency: high
#AI signature: public function post(string $pattern, array|callable $handler, array $middleware = []): RouteEntry
#AI contract: Registers a POST route. Delegates to add().
#AI param_details: [{name: $pattern | type: string | required: true | desc: URL pattern with optional @param tokens.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}; {name: $middleware | type: array | required: false | desc: Route-level middleware classes.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}

#AI:put
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function put(string $pattern, array|callable $handler, array $middleware = []): RouteEntry
#AI contract: Registers a PUT route. Delegates to add().
#AI param_details: [{name: $pattern | type: string | required: true | desc: URL pattern.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}; {name: $middleware | type: array | required: false | desc: Route-level middleware.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}

#AI:patch
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function patch(string $pattern, array|callable $handler, array $middleware = []): RouteEntry
#AI contract: Registers a PATCH route. Delegates to add().
#AI param_details: [{name: $pattern | type: string | required: true | desc: URL pattern.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}; {name: $middleware | type: array | required: false | desc: Route-level middleware.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}

#AI:delete
#AI group: HTTP Methods
#AI frequency: medium
#AI signature: public function delete(string $pattern, array|callable $handler, array $middleware = []): RouteEntry
#AI contract: Registers a DELETE route. Delegates to add().
#AI param_details: [{name: $pattern | type: string | required: true | desc: URL pattern.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}; {name: $middleware | type: array | required: false | desc: Route-level middleware.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}

#AI:any
#AI group: HTTP Methods
#AI frequency: low
#AI signature: public function any(string $pattern, array|callable $handler, array $middleware = []): RouteEntry
#AI contract: Registers a route for all HTTP methods (GET, POST, PUT, PATCH, DELETE).
#AI param_details: [{name: $pattern | type: string | required: true | desc: URL pattern.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}; {name: $middleware | type: array | required: false | desc: Route-level middleware.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}

#AI:map
#AI group: HTTP Methods
#AI frequency: low
#AI signature: public function map(string|array $methods, string $pattern, array|callable $handler): RouteEntry
#AI contract: Registers a route for one or more HTTP methods. Thin alias for add() — provides Slim/Laravel-style map() for compatibility with code that expects that convention.
#AI param_details: [{name: $methods | type: string|array | required: true | desc: One HTTP method ('GET') or a list (['GET','POST']).}; {name: $pattern | type: string | required: true | desc: URL pattern with optional @param tokens.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}
#AI throws_details: [{type: \LogicException | desc: If the mutation guard blocks the call.}]
#AI notes: Prefer get()/post()/put()/patch()/delete() for single-method routes and any() for all-method routes. Use map() when the method set is dynamic or when mirroring Slim/Laravel-style code.

#AI:setMutationGuard
#AI group: Route Registration
#AI frequency: internal
#AI signature: public function setMutationGuard(callable $guard): void
#AI contract: Installs a callback that returns false when route mutation should be blocked.
#AI param_details: [{name: $guard | type: callable | required: true | desc: Returns true when mutation is allowed.}]

#AI:add
#AI group: Route Registration
#AI frequency: high
#AI signature: public function add(string|array $methods, string $pattern, array|callable $handler): RouteEntry
#AI contract: Registers a route for one or more HTTP methods. Converts @param tokens, applies group prefix and middleware, invalidates the compiled dispatcher.
#AI param_details: [{name: $methods | type: string|array | required: true | desc: HTTP method(s).}; {name: $pattern | type: string | required: true | desc: URL pattern with optional @param tokens.}; {name: $handler | type: array|callable | required: true | desc: Controller reference or closure.}]
#AI return_detail: {type: RouteEntry | desc: Fluent route configuration object.}
#AI throws_details: [{type: \LogicException | desc: If the mutation guard blocks the call.}]
#AI side_effects: [Invalidates compiled dispatcher; Appends to routes array]

#AI:group
#AI group: Groups & Commands
#AI frequency: high
#AI signature: public function group(string $prefix, callable $callback, array $middleware = []): void
#AI contract: Groups routes under a shared prefix and middleware stack. Groups nest additively.
#AI param_details: [{name: $prefix | type: string | required: true | desc: URL prefix prepended to all routes in the group.}; {name: $callback | type: callable | required: true | desc: Receives the router for route registration.}; {name: $middleware | type: array | required: false | desc: Middleware classes applied to all group routes.}]
#AI throws_details: [{type: \LogicException | desc: If the mutation guard blocks the call.}]

#AI:command
#AI group: Groups & Commands
#AI frequency: medium
#AI signature: public function command(string $name, array|callable $handler): void
#AI contract: Registers a CLI command route dispatched by bin/skim when argv[1] matches.
#AI param_details: [{name: $name | type: string | required: true | desc: Command name.}; {name: $handler | type: array|callable | required: true | desc: Command handler.}]
#AI throws_details: [{type: \LogicException | desc: If the mutation guard blocks the call.}]

#AI:registerName
#AI group: Named Routes
#AI frequency: internal
#AI signature: public function registerName(string $name, string $pattern): void
#AI contract: Registers a name-to-pattern mapping for reverse URL generation. Called by RouteEntry::name().
#AI param_details: [{name: $name | type: string | required: true | desc: Route name.}; {name: $pattern | type: string | required: true | desc: URL pattern with placeholders.}]
#AI throws_details: [{type: \LogicException | desc: If the mutation guard blocks the call.}]

#AI:url
#AI group: Named Routes
#AI frequency: high
#AI signature: public static function url(string $name, array $params = []): string
#AI contract: Static entry point that resolves the router from the app singleton and generates a URL from a named route.
#AI param_details: [{name: $name | type: string | required: true | desc: Registered route name.}; {name: $params | type: array | required: false | desc: Key-value pairs for route placeholders.}]
#AI return_detail: {type: string | desc: Generated URL path.}
#AI throws_details: [{type: \InvalidArgumentException | desc: If name is not registered or params are missing.}; {type: \RuntimeException | desc: If router is not available in container.}]

#AI:buildUrl
#AI group: Named Routes
#AI frequency: medium
#AI signature: public function buildUrl(string $name, array $params = []): string
#AI contract: Instance method that replaces {param:regex} segments with values from $params.
#AI param_details: [{name: $name | type: string | required: true | desc: Registered route name.}; {name: $params | type: array | required: false | desc: Key-value pairs for placeholders.}]
#AI return_detail: {type: string | desc: Generated URL path.}
#AI throws_details: [{type: \InvalidArgumentException | desc: If name not found or params missing.}]

#AI:dispatch
#AI group: Dispatch
#AI frequency: high
#AI signature: public function dispatch(string $method, string $path): array|null|false
#AI contract: Compiles routes on first call, then dispatches method+path against the fast-route dispatcher. Returns route info on match, null on 404, false on 405.
#AI param_details: [{name: $method | type: string | required: true | desc: HTTP method.}; {name: $path | type: string | required: true | desc: Request path.}]
#AI return_detail: {type: array|null|false | desc: Route info {handler, params, middleware} on match, null (404), or false (405).}

#AI:dispatchCommand
#AI group: Dispatch
#AI frequency: medium
#AI signature: public function dispatchCommand(string $name): array|null
#AI contract: Dispatches a CLI command by name. Returns handler info or null if not registered.
#AI param_details: [{name: $name | type: string | required: true | desc: Command name from argv[1].}]
#AI return_detail: {type: array|null | desc: Handler info or null.}
