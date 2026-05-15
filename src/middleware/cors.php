<?php declare(strict_types=1);

namespace skim\middleware;

use skim\core\middleware;
use skim\core\request;
use skim\core\response;

// CORS middleware — must run before auth middleware.
// Preflight OPTIONS requests must pass through before any auth check runs.
// Reason: browsers send OPTIONS without auth headers; blocking them breaks CORS.
class cors implements middleware {
    public function __construct(
        private readonly string $allow_origin  = '*',
        private readonly string $allow_methods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private readonly string $allow_headers = 'Content-Type, Authorization, X-Requested-With',
        private readonly int    $max_age       = 86400,
    ) {}

    /**
     * @ai-contract adds CORS headers to every response
     * @ai-contract short-circuits on OPTIONS preflight — returns 204 without calling $next
     */
    public function handle(request $req, response $res, callable $next): mixed {
        $res->with_header('Access-Control-Allow-Origin',  $this->allow_origin)
            ->with_header('Access-Control-Allow-Methods', $this->allow_methods)
            ->with_header('Access-Control-Allow-Headers', $this->allow_headers)
            ->with_header('Access-Control-Max-Age',       (string) $this->max_age);

        if ($req->method() === 'OPTIONS') {
            return $res->status(204)->json([]);
        }

        return $next($req, $res);
    }
}
