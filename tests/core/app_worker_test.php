<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Request;
use Skim\Core\Response;

describe('app — request-scoped and transient bindings', function (): void {

    test('bindRequest marks service as request-scoped', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindRequest('svc.req', fn() => new \stdClass());

        $first = $app->make('svc.req');
        $second = $app->make('svc.req');

        expect($first)->toBe($second);
    });

    test('endRequest clears request-scoped resolved singletons', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindRequest('svc.req', fn() => new \stdClass());

        $before = $app->make('svc.req');
        $app->endRequest();
        $after = $app->make('svc.req');

        expect($after)->not->toBe($before);
    });

    test('endRequest leaves non-request-scoped bindings intact', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bind('svc.normal', fn() => new \stdClass());

        $before = $app->make('svc.normal');
        $app->endRequest();
        $after = $app->make('svc.normal');

        expect($after)->toBe($before);
    });

    test('bindTransient returns a new instance on every make()', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindTransient('svc.tr', fn() => new \stdClass());

        $first  = $app->make('svc.tr');
        $second = $app->make('svc.tr');

        expect($first)->not->toBe($second);
    });

    test('transient binding does not cache in resolved', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindTransient('svc.tr', fn() => 'x');

        $app->make('svc.tr');

        expect($app->resolvedServices())->toBe([]);
    });

    test('bind clears previous transient flag', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindTransient('svc.x', fn() => 'a');
        $app->bind('svc.x', fn() => 'b');

        $first  = $app->make('svc.x');
        $second = $app->make('svc.x');

        expect($first)->toBe($second);
    });

    test('bindRequest clears previous transient flag so it caches again', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindTransient('svc.t', fn() => new \stdClass());
        $app->bindRequest('svc.t', fn() => new \stdClass());

        $first  = $app->make('svc.t');
        $second = $app->make('svc.t');

        expect($first)->toBe($second); // no longer transient → cached
    });

    test('bindTransient clears previous request-scoped flag', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bindRequest('svc.r', fn() => new \stdClass());
        $app->bindTransient('svc.r', fn() => new \stdClass());

        $first  = $app->make('svc.r');
        $second = $app->make('svc.r');

        expect($first)->not->toBe($second); // now transient → fresh each call
    });

    test('makeTransient always returns a fresh instance', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bind('svc.s', fn() => new \stdClass());

        $a = $app->makeTransient('svc.s');
        $b = $app->makeTransient('svc.s');

        expect($a)->not->toBe($b);
        expect($app->resolvedServices())->toBe([]); // never cached
    });

    test('makeTransient resolves auto-wired classes without caching', function (): void {
        $app = \Skim\Core\App::testInstance();

        $a = $app->makeTransient(\stdClass::class);
        $b = $app->makeTransient(\stdClass::class);

        expect($a)->not->toBe($b);
        expect($app->resolvedServices())->toBe([]);
    });

});

describe('app — emit()', function (): void {

    test('emit sends a response instance directly', function (): void {
        $app = \Skim\Core\App::testInstance();
        $res = new \Skim\Core\Response();

        ob_start();
        $app->emit($res, new \Skim\Core\Response());
        ob_end_clean();

        expect($res->getStatus())->toBe(200);
    });

    test('emit serializes arrays to json', function (): void {
        $app = \Skim\Core\App::testInstance();
        $fallback = new \Skim\Core\Response();

        ob_start();
        $app->emit(['id' => 42], $fallback);
        $out = ob_get_clean();

        expect($fallback->getStatus())->toBe(200);
        expect($fallback->getJson())->toBe(['id' => 42]);
    });

    test('emit sends strings as raw body', function (): void {
        $app = \Skim\Core\App::testInstance();
        $fallback = new \Skim\Core\Response();

        ob_start();
        $app->emit('hello', $fallback);
        $out = ob_get_clean();

        expect($out)->toBe('hello');
    });

    test('emit sends null as empty body with fallback', function (): void {
        $app = \Skim\Core\App::testInstance();
        $fallback = new \Skim\Core\Response();

        ob_start();
        $app->emit(null, $fallback);
        $out = ob_get_clean();

        expect($out)->toBe('');
    });

    test('emit returns 405 for false', function (): void {
        $app = \Skim\Core\App::testInstance();
        $fallback = new \Skim\Core\Response();

        ob_start();
        $app->emit(false, $fallback);
        ob_end_clean();

        expect($fallback->getStatus())->toBe(405);
    });

});

describe('app — bootExtensions()', function (): void {

    test('bootExtensions is idempotent', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bootExtensions();
        $app->bootExtensions();

        expect($app)->toBeInstanceOf(\Skim\Core\App::class);
    });

    test('bootExtensions returns early when already booted', function (): void {
        $app = \Skim\Core\App::testInstance();
        $app->bootExtensions();
        // second call must not throw
        expect(fn() => $app->bootExtensions())->not->toThrow(\Throwable::class);
    });

});

describe('app — isDebugMode()', function (): void {

    test('returns false when app.debug is not set', function (): void {
        $app = \Skim\Core\App::testInstance();
        expect($app->isDebugMode())->toBeFalse();
    });

    test('returns true when app.debug is true', function (): void {
        $app = \Skim\Core\App::testInstance(['app.debug' => true]);
        $app->beginRequest();
        expect($app->isDebugMode())->toBeTrue();
    });

});

describe('app — handleException()', function (): void {

    test('renders error page in debug mode', function (): void {
        $app = \Skim\Core\App::testInstance(['app.debug' => true]);
        $app->beginRequest();

        ob_start();
        $app->handleException(new \RuntimeException('test'));
        $out = ob_get_clean();

        expect($out)->toContain('test');
    });

    test('returns 500 in production mode', function (): void {
        $app = \Skim\Core\App::testInstance(['app.debug' => false]);
        $app->beginRequest();

        ob_start();
        $app->handleException(new \RuntimeException('test'));
        ob_end_clean();

        expect(http_response_code())->toBe(500);
    });

});
