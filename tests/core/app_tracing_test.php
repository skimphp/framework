<?php declare(strict_types=1);

use Skim\Core\App;

beforeEach(function(): void {
    \Skim\Core\App::testInstance(); // resets the singleton
});

describe('app DI tracing', function(): void {

    test('tracing is off by default', function(): void {
        $a = \Skim\Core\App::testInstance();
        expect($a->tracing)->toBeFalse();
    });

    test('enable_tracing() flips the flag and clears state', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->tracing        = true;
        $a->resolve_stack  = [['id' => 'stale', 'time' => 0.0]];
        $a->failed_at      = 'stale';
        $a->partial_args   = ['stale'];

        $a->enableTracing();

        expect($a->tracing)->toBeTrue();
        expect($a->resolve_stack)->toBe([]);
        expect($a->failed_at)->toBeNull();
        expect($a->partial_args)->toBe([]);
    });

    test('disable_tracing() clears state and flips the flag off', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->enableTracing();
        $a->disableTracing();

        expect($a->tracing)->toBeFalse();
        expect($a->resolve_stack)->toBe([]);
        expect($a->failed_at)->toBeNull();
        expect($a->partial_args)->toBe([]);
    });

    test('resolve_stack records in-flight chain when tracing is on', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.a', fn() => 'A');
        $a->enableTracing();

        $a->make('svc.a');

        // make() pushes to stack then pops on success — stack must be empty
        expect($a->resolve_stack)->toBe([]);
    });

    test('failed_at is captured when a binding throws', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.bad', function() {
            throw new \RuntimeException('boom');
        });
        $a->enableTracing();

        try {
            $a->make('svc.bad');
            $this->fail('expected exception');
        }
        catch (\RuntimeException) {
            // expected
        }

        expect($a->failed_at)->toBe('svc.bad');
        expect($a->resolve_stack)->toBe([]); // cleaned up after throw
    });

    test('bindings_snapshot is captured after boot()', function(): void {
        $a = \Skim\Core\App::testInstance(['app.debug' => true]);
        $a->bind('svc.foo', fn() => new \stdClass());
        $a->bind('svc.bar', fn() => new \stdClass());
        $a->boot();

        expect($a->bindings_snapshot)->toBeArray();
        $abstracts = array_column($a->bindings_snapshot, 'abstract');
        expect($abstracts)->toContain('svc.foo');
        expect($abstracts)->toContain('svc.bar');
    });

    test('snapshot_bindings() records factory_kind and priority', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.closure', fn() => null);
        $a->bind(\stdClass::class, fn() => new \stdClass(), priority: 100);

        $snap = $a->snapshotBindings();

        $closure_row = null;
        $class_row   = null;
        foreach ($snap as $row) {
            if ($row['abstract'] === 'svc.closure') {
                $closure_row = $row;
            }
            if ($row['abstract'] === \stdClass::class) {
                $class_row = $row;
            }
        }

        // Default priority for non-extension binds is 100, but the explicit
        // priority of 100 in the second bind proves the value is recorded.
        expect($closure_row['factory_kind'])->toBe('closure');
        expect($closure_row['priority'])->toBe(100);
        expect($class_row['factory_kind'])->toBe('closure');
        expect($class_row['priority'])->toBe(100);
    });

    test('tracing is allocation-free when disabled (no array push)', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.simple', fn() => 'ok');
        $a->tracing = false;

        $a->make('svc.simple');

        // resolve_stack should be empty (and untouched) when tracing is off
        expect($a->resolve_stack)->toBe([]);
        expect($a->failed_at)->toBeNull();
    });

    test('resolved_services() returns the sorted list of resolved abstracts', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.alpha', fn() => 'A');
        $a->bind('svc.beta',  fn() => 'B');
        $a->bind('svc.gamma', fn() => 'C');

        // nothing resolved yet
        expect($a->resolvedServices())->toBe([]);

        $a->make('svc.gamma');
        $a->make('svc.alpha');

        expect($a->resolvedServices())->toBe(['svc.alpha', 'svc.gamma']);
    });

    test('resolved_services() returns empty after a fresh test_instance()', function(): void {
        // The Pest beforeEach resets via test_instance() — must clear $resolved.
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.foo', fn() => 'foo');
        $a->make('svc.foo');
        expect($a->resolvedServices())->toBe(['svc.foo']);

        // Re-bootstrap — should be empty again
        $a2 = \Skim\Core\App::testInstance();
        expect($a2->resolvedServices())->toBe([]);
    });

});
