<?php declare(strict_types=1);

namespace skim\core;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

// Wraps nikic/fast-route — compiles all routes into one regex on first dispatch.
// Orders of magnitude faster than F3's per-route string matching loop.
//
// @param token syntax (F3 compat): @id, @id:int, @slug:str, @any
// Converted to fast-route regex groups before registration.
class router {
    private array $routes       = [];   // raw registrations before compile
    private array $named        = [];   // name → pattern map for URL generation
    private array $commands     = [];   // CLI command → handler
    private array $group_stack  = [];   // active group prefixes and middleware

    private ?Dispatcher $dispatcher = null;  // compiled on first dispatch()

    // --- F3-style @param token conversion ---

    // Converts @id → {id:[^/]+}, @id:int → {id:\d+}, @slug:str → {id:[a-zA-Z0-9\-]+}
    // Why: F3 backward compat shorthand; fast-route uses {name:regex} natively
    private static function convert_params(string $pattern): string {
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
     * @ai-contract registers a route for one or more HTTP methods
     * @ai-contract invalidates compiled dispatcher — next dispatch() recompiles
     * @ai-contract returns route_entry for fluent chaining (.name(), .middleware())
     */
    public function add(string|array $methods, string $pattern, array|callable $handler): route_entry {
        $current_prefix     = $this->current_prefix();
        $current_middleware = $this->current_group_middleware();

        $full_pattern   = $current_prefix . self::convert_params($pattern);
        $methods        = (array) $methods;
        $entry          = new route_entry(implode('|', $methods), $full_pattern, $handler, $this);

        $this->routes[] = [
            'methods'    => $methods,
            'pattern'    => $full_pattern,
            'handler'    => $handler,
            'middleware' => $current_middleware,
            'entry'      => $entry,
        ];

        $this->dispatcher = null;   // force recompile

        return $entry;
    }

    /**
     * @ai-contract executes $callback with prefix + middleware applied to all routes inside
     * @ai-contract groups can be nested — prefixes and middleware stack additively
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void {
        $this->group_stack[] = [
            'prefix'     => $this->current_prefix() . $prefix,
            'middleware' => array_merge($this->current_group_middleware(), $middleware),
        ];

        $callback($this);

        array_pop($this->group_stack);
    }

    /**
     * @ai-contract registers a CLI command route
     * @ai-contract dispatched by bin/skim when argv[1] matches $name
     */
    public function command(string $name, array|callable $handler): void {
        $this->commands[$name] = $handler;
    }

    /**
     * @ai-contract registers a name→pattern mapping for route() URL generation
     * @ai-contract called automatically by route_entry::name()
     */
    public function register_name(string $name, string $pattern): void {
        $this->named[$name] = $pattern;
    }

    /**
     * @ai-contract generates a URL from a named route and parameter values
     * @ai-contract throws \InvalidArgumentException if name not registered
     * @ai-contract replaces {name:regex} segments with values from $params
     */
    public static function url(string $name, array $params = []): string {
        // Static call — needs the singleton. Populated by register_name().
        // For tests, use router instance directly via route_entry::name().
        $instance = app::instance()->get('sys.router');
        if (!$instance instanceof self) {
            throw new \RuntimeException('Router not available in container.');
        }
        return $instance->build_url($name, $params);
    }

    public function build_url(string $name, array $params = []): string {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("Route '{$name}' not found.");
        }
        $pattern = $this->named[$name];
        // Replace {param:regex} and {param} with actual values
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
     * @ai-contract dispatches method + path against compiled route table
     * @ai-contract returns array{handler, params, middleware} on match
     * @ai-contract returns null on 404, false on 405 (method not allowed)
     */
    public function dispatch(string $method, string $path): array|null|false {
        $this->compile();

        $info = $this->dispatcher->dispatch($method, $path);

        return match ($info[0]) {
            Dispatcher::FOUND     => [
                'handler'    => $info[1]['handler'],
                'params'     => $info[2],
                'middleware' => $info[1]['middleware'] ?? [],
            ],
            Dispatcher::NOT_FOUND         => null,
            Dispatcher::METHOD_NOT_ALLOWED => false,
            default                        => null,
        };
    }

    /**
     * @ai-contract dispatches a CLI command by name, null if not registered
     */
    public function dispatch_command(string $name): array|null {
        if (!isset($this->commands[$name])) {
            return null;
        }
        return ['handler' => $this->commands[$name], 'params' => [], 'middleware' => []];
    }

    // --- internals ---

    private function compile(): void {
        if ($this->dispatcher !== null) {
            return;
        }

        $routes = $this->routes;   // capture for closure

        $this->dispatcher = simpleDispatcher(function(RouteCollector $r) use ($routes): void {
            foreach ($routes as $route) {
                foreach ($route['methods'] as $method) {
                    $r->addRoute($method, $route['pattern'], [
                        'handler'    => $route['handler'],
                        'middleware' => array_merge(
                            $route['middleware'],
                            $route['entry']->get_middleware(),
                        ),
                    ]);
                }
            }
        });
    }

    private function current_prefix(): string {
        return empty($this->group_stack) ? '' : end($this->group_stack)['prefix'];
    }

    private function current_group_middleware(): array {
        return empty($this->group_stack) ? [] : end($this->group_stack)['middleware'];
    }
}
