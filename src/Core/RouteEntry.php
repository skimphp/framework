<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * Fluent route registration result returned by Router::add() and App::get() / post() / etc.
 *
 * Use when you need to configure a freshly registered route: assign a name for
 * reverse URL generation, or attach route-scoped middleware. Each method returns
 * $this for fluent chaining. The route_entry is created internally by the router
 * and returned from every HTTP-method registration call.
 *
 * Example:
 *   $app->get('/users/@id:int', [UserController::class, 'show'])
 *       ->name('user.show')
 *       ->middleware(AuthMiddleware::class, RateLimitMiddleware::class);
 *
 * Testing: route_entry is a value object consumed by Router::dispatch() — test
 * the chaining API separately or through route registration assertions.
 *
 * #AI:class
 */
class RouteEntry {
    private ?string $name       = null;
    private array   $middleware = [];

    public function __construct(
        public readonly string       $method,
        public readonly string       $pattern,
        public readonly mixed        $handler,
        private readonly \Skim\Core\Router      $router,
    ) {}

    /**
     * Assigns a unique name for reverse URL generation. #AI:name
     *
     * The name is registered with the router so route('user.show', ['id' => 5])
     * resolves to '/users/5'. Overwrites any previous registration for the same
     * name — the last call wins.
     *
     * Example:
     *   $route->name('user.show');
     *   // Later: route('user.show', ['id' => 5]) -> '/users/5'
     *
     * @param string $name Unique route name. Must be non-empty. Overwrites duplicates.
     * @return $this For fluent chaining.
     */
    public function name(string $name): static {
        $this->name = $name;
        $this->router->registerName($name, $this->pattern);
        return $this;
    }

    /**
     * Appends middleware classes to this route's execution stack. #AI:middleware
     *
     * Route-level middleware runs AFTER global and group middleware. Each call
     * merges with previously appended classes — middleware is additive, not
     * replacing. The order within route middleware matches the call order.
     *
     * Example:
     *   $route->middleware(LogMiddleware::class);
     *   $route->middleware(CacheMiddleware::class); // log runs first, then cache
     *
     * @param string ...$classes Middleware class-strings to append.
     * @return $this For fluent chaining.
     */
    public function middleware(string ...$classes): static {
        $this->middleware = array_merge($this->middleware, $classes);
        return $this;
    }

    /**
     * Returns the assigned route name, or null if not set. #AI:getName
     *
     * @return string|null The route name, or null if name() was never called.
     */
    public function getName(): ?string {
        return $this->name;
    }

    /**
     * Returns all appended middleware class-strings. #AI:getMiddleware
     *
     * @return array Ordered list of middleware class-strings.
     */
    public function getMiddleware(): array {
        return $this->middleware;
    }
}

#AI:class
#AI symbol: Skim\Core\RouteEntry
#AI source_path: src/Core/RouteEntry.php
#AI title: RouteEntry
#AI description: Fluent route configuration object returned by router registration methods.
#AI role: route configuration builder
#AI layer: core
#AI badges: [fluent; route; middleware; named-route]
#AI intro: `Skim\Core\RouteEntry` is the return value of every `Router::add()` and `App::get()/post()/put()/patch()/delete()` call. It exposes two fluent configuration methods — `name()` and `middleware()` — and two read-only accessors consumed internally by the router during dispatch.
#AI flow: Router::add() -> new RouteEntry -> name() registers reverse URL -> middleware() appends to stack -> dispatch reads getName() and getMiddleware()
#AI lifecycle: Created fresh per route registration. Passed around by reference (object, not copy) until consumed by Router::dispatch().
#AI test_seam: Instantiate with a router mock; assert name() calls registerName() on the router; assert getMiddleware() returns accumulated classes.
#AI invariants: [name() overwrites duplicate names silently; middleware() is additive, never replacing; getName() returns null when name() was never called]
#AI section_order: [Configuration; Accessors]
#AI architectural_notes: RouteEntry is intentionally minimal — it captures only what needs to be set after route registration. The method, pattern, and handler are constructor-promoted readonly properties; configuration happens via chained methods; state is consumed by the router at dispatch time.

#AI:name
#AI group: Configuration
#AI frequency: high
#AI signature: public function name(string $name): static
#AI contract: Assigns a unique name and registers it with the router for reverse URL generation via route(). Overwrites any previous registration for the same name.
#AI param_details: [{name: $name | type: string | required: true | desc: Unique route name. Must be non-empty. Overwrites duplicates.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}
#AI side_effects: Calls $router->registerName($name, $pattern).

#AI:middleware
#AI group: Configuration
#AI frequency: high
#AI signature: public function middleware(string ...$classes): static
#AI contract: Appends one or more middleware class-strings to this route's execution stack. Route middleware runs after global and group middleware.
#AI param_details: [{name: $classes | type: string | required: true | desc: Middleware class-strings. Each must implement the middleware interface.}]
#AI return_detail: {type: static | desc: $this for fluent chaining.}
#AI notes: middleware is additive — call it multiple times to accumulate. Order within route middleware matches call order.

#AI:getName
#AI group: Accessors
#AI frequency: low
#AI signature: public function getName(): ?string
#AI contract: Returns the assigned route name or null if name() was never called.
#AI return_detail: {type: ?string | desc: Route name or null.}

#AI:getMiddleware
#AI group: Accessors
#AI frequency: low
#AI signature: public function getMiddleware(): array
#AI contract: Returns all appended middleware class-strings in registration order.
#AI return_detail: {type: array | desc: Ordered list of middleware class-strings.}
