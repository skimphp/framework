<?php declare(strict_types=1);

use skim\core\middleware;
use skim\core\pipeline;
use skim\core\request;
use skim\core\response;

// Test middleware implementations — inline anonymous classes

function make_recording_middleware(string $label, array &$log): middleware {
    return new class($label, $log) implements middleware {
        public function __construct(private string $label, private array &$log) {}

        public function handle(request $req, response $res, callable $next): mixed {
            $this->log[] = $this->label . ':before';
            $result = $next($req, $res);
            $this->log[] = $this->label . ':after';
            return $result;
        }
    };
}

function make_short_circuit_middleware(int $status): middleware {
    return new class($status) implements middleware {
        public function __construct(private int $status) {}

        public function handle(request $req, response $res, callable $next): mixed {
            return $res->status($this->status)->json(['error' => 'blocked']);
        }
    };
}

describe('pipeline — execution order', function(): void {

    test('middlewares execute in registration order (first registered = first run)', function(): void {
        $log      = [];
        $pipeline = new pipeline();
        $req      = request::make();
        $res      = new response();

        $a = make_recording_middleware('A', $log);
        $b = make_recording_middleware('B', $log);

        $pipeline->run($req, $res, [$a, $b], function() use (&$log, $res): response {
            $log[] = 'core';
            return $res->json(['ok' => true]);
        });

        expect($log)->toBe(['A:before', 'B:before', 'core', 'B:after', 'A:after']);
    });

    test('single middleware wraps the core handler', function(): void {
        $log      = [];
        $pipeline = new pipeline();
        $req      = request::make();
        $res      = new response();

        $m = make_recording_middleware('M', $log);

        $pipeline->run($req, $res, [$m], function() use (&$log, $res): response {
            $log[] = 'core';
            return $res->json([]);
        });

        expect($log)->toBe(['M:before', 'core', 'M:after']);
    });

    test('empty middleware list runs core directly', function(): void {
        $pipeline = new pipeline();
        $req      = request::make();
        $res      = new response();
        $called   = false;

        $pipeline->run($req, $res, [], function() use (&$called, $res): response {
            $called = true;
            return $res;
        });

        expect($called)->toBeTrue();
    });

});

describe('pipeline — short-circuit', function(): void {

    test('short-circuit middleware prevents core from running', function(): void {
        $pipeline  = new pipeline();
        $req       = request::make();
        $res       = new response();
        $core_ran  = false;

        $blocker = make_short_circuit_middleware(401);

        $result = $pipeline->run($req, $res, [$blocker], function() use (&$core_ran, $res): response {
            $core_ran = true;
            return $res;
        });

        expect($core_ran)->toBeFalse();
        expect($result->get_status())->toBe(401);
    });

    test('middleware after short-circuit does not run', function(): void {
        $log      = [];
        $pipeline = new pipeline();
        $req      = request::make();
        $res      = new response();

        $blocker = make_short_circuit_middleware(403);
        $after   = make_recording_middleware('after', $log);

        $pipeline->run($req, $res, [$blocker, $after], fn() => $res);

        expect($log)->toBeEmpty();
    });

});

describe('cors middleware', function(): void {

    test('adds CORS headers to every response', function(): void {
        $cors     = new \skim\middleware\cors();
        $req      = request::make('GET', '/');
        $res      = new response();

        $cors->handle($req, $res, fn($r, $s) => $s->json([]));

        expect($res->get_headers())->toHaveKey('Access-Control-Allow-Origin');
    });

    test('short-circuits with 204 on OPTIONS preflight', function(): void {
        $cors = new \skim\middleware\cors();
        $req  = request::make('OPTIONS', '/api/users');
        $res  = new response();

        $called = false;
        $result = $cors->handle($req, $res, function() use (&$called, $res): response {
            $called = true;
            return $res;
        });

        expect($called)->toBeFalse();
        expect($result->get_status())->toBe(204);
    });

});
