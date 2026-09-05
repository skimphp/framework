<?php declare(strict_types=1);

use Skim\Core\Middleware;
use Skim\Core\Pipeline;
use Skim\Core\Request;
use Skim\Core\Response;

// Test middleware implementations — inline anonymous classes

function makeRecordingMiddleware(string $label, array &$log): \Skim\Core\Middleware {
    return new class($label, $log) implements \Skim\Core\Middleware {
        public function __construct(private string $label, private array &$log) {}

        public function handle(\Skim\Core\Request $req, \Skim\Core\Response $res, callable $next): mixed {
            $this->log[] = $this->label . ':before';
            $result = $next($req, $res);
            $this->log[] = $this->label . ':after';
            return $result;
        }
    };
}

function makeShortCircuitMiddleware(int $status): \Skim\Core\Middleware {
    return new class($status) implements \Skim\Core\Middleware {
        public function __construct(private int $status) {}

        public function handle(\Skim\Core\Request $req, \Skim\Core\Response $res, callable $next): mixed {
            return $res->status($this->status)->json(['error' => 'blocked']);
        }
    };
}

describe('pipeline — execution order', function(): void {

    test('middlewares execute in registration order (first registered = first run)', function(): void {
        $log      = [];
        $pipeline = new \Skim\Core\Pipeline();
        $req      = \Skim\Core\Request::make();
        $res      = new \Skim\Core\Response();

        $a = makeRecordingMiddleware('A', $log);
        $b = makeRecordingMiddleware('B', $log);

        $pipeline->run($req, $res, [$a, $b], function() use (&$log, $res): \Skim\Core\Response {
            $log[] = 'core';
            return $res->json(['ok' => true]);
        });

        expect($log)->toBe(['A:before', 'B:before', 'core', 'B:after', 'A:after']);
    });

    test('single middleware wraps the core handler', function(): void {
        $log      = [];
        $pipeline = new \Skim\Core\Pipeline();
        $req      = \Skim\Core\Request::make();
        $res      = new \Skim\Core\Response();

        $m = makeRecordingMiddleware('M', $log);

        $pipeline->run($req, $res, [$m], function() use (&$log, $res): \Skim\Core\Response {
            $log[] = 'core';
            return $res->json([]);
        });

        expect($log)->toBe(['M:before', 'core', 'M:after']);
    });

    test('empty middleware list runs core directly', function(): void {
        $pipeline = new \Skim\Core\Pipeline();
        $req      = \Skim\Core\Request::make();
        $res      = new \Skim\Core\Response();
        $called   = false;

        $pipeline->run($req, $res, [], function() use (&$called, $res): \Skim\Core\Response {
            $called = true;
            return $res;
        });

        expect($called)->toBeTrue();
    });

});

describe('pipeline — short-circuit', function(): void {

    test('short-circuit middleware prevents core from running', function(): void {
        $pipeline  = new \Skim\Core\Pipeline();
        $req       = \Skim\Core\Request::make();
        $res       = new \Skim\Core\Response();
        $coreRan  = false;

        $blocker = makeShortCircuitMiddleware(401);

        $result = $pipeline->run($req, $res, [$blocker], function() use (&$coreRan, $res): \Skim\Core\Response {
            $coreRan = true;
            return $res;
        });

        expect($coreRan)->toBeFalse();
        expect($result->getStatus())->toBe(401);
    });

    test('middleware after short-circuit does not run', function(): void {
        $log      = [];
        $pipeline = new \Skim\Core\Pipeline();
        $req      = \Skim\Core\Request::make();
        $res      = new \Skim\Core\Response();

        $blocker = makeShortCircuitMiddleware(403);
        $after   = makeRecordingMiddleware('after', $log);

        $pipeline->run($req, $res, [$blocker, $after], fn() => $res);

        expect($log)->toBeEmpty();
    });

});

describe('cors middleware', function(): void {

    test('adds CORS headers to every response', function(): void {
        $cors     = new \Skim\Middleware\Cors();
        $req      = \Skim\Core\Request::make('GET', '/');
        $res      = new \Skim\Core\Response();

        $cors->handle($req, $res, fn($r, $s) => $s->json([]));

        expect($res->getHeaders())->toHaveKey('Access-Control-Allow-Origin');
    });

    test('short-circuits with 204 on OPTIONS preflight', function(): void {
        $cors = new \Skim\Middleware\Cors();
        $req  = \Skim\Core\Request::make('OPTIONS', '/api/users');
        $res  = new \Skim\Core\Response();

        $called = false;
        $result = $cors->handle($req, $res, function() use (&$called, $res): \Skim\Core\Response {
            $called = true;
            return $res;
        });

        expect($called)->toBeFalse();
        expect($result->getStatus())->toBe(204);
    });

});
