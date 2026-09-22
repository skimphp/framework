<?php declare(strict_types=1);

use Skim\Core\Config;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Middleware\RateLimit;

describe('RateLimit', function(): void {

    afterEach(function(): void {
        $r = new Redis();
        $r->connect(testRedisHost(), 6379);
        foreach ($r->keys('rl:test:*') as $k) {
            $r->del($k);
        }
    });

    test('fails open and calls next when redis is unreachable', function(): void {
        Config::set('cache.redis', ['host' => '127.0.0.1', 'port' => 1]);

        $req = Request::make('GET', '/api');
        $res = new Response();
        $called = false;

        $result = (new RateLimit())->handle($req, $res, function($r, $s) use (&$called) {
            $called = true;
            return $s;
        });

        expect($called)->toBeTrue();
        expect($result)->toBe($res);
    });

    test('returns 429 when limit exceeded and sets rate limit headers', function(): void {
        Config::set('cache.redis', ['host' => testRedisHost(), 'port' => 6379]);

        $req = Request::make('GET', '/api');
        $next = fn($r, $s) => $s;
        $rl = new RateLimit(limit: 2, window: 60, prefix: 'rl:test:');

        $r1 = $rl->handle($req, new Response(), $next);
        $rl->handle($req, new Response(), $next);
        $r3 = $rl->handle($req, new Response(), $next);

        expect($r1->getHeader('X-RateLimit-Limit'))->toBe('2');
        expect($r1->getHeader('X-RateLimit-Remaining'))->toBe('1');
        expect($r3->getStatus())->toBe(429);
        expect($r3->getJson())->toBe(['error' => 'Too Many Requests']);
    });

});
