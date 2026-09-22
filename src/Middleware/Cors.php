<?php declare(strict_types=1);

namespace Skim\Middleware;

use Skim\Core\Middleware;
use Skim\Core\Request;
use Skim\Core\Response;

/**
 * CORS middleware — adds cross-origin headers and handles preflight.
 *
 * Use as a global middleware when the API is consumed by browser-based
 * clients on different origins. Must run before auth middleware because
 * browsers send OPTIONS preflight without auth headers.
 *
 * Example:
 *   $app->use(Cors::class);
 *   // or with custom origin:
 *   $app->use(new Cors(allow_origin: 'https://app.example.com'));
 *
 * #AI:class
 */
class Cors implements \Skim\Core\Middleware {
    public function __construct(
        private readonly string $allowOrigin  = '*',
        private readonly string $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private readonly string $allowHeaders = 'Content-Type, Authorization, X-Requested-With',
        private readonly int    $maxAge       = 86400,
    ) {}

    /**
     * Adds CORS headers and short-circuits OPTIONS preflight with 204. #AI:handle
     *
     * Every response receives Access-Control-Allow-* headers. OPTIONS requests
     * return a 204 immediately without calling $next, so auth middleware never
     * sees preflight requests.
     *
     * @param \Skim\Core\Request $req Current HTTP request.
     * @param \Skim\Core\Response $res Current HTTP response.
     * @param callable $next Next middleware or controller in the pipeline.
     */
    public function handle(\Skim\Core\Request $req, \Skim\Core\Response $res, callable $next): mixed {
        $res->withHeader('Access-Control-Allow-Origin',  $this->allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
            ->withHeader('Access-Control-Max-Age',       (string) $this->maxAge);

        if ($req->method() === 'OPTIONS') {
            return $res->status(204)->json([]);
        }

        return $next($req, $res);
    }
}

#AI:class
#AI symbol: Skim\Middleware\Cors
#AI source_path: src/Middleware/Cors.php
#AI title: cors
#AI description: CORS middleware that adds cross-origin headers and handles OPTIONS preflight.
#AI role: CORS middleware
#AI layer: middleware
#AI badges: [middleware; cors; http; security]
#AI intro: `cors` adds Access-Control-Allow-* headers to every response and short-circuits OPTIONS preflight requests with a 204. It must run before auth middleware since browsers send preflight without credentials.
#AI lifecycle: registered as global middleware; runs on every request
#AI test_seam: construct with custom allowOrigin for per-test configuration
#AI invariants: [OPTIONS requests never reach $next; All responses receive CORS headers; Default allowOrigin is wildcard '*']
#AI core_behaviors: [Adds Allow-Origin, Allow-Methods, Allow-Headers, Max-Age headers; Short-circuits OPTIONS with 204 empty response]
#AI owns: nothing
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not validate origin against a whitelist; Does not handle credentials mode; Does not set Vary header]
#AI side_effects: [Adds CORS headers to response; Short-circuits OPTIONS requests]
#AI flow: handle() -> add CORS headers -> OPTIONS? -> 204 : $next()
#AI section_order: [Middleware]

#AI:handle
#AI group: Middleware
#AI frequency: high
#AI signature: public function handle(Request $req, Response $res, callable $next): mixed
#AI contract: Adds CORS headers to every response. Short-circuits OPTIONS preflight with 204 without calling $next.
#AI param_details: [{name: $req | type: request | required: true | desc: Current HTTP request.}; {name: $res | type: response | required: true | desc: Current HTTP response.}; {name: $next | type: callable | required: true | desc: Next middleware or controller.}]
#AI return_detail: {type: mixed | desc: Response with CORS headers, or 204 for OPTIONS preflight.}
#AI side_effects: [Adds Access-Control-* headers to response]
