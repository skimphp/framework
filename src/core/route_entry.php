<?php declare(strict_types=1);

namespace skim\core;

// Fluent route registration result. Returned by router::add() and app::get/post/etc.
// Allows chaining: $app->get('/users/@id', $handler)->name('user.show')->middleware(...)
class route_entry {
    private ?string $name       = null;
    private array   $middleware = [];

    public function __construct(
        public readonly string       $method,
        public readonly string       $pattern,
        public readonly mixed $handler,
        private readonly router      $router,
    ) {}

    /**
     * @ai-contract assigns a name for reverse URL generation via route()
     * @ai-contract name must be unique — overwrites previous entry with same name
     */
    public function name(string $name): static {
        $this->name = $name;
        $this->router->register_name($name, $this->pattern);
        return $this;
    }

    /**
     * @ai-contract appends middleware classes to this route's execution stack
     * @ai-contract route middleware runs AFTER group middleware
     */
    public function middleware(string ...$classes): static {
        $this->middleware = array_merge($this->middleware, $classes);
        return $this;
    }

    public function get_name(): ?string {
        return $this->name;
    }

    public function get_middleware(): array {
        return $this->middleware;
    }
}
