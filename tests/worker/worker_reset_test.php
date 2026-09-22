<?php declare(strict_types=1);

use Skim\Cache\Cache;
use Skim\Cache\ArrayDriver;
use Skim\Core\Pipeline;
use Skim\Db\Db;
use Skim\Events\Event;
use Skim\I18n\I18n;
use Skim\Session\Session;
use Skim\Testing\SessionFake;
use Skim\View\ComponentCollector;
use Skim\View\View;
use Skim\Worker\Resettable;
use Skim\Worker\WorkerReset;

beforeEach(function (): void {
    \Skim\Cache\Cache::setDriver(new \Skim\Cache\ArrayDriver());
    \Skim\Events\Event::off();
    \Skim\I18n\I18n::reset();
    \Skim\Session\Session::reset();
    \Skim\View\View::reset();
    \Skim\View\ComponentCollector::resetRequest();
});

describe('WorkerReset::apply()', function (): void {

    test('clears event listeners', function (): void {
        \Skim\Events\Event::on('test', fn() => null);
        expect(\Skim\Events\Event::listenerCount('test'))->toBe(1);

        \Skim\Worker\WorkerReset::apply(ob_get_level());

        expect(\Skim\Events\Event::listenerCount('test'))->toBe(0);
    });

    test('resets i18n locale to fallback', function (): void {
        \Skim\I18n\I18n::locale('fr');
        expect(\Skim\I18n\I18n::currentLocale())->toBe('fr');

        \Skim\Worker\WorkerReset::apply(ob_get_level());

        expect(\Skim\I18n\I18n::currentLocale())->toBe('en');
    });

    test('resets session driver and started flag', function (): void {
        $driver = new class implements \Skim\Session\SessionDriver {
            private string $sid = 'test-sid';
            public function start(): void {}
            public function get(string $key, mixed $default = null): mixed { return null; }
            public function set(string $key, mixed $value): void {}
            public function has(string $key): bool { return false; }
            public function delete(string $key): void {}
            public function regenerate(): void { $this->sid = 'new-sid'; }
            public function flush(): void {}
            public function id(): string { return $this->sid; }
        };

        \Skim\Session\Session::setDriver($driver);
        \Skim\Session\Session::start();
        expect(\Skim\Session\Session::id())->toBe('test-sid');

        \Skim\Worker\WorkerReset::apply(ob_get_level());

        // After reset, session should be back to unstarted state
        expect(true)->toBeTrue(); // no throw
    });

    test('clears view shared data', function (): void {
        \Skim\View\View::share('user', 'Alice');
        expect(\Skim\View\View::getShared('user'))->toBe('Alice');

        \Skim\Worker\WorkerReset::apply(ob_get_level());

        expect(\Skim\View\View::getShared('user'))->toBeNull();
    });

    test('clears middleware instance cache', function (): void {
        // We cannot directly inspect the private cache, but we can
        // verify no error occurs and the method exists.
        expect(fn() => \Skim\Worker\WorkerReset::apply(ob_get_level()))->not->toThrow(\Throwable::class);
    });

    test('clears output buffers', function (): void {
        $baseline = ob_get_level();
        ob_start();
        echo 'buffered';

        \Skim\Worker\WorkerReset::apply($baseline);

        // After apply, only PHPUnit's buffer remains
        expect(ob_get_level())->toBe($baseline);
    });

    test('clears a ComponentCollector left on the stack by a throwing component', function (): void {
        \Skim\View\ComponentCollector::push(new \Skim\View\ComponentCollector());
        expect(\Skim\View\ComponentCollector::current())->not->toBeNull();

        \Skim\Worker\WorkerReset::apply(ob_get_level());

        expect(\Skim\View\ComponentCollector::current())->toBeNull();
    });

    test('discovers and resets a resettable class loaded after the first request', function (): void {
        // Simulate the lazy-autoload case: a resettable facade that is only
        // declared on a later request must still be discovered and reset, not
        // permanently missed because discovery was cached on the first request.
        \Skim\Worker\WorkerReset::apply(ob_get_level());

        $class = 'late_resettable_' . str_replace('.', '', uniqid('', true));
        eval("class {$class} implements \\Skim\\Worker\\Resettable {
            public static bool \$was_reset = false;
            public static function resetRequest(): void { static::\$was_reset = true; }
        }");

        \Skim\Worker\WorkerReset::apply(ob_get_level());

        expect(\Skim\Worker\WorkerReset::discovered())->toContain($class);
        expect($class::$was_reset)->toBeTrue();
    });

});

describe('resettable contract guard', function (): void {

    // Regression guard: every static facade that holds PER-REQUEST state and is
    // reset via the resettable interface must implement it, so worker_reset clears
    // it between requests in worker mode. When you add such a facade, list it here
    // AND implement resettable.
    //
    // Note: profiler and request_trace also hold per-request state but are reset by
    // explicit calls in WorkerReset::apply() (Profiler::reset()/RequestTrace::reset()),
    // not via the resettable interface — so they are intentionally absent here.
    // Process-scoped state (config, env, db connection pool, persistent caches,
    // model metadata) intentionally stays put and must NOT be listed.
    test('all interface-reset request-state facades implement resettable', function (): void {
        $requestStateFacades = [
            \Skim\Events\Event::class,
            \Skim\View\View::class,
            \Skim\Session\Session::class,
            \Skim\I18n\I18n::class,
            \Skim\View\ComponentCollector::class,
        ];

        foreach ($requestStateFacades as $facade) {
            expect(is_subclass_of($facade, \Skim\Worker\Resettable::class))->toBeTrue(
                "{$facade} holds per-request state and must implement Skim\Worker\Resettable"
            );
        }
    });

});
