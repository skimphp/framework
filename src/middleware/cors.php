<?php declare(strict_types=1);

namespace skim\middleware;

use skim\core\middleware;
use skim\core\request;
use skim\core\response;

/**
 * CORS middleware — adds cross-origin headers and handles preflight.
 *
 * Use as a global middleware when the API is consumed by browser-based
 * clients on different origins. Must run before auth middleware because
 * browsers send OPTIONS preflight without auth headers.
 *
 * Example:
 *   $app->use(cors::class);
 *   // or with custom origin:
 *   $app->use(new cors(allow_origin: 'https://app.example.com'));
 *
 * #AI:class
 */
class cors implements middleware {
    public function __construct(
        private readonly string $allow_origin  = '*',
        private readonly string $allow_methods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private readonly string $allow_headers = 'Content-Type, Authorization, X-Requested-With',
        private readonly int    $max_age       = 86400,
    ) {}

    /**
     * Adds CORS headers and short-circuits OPTIONS preflight with 204. #AI:handle
     *
     * Every response receives Access-Control-Allow-* headers. OPTIONS requests
     * return a 204 immediately without calling $next, so auth middleware never
     * sees preflight requests.
     *
     * @param request  $req  Current HTTP request.
     * @param response $res  Current HTTP response.
     * @param callable $next Next middleware or controller in the pipeline.
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

#AI:class
#AI symbol: skim\middleware\cors
#AI source_path: src/middleware/cors.php
#AI title: cors
#AI description: CORS middleware that adds cross-origin headers and handles OPTIONS preflight.
#AI role: CORS middleware
#AI layer: middleware
#AI badges: [middleware; cors; http; security]
#AI intro: `cors` adds Access-Control-Allow-* headers to every response and short-circuits OPTIONS preflight requests with a 204. It must run before auth middleware since browsers send preflight without credentials.
#AI lifecycle: registered as global middleware; runs on every request
#AI test_seam: construct with custom allow_origin for per-test configuration
#AI invariants: [OPTIONS requests never reach $next; All responses receive CORS headers; Default allow_origin is wildcard '*']
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
#AI signature: public function handle(request $req, response $res, callable $next): mixed
#AI contract: Adds CORS headers to every response. Short-circuits OPTIONS preflight with 204 without calling $next.
#AI param_details: [{name: $req | type: request | required: true | desc: Current HTTP request.}; {name: $res | type: response | required: true | desc: Current HTTP response.}; {name: $next | type: callable | required: true | desc: Next middleware or controller.}]
#AI return_detail: {type: mixed | desc: Response with CORS headers, or 204 for OPTIONS preflight.}
#AI side_effects: [Adds Access-Control-* headers to response]
