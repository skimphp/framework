<?php declare(strict_types=1);

namespace skim\core;

// Middleware contract. Every middleware must implement this interface.
//
// Execution flow: handle() receives request, response, and $next.
// - Call $next($req, $res) to continue the chain → controller eventually runs.
// - Return without calling $next to short-circuit → controller is skipped.
//
// Order: global → group → route (registered at app::use() / group() / route_entry::middleware())
interface middleware {
    /**
     * @ai-contract $next is the next middleware or the route handler
     * @ai-contract return $next($req, $res) to continue the chain
     * @ai-contract return response directly to short-circuit (e.g. 401 from auth)
     */
    public function handle(request $req, response $res, callable $next): mixed;
}
