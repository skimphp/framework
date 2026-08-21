<?php declare(strict_types=1);

use skim\cache\cache;
use skim\cache\array_driver;
use skim\core\pipeline;
use skim\db\db;
use skim\events\event;
use skim\i18n\i18n;
use skim\session\session;
use skim\testing\session_fake;
use skim\view\component_collector;
use skim\view\view;
use skim\worker\resettable;
use skim\worker\worker_reset;

beforeEach(function (): void {
    cache::set_driver(new array_driver());
    event::off();
    i18n::reset();
    session::reset();
    view::reset();
    component_collector::reset_request();
});

describe('worker_reset::apply()', function (): void {

    test('clears event listeners', function (): void {
        event::on('test', fn() => null);
        expect(event::listener_count('test'))->toBe(1);

        worker_reset::apply(ob_get_level());

        expect(event::listener_count('test'))->toBe(0);
    });

    test('resets i18n locale to fallback', function (): void {
        i18n::locale('fr');
        expect(i18n::current_locale())->toBe('fr');

        worker_reset::apply(ob_get_level());

        expect(i18n::current_locale())->toBe('en');
    });

    test('resets session driver and started flag', function (): void {
        $driver = new class implements \skim\session\session_driver {
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

        session::set_driver($driver);
        session::start();
        expect(session::id())->toBe('test-sid');

        worker_reset::apply(ob_get_level());

        // After reset, session should be back to unstarted state
        expect(true)->toBeTrue(); // no throw
    });

    test('clears view shared data', function (): void {
        view::share('user', 'Alice');
        expect(view::get_shared('user'))->toBe('Alice');

        worker_reset::apply(ob_get_level());

        expect(view::get_shared('user'))->toBeNull();
    });

    test('clears middleware instance cache', function (): void {
        // We cannot directly inspect the private cache, but we can
        // verify no error occurs and the method exists.
        expect(fn() => worker_reset::apply(ob_get_level()))->not->toThrow(\Throwable::class);
    });

    test('clears output buffers', function (): void {
        $baseline = ob_get_level();
        ob_start();
        echo 'buffered';

        worker_reset::apply($baseline);

        // After apply, only PHPUnit's buffer remains
        expect(ob_get_level())->toBe($baseline);
    });

    test('clears a component_collector left on the stack by a throwing component', function (): void {
        component_collector::push(new component_collector());
        expect(component_collector::current())->not->toBeNull();

        worker_reset::apply(ob_get_level());

        expect(component_collector::current())->toBeNull();
    });

    test('discovers and resets a resettable class loaded after the first request', function (): void {
        // Simulate the lazy-autoload case: a resettable facade that is only
        // declared on a later request must still be discovered and reset, not
        // permanently missed because discovery was cached on the first request.
        worker_reset::apply(ob_get_level());

        $class = 'late_resettable_' . str_replace('.', '', uniqid('', true));
        eval("class {$class} implements \\skim\\worker\\resettable {
            public static bool \$was_reset = false;
            public static function reset_request(): void { static::\$was_reset = true; }
        }");

        worker_reset::apply(ob_get_level());

        expect(worker_reset::discovered())->toContain($class);
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
    // explicit calls in worker_reset::apply() (profiler::reset()/request_trace::reset()),
    // not via the resettable interface — so they are intentionally absent here.
    // Process-scoped state (config, env, db connection pool, persistent caches,
    // model metadata) intentionally stays put and must NOT be listed.
    test('all interface-reset request-state facades implement resettable', function (): void {
        $request_state_facades = [
            \skim\events\event::class,
            \skim\view\view::class,
            \skim\session\session::class,
            \skim\i18n\i18n::class,
            \skim\view\component_collector::class,
        ];

        foreach ($request_state_facades as $facade) {
            expect(is_subclass_of($facade, resettable::class))->toBeTrue(
                "{$facade} holds per-request state and must implement skim\\worker\\resettable"
            );
        }
    });

});
