<?php declare(strict_types=1);

use skim\core\app;
use skim\core\request;
use skim\core\response;

describe('app — request-scoped and transient bindings', function (): void {

    test('bind_request marks service as request-scoped', function (): void {
        $app = app::test_instance();
        $app->bind_request('svc.req', fn() => new \stdClass());

        $first = $app->make('svc.req');
        $second = $app->make('svc.req');

        expect($first)->toBe($second);
    });

    test('end_request clears request-scoped resolved singletons', function (): void {
        $app = app::test_instance();
        $app->bind_request('svc.req', fn() => new \stdClass());

        $before = $app->make('svc.req');
        $app->end_request();
        $after = $app->make('svc.req');

        expect($after)->not->toBe($before);
    });

    test('end_request leaves non-request-scoped bindings intact', function (): void {
        $app = app::test_instance();
        $app->bind('svc.normal', fn() => new \stdClass());

        $before = $app->make('svc.normal');
        $app->end_request();
        $after = $app->make('svc.normal');

        expect($after)->toBe($before);
    });

    test('bind_transient returns a new instance on every make()', function (): void {
        $app = app::test_instance();
        $app->bind_transient('svc.tr', fn() => new \stdClass());

        $first  = $app->make('svc.tr');
        $second = $app->make('svc.tr');

        expect($first)->not->toBe($second);
    });

    test('transient binding does not cache in resolved', function (): void {
        $app = app::test_instance();
        $app->bind_transient('svc.tr', fn() => 'x');

        $app->make('svc.tr');

        expect($app->resolved_services())->toBe([]);
    });

    test('bind clears previous transient flag', function (): void {
        $app = app::test_instance();
        $app->bind_transient('svc.x', fn() => 'a');
        $app->bind('svc.x', fn() => 'b');

        $first  = $app->make('svc.x');
        $second = $app->make('svc.x');

        expect($first)->toBe($second);
    });

    test('bind_request clears previous transient flag so it caches again', function (): void {
        $app = app::test_instance();
        $app->bind_transient('svc.t', fn() => new \stdClass());
        $app->bind_request('svc.t', fn() => new \stdClass());

        $first  = $app->make('svc.t');
        $second = $app->make('svc.t');

        expect($first)->toBe($second); // no longer transient → cached
    });

    test('bind_transient clears previous request-scoped flag', function (): void {
        $app = app::test_instance();
        $app->bind_request('svc.r', fn() => new \stdClass());
        $app->bind_transient('svc.r', fn() => new \stdClass());

        $first  = $app->make('svc.r');
        $second = $app->make('svc.r');

        expect($first)->not->toBe($second); // now transient → fresh each call
    });

    test('make_transient always returns a fresh instance', function (): void {
        $app = app::test_instance();
        $app->bind('svc.s', fn() => new \stdClass());

        $a = $app->make_transient('svc.s');
        $b = $app->make_transient('svc.s');

        expect($a)->not->toBe($b);
        expect($app->resolved_services())->toBe([]); // never cached
    });

    test('make_transient resolves auto-wired classes without caching', function (): void {
        $app = app::test_instance();

        $a = $app->make_transient(\stdClass::class);
        $b = $app->make_transient(\stdClass::class);

        expect($a)->not->toBe($b);
        expect($app->resolved_services())->toBe([]);
    });

});

describe('app — emit()', function (): void {

    test('emit sends a response instance directly', function (): void {
        $app = app::test_instance();
        $res = new response();

        ob_start();
        $app->emit($res, new response());
        ob_end_clean();

        expect($res->get_status())->toBe(200);
    });

    test('emit serializes arrays to json', function (): void {
        $app = app::test_instance();
        $fallback = new response();

        ob_start();
        $app->emit(['id' => 42], $fallback);
        $out = ob_get_clean();

        expect($fallback->get_status())->toBe(200);
        expect($fallback->get_json())->toBe(['id' => 42]);
    });

    test('emit sends strings as raw body', function (): void {
        $app = app::test_instance();
        $fallback = new response();

        ob_start();
        $app->emit('hello', $fallback);
        $out = ob_get_clean();

        expect($out)->toBe('hello');
    });

    test('emit sends null as empty body with fallback', function (): void {
        $app = app::test_instance();
        $fallback = new response();

        ob_start();
        $app->emit(null, $fallback);
        $out = ob_get_clean();

        expect($out)->toBe('');
    });

    test('emit returns 405 for false', function (): void {
        $app = app::test_instance();
        $fallback = new response();

        ob_start();
        $app->emit(false, $fallback);
        ob_end_clean();

        expect($fallback->get_status())->toBe(405);
    });

});

describe('app — boot_extensions()', function (): void {

    test('boot_extensions is idempotent', function (): void {
        $app = app::test_instance();
        $app->boot_extensions();
        $app->boot_extensions();

        expect($app)->toBeInstanceOf(app::class);
    });

    test('boot_extensions returns early when already booted', function (): void {
        $app = app::test_instance();
        $app->boot_extensions();
        // second call must not throw
        expect(fn() => $app->boot_extensions())->not->toThrow(\Throwable::class);
    });

});

describe('app — is_debug_mode()', function (): void {

    test('returns false when app.debug is not set', function (): void {
        $app = app::test_instance();
        expect($app->is_debug_mode())->toBeFalse();
    });

    test('returns true when app.debug is true', function (): void {
        $app = app::test_instance(['app.debug' => true]);
        $app->begin_request();
        expect($app->is_debug_mode())->toBeTrue();
    });

});

describe('app — handle_exception()', function (): void {

    test('renders error page in debug mode', function (): void {
        $app = app::test_instance(['app.debug' => true]);
        $app->begin_request();

        ob_start();
        $app->handle_exception(new \RuntimeException('test'));
        $out = ob_get_clean();

        expect($out)->toContain('test');
    });

    test('returns 500 in production mode', function (): void {
        $app = app::test_instance(['app.debug' => false]);
        $app->begin_request();

        ob_start();
        $app->handle_exception(new \RuntimeException('test'));
        ob_end_clean();

        expect(http_response_code())->toBe(500);
    });

});
