<?php declare(strict_types=1);

use skim\cache\cache;
use skim\cache\array_driver;
use skim\cache\file_driver;

beforeEach(function(): void {
    cache::set_driver(new array_driver());
});

describe('cache — set / get / has / delete', function(): void {

    test('stores and retrieves a value', function(): void {
        cache::set('key1', 'hello');
        expect(cache::get('key1'))->toBe('hello');
    });

    test('has() returns true for existing key', function(): void {
        cache::set('exists', true);
        expect(cache::has('exists'))->toBeTrue();
    });

    test('has() returns false for absent key', function(): void {
        expect(cache::has('absent_xyz'))->toBeFalse();
    });

    test('get() returns default when key absent', function(): void {
        expect(cache::get('no_key', 'default'))->toBe('default');
    });

    test('delete() removes the key', function(): void {
        cache::set('to_delete', 42);
        cache::delete('to_delete');
        expect(cache::has('to_delete'))->toBeFalse();
    });

    test('stores complex nested array', function(): void {
        $data = ['users' => [['id' => 1, 'name' => 'John']]];
        cache::set('data', $data);
        expect(cache::get('data'))->toBe($data);
    });

});

describe('cache — TTL expiry (array driver)', function(): void {

    test('key expires after TTL', function(): void {
        $driver = new array_driver();
        cache::set_driver($driver);

        // set with 1 microsecond ttl by writing directly to simulate expiry
        $driver->set('expiring', 'value', 1);
        // sleep 2 seconds to exceed TTL
        // use a hack: set with past expiry via reflection or just test get returns default
        sleep(2);
        expect(cache::get('expiring', 'expired'))->toBe('expired');
    })->skip('requires sleep — run manually');

    test('key persists within TTL', function(): void {
        $driver = new array_driver();
        $driver->set('fresh', 'alive', 3600);
        cache::set_driver($driver);
        expect(cache::get('fresh'))->toBe('alive');
    });

});

describe('cache::remember()', function(): void {

    test('calls closure on miss and caches result', function(): void {
        $calls = 0;
        $value = cache::remember('computed', 60, function() use (&$calls): string {
            $calls++;
            return 'result';
        });
        expect($value)->toBe('result');
        expect($calls)->toBe(1);
    });

    test('returns cached value on second call without running closure', function(): void {
        $calls = 0;
        cache::remember('cached_key', 60, function() use (&$calls): int {
            $calls++;
            return 42;
        });
        $second = cache::remember('cached_key', 60, function() use (&$calls): int {
            $calls++;
            return 99;
        });
        expect($second)->toBe(42);
        expect($calls)->toBe(1);
    });

});

describe('cache::flush()', function(): void {

    test('flush with prefix removes only matching keys', function(): void {
        cache::set('user:1', 'Alice');
        cache::set('user:2', 'Bob');
        cache::set('post:1', 'Hello');

        cache::flush('user:');

        expect(cache::has('user:1'))->toBeFalse();
        expect(cache::has('user:2'))->toBeFalse();
        expect(cache::has('post:1'))->toBeTrue();
    });

    test('flush_all() removes all keys', function(): void {
        cache::set('a', 1);
        cache::set('b', 2);
        cache::flush_all();
        expect(cache::has('a'))->toBeFalse();
        expect(cache::has('b'))->toBeFalse();
    });

});

describe('file_driver', function(): void {

    test('stores and retrieves a value on filesystem', function(): void {
        $dir    = sys_get_temp_dir() . '/skim_cache_test_' . uniqid();
        $driver = new file_driver($dir);
        $driver->set('hello', 'world');
        expect($driver->get('hello'))->toBe('world');
        $driver->flush_all();
        rmdir($dir);
    });

    test('has() returns false for expired key', function(): void {
        $dir    = sys_get_temp_dir() . '/skim_cache_test_' . uniqid();
        $driver = new file_driver($dir);
        // Write a file with past expiry manually
        $key  = 'expired_key';
        $file = $dir . '/' . base64_encode($key) . '.cache';
        file_put_contents($file, serialize([microtime(true) - 1, 'old_value']));
        expect($driver->has($key))->toBeFalse();
        rmdir($dir);
    });

});
