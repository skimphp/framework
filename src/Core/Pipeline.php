<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * Builds and executes the middleware chain using the onion model.
 *
 * Use when dispatching a request through a stack of middleware before reaching
 * a controller. The pipeline reverses the middleware list internally so that
 * the first-registered middleware becomes the outermost wrapper — it runs first,
 * calls $next to descend, and receives the response on the way back out. A
 * middleware that returns without calling $next short-circuits the chain.
 *
 * Example:
 *   $res = (new Pipeline())->run(
 *       $req, $res,
 *       middlewares: [Cors::class, AuthMiddleware::class],
 *       core: fn(Request $req, Response $res) => $controller->handle($req, $res),
 *   );
 *
 * Testing: instantiate pipeline directly with test doubles as middlewares and a
 * callable core; assert the final response value.
 *
 * #AI:class
 */
class Pipeline {
    /** @var array<class-string, \Skim\Core\Middleware> */
    private static array $instanceCache = [];
    /**
     * Clears the middleware singleton cache. Called between requests in worker mode.
     *
     * Middleware instances should be stateless; clearing the cache prevents
     * request-scoped state from leaking across requests while still allowing
     * process-lifetime caching to be rebuilt lazily.
     *
     * #AI:reset_instance_cache
     */
    public static function resetInstanceCache(): void {
        self::$instanceCache = [];
    }

    /**
     * Executes the middleware chain and returns the result. #AI:run
     *
     * Resolves each middleware entry to a concrete instance, builds the chain from
     * innermost to outermost, then invokes the chain with the given request and
     * response. The terminal callable ($core) is only reached if no middleware
     * short-circuits.
     *
     * @param \Skim\Core\Request $req The incoming request.
     * @param \Skim\Core\Response $res The mutable response object.
     * @param array    $middlewares Ordered list of middleware entries. Each entry
     *                              is either a class-string, a middleware instance,
     *                              or ['class' => ..., 'args' => [...]].
     * @param callable $core        Terminal handler (typically the controller).
     *                              Signature: (request, response): mixed.
     * @return mixed Whatever the terminal handler or a short-circuit middleware returns.
     */
    public function run(
        \Skim\Core\Request  $req,
        \Skim\Core\Response $res,
        array    $middlewares,
        callable $core,
    ): mixed {
        $chain = $this->build($middlewares, $core);
        return $chain($req, $res);
    }

    /**
     * Emits one timeline event summarising the middleware stack for this request. #AI:record_pipeline_trace
     *
     * Called at the top of run() so the trace always carries a 'middleware_ran' event
     * with the full class list (in execution order) and a synthetic marker identifying
     * the innermost entry. Read by the error page's Request panel and the toolbar.
     *
     * No-op when request_trace is disabled — never throws.
     */
    public function recordPipelineTrace(\Skim\Core\Request $req, array $middlewares): void {
        if (!\Skim\Dev\RequestTrace::isEnabled()) {
            return;
        }

        $classes = array_values(array_map(
            static fn(string|array|\Skim\Core\Middleware $entry): string
                => match (true) {
                    $entry instanceof \Skim\Core\Middleware => $entry::class,
                    is_string($entry)            => $entry,
                    default                      => (string) ($entry['class'] ?? '?'),
                },
            $middlewares,
        ));

        \Skim\Dev\RequestTrace::event('middleware_ran', [
            'method' => $req->method(),
            'path'   => $req->path(),
            'stack'  => $classes,
        ]);
    }

    /**
     * Builds the chain from innermost to outermost. #AI:build
     *
     * Starts with $core as the innermost callable, then wraps it with each
     * middleware in reverse order. The result is a single closure where the
     * first middleware in the list is the outermost wrapper.
     *
     * @param array    $middlewares Ordered middleware entries.
     * @param callable $core        Terminal handler.
     * @return callable Composed chain: (request, response): mixed.
     */
    private function build(array $middlewares, callable $core): callable {
        // Build from end to start — last middleware in list wraps the core first,
        // so that when called the first middleware runs first (correct onion order).
        $chain = $core;

        foreach (array_reverse($middlewares) as $entry) {
            $instance = $this->resolve($entry);
            $next     = $chain;

            $chain = function(\Skim\Core\Request $req, \Skim\Core\Response $res) use ($instance, $next): mixed {
                return $instance->handle($req, $res, $next);
            };
        }

        return $chain;
    }

    /**
     * Resolves a middleware entry to a concrete instance. #AI:resolve
     *
     * Accepts three formats:
     * - middleware instance: returned as-is.
     * - class-string: instantiated with no arguments via `new $class()`.
     * - array with 'class' and optional 'args': instantiated with constructor args.
     *
     * @param string|array|\Skim\Core\Middleware $entry Middleware entry.
     * @return \Skim\Core\Middleware Resolved middleware instance.
     */
    private function resolve(string|array|\Skim\Core\Middleware $entry): \Skim\Core\Middleware {
        if ($entry instanceof \Skim\Core\Middleware) {
            return $entry;
        }
        if (is_string($entry)) {
            return self::$instanceCache[$entry] ??= new $entry();
        }

        // ['class' => ..., 'args' => [...]]
        $class = $entry['class'];
        $args  = $entry['args'] ?? [];
        return new $class(...$args);
    }
}

#AI:class
#AI symbol: Skim\Core\Pipeline
#AI source_path: src/Core/Pipeline.php
#AI title: pipeline
#AI description: Middleware chain builder and executor using the onion model.
#AI role: middleware pipeline
#AI layer: core
#AI badges: [pipeline; middleware; onion-model]
#AI intro: `Skim\Core\Pipeline` builds a composed callable from an ordered list of middleware entries and a terminal handler. The list is reversed internally so execution follows FIFO order: first-registered = first to run.
#AI flow: run($req, $res, $middlewares, $core) -> build($middlewares, $core) -> resolve() each entry -> reversed closure chain -> invoke chain($req, $res)
#AI lifecycle: Fresh pipeline instance per request — no mutable state is retained between calls.
#AI invariants: [middleware list is reversed internally for correct onion order; short-circuit skips $core entirely; resolve() supports instance, class-string, and factory-array formats]
#AI test_seam: Instantiate pipeline directly; pass test doubles and assert invocation order or short-circuit behavior.
#AI section_order: [Execution; Internals]
#AI architectural_notes: The pipeline is stateless — all three methods are pure functions of their inputs. The `resolve()` method does not use the DI container; middleware resolution is intentionally direct instantiation for clarity and simplicity.

#AI:run
#AI group: Execution
#AI frequency: high
#AI signature: public function run(Request $req, Response $res, array $middlewares, callable $core): mixed
#AI contract: Resolves and chains middlewares, invokes the composed chain, and returns the terminal or short-circuit response.
#AI param_details: [{name: $req | type: request | required: true | desc: Incoming request}; {name: $res | type: response | required: true | desc: Mutable response}; {name: $middlewares | type: array | required: true | desc: Ordered list of class-string, instance, or factory-array entries}; {name: $core | type: callable | required: true | desc: Terminal handler, signature (request, response): mixed}]
#AI return_detail: {type: mixed | desc: Response from terminal handler or short-circuit middleware.}

#AI:build
#AI group: Internals
#AI frequency: internal
#AI signature: private function build(array $middlewares, callable $core): callable
#AI contract: Reverses the middleware list and wraps $core with each resolved instance from innermost to outermost.
#AI param_details: [{name: $middlewares | type: array | required: true | desc: Ordered middleware entries}; {name: $core | type: callable | required: true | desc: Terminal handler}]
#AI return_detail: {type: callable | desc: Composed closure with signature (request, response): mixed.}

#AI:resolve
#AI group: Internals
#AI frequency: internal
#AI signature: private function resolve(string|array|Middleware $entry): middleware
#AI contract: Normalizes a middleware entry to a concrete middleware instance. Accepts instances (returned as-is), class-strings (instantiated via new), and factory arrays ['class' => ..., 'args' => [...]].
#AI param_details: [{name: $entry | type: string|array|middleware | required: true | desc: Entry in one of three supported formats}]
#AI return_detail: {type: middleware | desc: Resolved middleware instance.}
