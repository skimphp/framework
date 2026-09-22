<?php declare(strict_types=1);

namespace Skim\Middleware;

use Skim\Core\Middleware;
use Skim\Core\Request;
use Skim\Core\Response;

/**
 * Redis-backed sliding window rate limiter middleware.
 *
 * Use on API routes to protect against abuse. Counts requests per IP
 * in a Redis sorted set with timestamps as scores. Falls back to
 * passthrough (no rate limit) when Redis is unavailable.
 *
 * Example:
 *   $app->router->group('/api', function(Router $r) {
 *       $r->get('/data', [api_controller::class, 'index']);
 *   }, middleware: [new RateLimit(limit: 100, window: 60)]);
 *
 * #AI:class
 */
class RateLimit implements \Skim\Core\Middleware {
    public function __construct(
        private readonly int    $limit  = 60,
        private readonly int    $window = 60,
        private readonly string $prefix = 'rl:',
    ) {}

    /**
     * Counts requests per IP and short-circuits with 429 when limit exceeded. #AI:handle
     *
     * Uses a Redis sorted set sliding window per IP. Adds X-RateLimit-*
     * headers to every response. Falls back to passthrough when Redis
     * is unavailable — never blocks legitimate traffic due to Redis outage.
     *
     * @param \Skim\Core\Request $req Current HTTP request.
     * @param \Skim\Core\Response $res Current HTTP response.
     * @param callable $next Next middleware or controller in the pipeline.
     */
    public function handle(\Skim\Core\Request $req, \Skim\Core\Response $res, callable $next): mixed {
        $key = $this->prefix . $req->ip();
        $now = microtime(true);
        $min = $now - $this->window;

        try {
            $redis = $this->redis();
            $pipe  = $redis->pipeline();
            $pipe->zremrangebyscore($key, '-inf', (string) $min);
            $pipe->zadd($key, $now, (string) $now);
            $pipe->zcard($key);
            $pipe->expire($key, $this->window);
            $results = $pipe->exec();

            $count     = (int) ($results[2] ?? 0);
            $remaining = max(0, $this->limit - $count);

            $res->withHeader('X-RateLimit-Limit',     (string) $this->limit)
                ->withHeader('X-RateLimit-Remaining', (string) $remaining)
                ->withHeader('X-RateLimit-Reset',     (string) (int) ($now + $this->window));

            if ($count > $this->limit) {
                return $res->status(429)->json(['error' => 'Too Many Requests']);
            }
        } catch (\Throwable) {
            // Redis down — fail open (no rate limiting) rather than blocking all traffic
        }

        return $next($req, $res);
    }

    private function redis(): \Redis {
        $cfg = \Skim\Core\Config::get('cache.redis', []);
        $r   = new \Redis();
        $r->connect(
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? 6379,
            1.0,
        );
        if (!empty($cfg['password'])) {
            $r->auth($cfg['password']);
        }
        return $r;
    }
}

#AI:class
#AI symbol: Skim\Middleware\RateLimit
#AI source_path: src/Middleware/RateLimit.php
#AI title: RateLimit
#AI description: Redis-backed sliding window rate limiter with fail-open fallback.
#AI role: rate limiting middleware
#AI layer: middleware
#AI badges: [middleware; rate-limit; redis; fail-open]
#AI intro: `RateLimit` uses a Redis sorted set sliding window to count requests per IP. When the limit is exceeded, it returns 429. When Redis is unavailable, it fails open and passes all requests through.
#AI lifecycle: registered per-route or per-group; creates a Redis connection per request
#AI fallback: passthrough (no rate limiting) when Redis is unreachable
#AI test_seam: mock Redis or test with Redis available; verify X-RateLimit-* headers
#AI invariants: [Sliding window prevents boundary burst; Fail-open on Redis failure; X-RateLimit-* headers on every response when Redis is available]
#AI core_behaviors: [Counts requests per IP in Redis sorted set; Short-circuits with 429 when count exceeds limit; Adds rate limit headers to responses]
#AI owns: Redis sorted set keys per IP
#AI entry_points: [handle]
#AI config_reads: [cache.redis]
#AI non_goals: [Does not rate limit by user ID; Does not support distributed rate limiting across servers without shared Redis; Does not throttle — it blocks]
#AI side_effects: [Writes to Redis sorted set; Adds X-RateLimit-* headers to response]
#AI flow: handle() -> redis() -> pipeline(zremrangebyscore, zadd, zcard, expire) -> count > limit? -> 429 : $next()
#AI section_order: [Middleware]

#AI:handle
#AI group: Middleware
#AI frequency: high
#AI signature: public function handle(Request $req, Response $res, callable $next): mixed
#AI contract: Counts the request in a Redis sliding window per IP. Returns 429 when the limit is exceeded. Adds X-RateLimit-* headers. Falls open when Redis is unavailable.
#AI param_details: [{name: $req | type: request | required: true | desc: Current HTTP request (IP extracted for key).}; {name: $res | type: response | required: true | desc: Current HTTP response (headers added).}; {name: $next | type: callable | required: true | desc: Next middleware or controller.}]
#AI return_detail: {type: mixed | desc: Response with rate limit headers, or 429 JSON when limit exceeded.}
#AI side_effects: [Writes to Redis sorted set; Adds X-RateLimit-* response headers]
