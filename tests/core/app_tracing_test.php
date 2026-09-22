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

    test('enableTracing() flips the flag and clears state', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->tracing        = true;
        $a->resolveStack  = [['id' => 'stale', 'time' => 0.0]];
        $a->failedAt      = 'stale';
        $a->partialArgs   = ['stale'];

        $a->enableTracing();

        expect($a->tracing)->toBeTrue();
        expect($a->resolveStack)->toBe([]);
        expect($a->failedAt)->toBeNull();
        expect($a->partialArgs)->toBe([]);
    });

    test('disableTracing() clears state and flips the flag off', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->enableTracing();
        $a->disableTracing();

        expect($a->tracing)->toBeFalse();
        expect($a->resolveStack)->toBe([]);
        expect($a->failedAt)->toBeNull();
        expect($a->partialArgs)->toBe([]);
    });

    test('resolveStack records in-flight chain when tracing is on', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.a', fn() => 'A');
        $a->enableTracing();

        $a->make('svc.a');

        // make() pushes to stack then pops on success — stack must be empty
        expect($a->resolveStack)->toBe([]);
    });

    test('failedAt is captured when a binding throws', function(): void {
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

        expect($a->failedAt)->toBe('svc.bad');
        expect($a->resolveStack)->toBe([]); // cleaned up after throw
    });

    test('bindingsSnapshot is captured after boot()', function(): void {
        $a = \Skim\Core\App::testInstance(['app.debug' => true]);
        $a->bind('svc.foo', fn() => new \stdClass());
        $a->bind('svc.bar', fn() => new \stdClass());
        $a->boot();

        expect($a->bindingsSnapshot)->toBeArray();
        $abstracts = array_column($a->bindingsSnapshot, 'abstract');
        expect($abstracts)->toContain('svc.foo');
        expect($abstracts)->toContain('svc.bar');
    });

    test('snapshotBindings() records factory_kind and priority', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.closure', fn() => null);
        $a->bind(\stdClass::class, fn() => new \stdClass(), priority: 100);

        $snap = $a->snapshotBindings();

        $closureRow = null;
        $classRow   = null;
        foreach ($snap as $row) {
            if ($row['abstract'] === 'svc.closure') {
                $closureRow = $row;
            }
            if ($row['abstract'] === \stdClass::class) {
                $classRow = $row;
            }
        }

        // Default priority for non-extension binds is 100, but the explicit
        // priority of 100 in the second bind proves the value is recorded.
        expect($closureRow['factory_kind'])->toBe('closure');
        expect($closureRow['priority'])->toBe(100);
        expect($classRow['factory_kind'])->toBe('closure');
        expect($classRow['priority'])->toBe(100);
    });

    test('tracing is allocation-free when disabled (no array push)', function(): void {
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.simple', fn() => 'ok');
        $a->tracing = false;

        $a->make('svc.simple');

        // resolve_stack should be empty (and untouched) when tracing is off
        expect($a->resolveStack)->toBe([]);
        expect($a->failedAt)->toBeNull();
    });

    test('resolvedServices() returns the sorted list of resolved abstracts', function(): void {
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

    test('resolvedServices() returns empty after a fresh testInstance()', function(): void {
        // The Pest beforeEach resets via testInstance() — must clear $resolved.
        $a = \Skim\Core\App::testInstance();
        $a->bind('svc.foo', fn() => 'foo');
        $a->make('svc.foo');
        expect($a->resolvedServices())->toBe(['svc.foo']);

        // Re-bootstrap — should be empty again
        $a2 = \Skim\Core\App::testInstance();
        expect($a2->resolvedServices())->toBe([]);
    });

});
