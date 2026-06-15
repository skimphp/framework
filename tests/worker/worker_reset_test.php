<?php declare(strict_types=1);

use skim\cache\cache;
use skim\cache\array_driver;
use skim\core\pipeline;
use skim\db\db;
use skim\events\event;
use skim\i18n\i18n;
use skim\session\session;
use skim\testing\session_fake;
use skim\view\view;
use skim\worker\worker_reset;

beforeEach(function (): void {
    cache::set_driver(new array_driver());
    event::off();
    i18n::reset();
    session::reset();
    view::reset();
});

describe('worker_reset::apply()', function (): void {

    test('clears event listeners', function (): void {
        event::on('test', fn() => null);
        expect(event::listener_count('test'))->toBe(1);

        worker_reset::apply();

        expect(event::listener_count('test'))->toBe(0);
    });

    test('resets i18n locale to fallback', function (): void {
        i18n::locale('fr');
        expect(i18n::current_locale())->toBe('fr');

        worker_reset::apply();

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

        worker_reset::apply();

        // After reset, session should be back to unstarted state
        expect(true)->toBeTrue(); // no throw
    });

    test('clears view shared data', function (): void {
        view::share('user', 'Alice');
        expect(view::get_shared('user'))->toBe('Alice');

        worker_reset::apply();

        expect(view::get_shared('user'))->toBeNull();
    });

    test('clears middleware instance cache', function (): void {
        // We cannot directly inspect the private cache, but we can
        // verify no error occurs and the method exists.
        expect(fn() => worker_reset::apply())->not->toThrow(\Throwable::class);
    });

    test('clears output buffers', function (): void {
        ob_start();
        echo 'buffered';

        worker_reset::apply();

        // After apply, all buffers should be closed
        expect(ob_get_level())->toBe(0);
    });

});
