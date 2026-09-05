<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * Middleware contract — every middleware must implement this interface.
 *
 * Use when you need to intercept a request before it reaches the controller,
 * or modify the response after the controller runs. Execution follows the onion
 * model: the outermost (first-registered) middleware runs first, calls $next to
 * pass control inward, and receives the response on the way back out. A middleware
 * that returns without calling $next short-circuits the entire chain.
 *
 * Example:
 *   class AuthMiddleware implements Middleware {
 *       public function handle(Request $req, Response $res, callable $next): mixed {
 *           if (!Session::has('user_id')) {
 *               return $res->status(401)->json(['error' => 'Unauthorized']);
 *           }
 *           return $next($req, $res);
 *       }
 *   }
 *
 * Testing: implement the interface in a test double and assert whether $next was called.
 *
 * #AI:interface
 */
interface Middleware {
    /**
     * Process the request/response pair and decide whether to continue the chain. #AI:handle
     *
     * Call $next($req, $res) to pass control to the next middleware (and eventually
     * the controller). Return a response directly to short-circuit (e.g., 401 from
     * authentication, 403 from authorization). Both the request and response may be
     * mutated before being passed to $next.
     *
     * @param \Skim\Core\Request $req The current request. May be mutated before passing to $next.
     * @param \Skim\Core\Response $res The current response. May be mutated before passing to $next.
     * @param callable $next The next middleware or terminal handler. Signature:
     *                       (request, response): mixed.
     * @return mixed Response value — either the result of $next($req, $res) or a
     *               short-circuit response.
     */
    public function handle(\Skim\Core\Request $req, \Skim\Core\Response $res, callable $next): mixed;
}

#AI:interface
#AI symbol: Skim\Core\Middleware
#AI source_path: src/Core/Middleware.php
#AI title: middleware
#AI description: Middleware contract for the onion-model request/response pipeline.
#AI role: middleware contract
#AI layer: core
#AI badges: [interface; middleware; pipeline]
#AI intro: `Skim\Core\Middleware` is the contract every middleware must implement. The pipeline resolves each entry, calls `handle()` in onion order, and expects either `$next(...)` to continue or a direct response to short-circuit.
#AI flow: handle($req, $res, $next) -> call $next() to continue | return response to short-circuit
#AI section_order: [Contract]
#AI architectural_notes: Middleware ordering: global ($app->use()) runs first, then group middleware, then route middleware. Each middleware sees the same request and response object — mutation propagates inward. For outward propagation, wrap the $next call and capture/post-process its return value.

#AI:handle
#AI group: Contract
#AI frequency: high
#AI signature: public function handle(Request $req, Response $res, callable $next): mixed
#AI contract: Process the request/response pair. Call $next to continue the chain; return a response to short-circuit. Mutations to $req and $res propagate through the chain.
#AI param_details: [{name: $req | type: request | required: true | desc: Current request, mutable before passing to $next}; {name: $res | type: response | required: true | desc: Current response, mutable before passing to $next}; {name: $next | type: callable | required: true | desc: Next middleware or terminal handler, signature (request, response): mixed}]
#AI return_detail: {type: mixed | desc: Response from $next or short-circuit value.}
#AI side_effects: May mutate $req and $res. Short-circuiting skips the controller entirely.
