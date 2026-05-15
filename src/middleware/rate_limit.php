<?php declare(strict_types=1);

namespace skim\middleware;

use skim\core\middleware;
use skim\core\request;
use skim\core\response;

// Redis-backed sliding window rate limiter.
// Uses a sorted set per IP: timestamps as scores, expiry = window seconds.
// Why sliding window: fixed window (e.g. INCR per minute) allows 2× burst at window boundary.
class rate_limit implements middleware {
    public function __construct(
        private readonly int    $limit  = 60,   // max requests per $window
        private readonly int    $window = 60,   // window size in seconds
        private readonly string $prefix = 'rl:',
    ) {}

    /**
     * @ai-contract counts requests per IP in a Redis sorted set sliding window
     * @ai-contract short-circuits with 429 when limit exceeded
     * @ai-contract adds X-RateLimit-* headers to every response (remaining, reset)
     * @ai-contract falls back to passthrough (no rate limit) when Redis is unavailable
     */
    public function handle(request $req, response $res, callable $next): mixed {
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
            $results = $pipe->execute();

            $count     = (int) ($results[2] ?? 0);
            $remaining = max(0, $this->limit - $count);

            $res->with_header('X-RateLimit-Limit',     (string) $this->limit)
                ->with_header('X-RateLimit-Remaining', (string) $remaining)
                ->with_header('X-RateLimit-Reset',     (string) (int) ($now + $this->window));

            if ($count > $this->limit) {
                return $res->status(429)->json(['error' => 'Too Many Requests']);
            }
        } catch (\Throwable) {
            // Redis down — fail open (no rate limiting) rather than blocking all traffic
        }

        return $next($req, $res);
    }

    private function redis(): \Redis {
        $cfg = \skim\core\config::get('cache.redis', []);
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
