<?php declare(strict_types=1);

namespace skim\core;

// Builds and executes the middleware chain.
// Chain direction: first registered = outermost wrapper = first to run.
// Each middleware wraps the next — onion model.
//
// Short-circuit: a middleware returning without calling $next stops execution.
// The controller is the innermost callable at the end of the chain.
class pipeline {
    /**
     * @ai-contract executes $middlewares as a chain, $core is the terminal handler (controller)
     * @ai-contract each $middleware entry is either a class-string or ['class' => ..., 'args' => [...]]
     * @ai-contract middlewares are resolved via the app container if available
     * @ai-contract returns mixed — whatever the terminal handler or a short-circuit middleware returns
     */
    public function run(
        request  $req,
        response $res,
        array    $middlewares,
        callable $core,
    ): mixed {
        $chain = $this->build($middlewares, $core);
        return $chain($req, $res);
    }

    private function build(array $middlewares, callable $core): callable {
        // Build from end to start — last middleware in list wraps the core first,
        // so that when called the first middleware runs first (correct onion order).
        $chain = $core;

        foreach (array_reverse($middlewares) as $entry) {
            $instance = $this->resolve($entry);
            $next     = $chain;

            $chain = function(request $req, response $res) use ($instance, $next): mixed {
                return $instance->handle($req, $res, $next);
            };
        }

        return $chain;
    }

    private function resolve(string|array|middleware $entry): middleware {
        if ($entry instanceof middleware) {
            return $entry;
        }
        if (is_string($entry)) {
            return new $entry();
        }

        // ['class' => ..., 'args' => [...]]
        $class = $entry['class'];
        $args  = $entry['args'] ?? [];
        return new $class(...$args);
    }
}
