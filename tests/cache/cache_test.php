<?php declare(strict_types=1);

use Skim\Cache\Cache;
use Skim\Cache\ArrayDriver;
use Skim\Cache\FileDriver;

beforeEach(function(): void {
    \Skim\Cache\Cache::setDriver(new \Skim\Cache\ArrayDriver());
});

describe('cache — set / get / has / delete', function(): void {

    test('stores and retrieves a value', function(): void {
        \Skim\Cache\Cache::set('key1', 'hello');
        expect(\Skim\Cache\Cache::get('key1'))->toBe('hello');
    });

    test('has() returns true for existing key', function(): void {
        \Skim\Cache\Cache::set('exists', true);
        expect(\Skim\Cache\Cache::has('exists'))->toBeTrue();
    });

    test('has() returns false for absent key', function(): void {
        expect(\Skim\Cache\Cache::has('absent_xyz'))->toBeFalse();
    });

    test('get() returns default when key absent', function(): void {
        expect(\Skim\Cache\Cache::get('no_key', 'default'))->toBe('default');
    });

    test('delete() removes the key', function(): void {
        \Skim\Cache\Cache::set('to_delete', 42);
        \Skim\Cache\Cache::delete('to_delete');
        expect(\Skim\Cache\Cache::has('to_delete'))->toBeFalse();
    });

    test('stores complex nested array', function(): void {
        $data = ['users' => [['id' => 1, 'name' => 'John']]];
        \Skim\Cache\Cache::set('data', $data);
        expect(\Skim\Cache\Cache::get('data'))->toBe($data);
    });

});

describe('cache — TTL expiry (array driver)', function(): void {

    test('key expires after TTL', function(): void {
        $driver = new \Skim\Cache\ArrayDriver();
        \Skim\Cache\Cache::setDriver($driver);

        // set with 1 microsecond ttl by writing directly to simulate expiry
        $driver->set('expiring', 'value', 1);
        // sleep 2 seconds to exceed TTL
        // use a hack: set with past expiry via reflection or just test get returns default
        sleep(2);
        expect(\Skim\Cache\Cache::get('expiring', 'expired'))->toBe('expired');
    })->skip('requires sleep — run manually');

    test('key persists within TTL', function(): void {
        $driver = new \Skim\Cache\ArrayDriver();
        $driver->set('fresh', 'alive', 3600);
        \Skim\Cache\Cache::setDriver($driver);
        expect(\Skim\Cache\Cache::get('fresh'))->toBe('alive');
    });

});

describe('Cache::remember()', function(): void {

    test('calls closure on miss and caches result', function(): void {
        $calls = 0;
        $value = \Skim\Cache\Cache::remember('computed', 60, function() use (&$calls): string {
            $calls++;
            return 'result';
        });
        expect($value)->toBe('result');
        expect($calls)->toBe(1);
    });

    test('returns cached value on second call without running closure', function(): void {
        $calls = 0;
        \Skim\Cache\Cache::remember('cached_key', 60, function() use (&$calls): int {
            $calls++;
            return 42;
        });
        $second = \Skim\Cache\Cache::remember('cached_key', 60, function() use (&$calls): int {
            $calls++;
            return 99;
        });
        expect($second)->toBe(42);
        expect($calls)->toBe(1);
    });

});

describe('Cache::flush()', function(): void {

    test('flush with prefix removes only matching keys', function(): void {
        \Skim\Cache\Cache::set('user:1', 'Alice');
        \Skim\Cache\Cache::set('user:2', 'Bob');
        \Skim\Cache\Cache::set('post:1', 'Hello');

        \Skim\Cache\Cache::flush('user:');

        expect(\Skim\Cache\Cache::has('user:1'))->toBeFalse();
        expect(\Skim\Cache\Cache::has('user:2'))->toBeFalse();
        expect(\Skim\Cache\Cache::has('post:1'))->toBeTrue();
    });

    test('flushAll() removes all keys', function(): void {
        \Skim\Cache\Cache::set('a', 1);
        \Skim\Cache\Cache::set('b', 2);
        \Skim\Cache\Cache::flushAll();
        expect(\Skim\Cache\Cache::has('a'))->toBeFalse();
        expect(\Skim\Cache\Cache::has('b'))->toBeFalse();
    });

});

describe('FileDriver', function(): void {

    test('stores and retrieves a value on filesystem', function(): void {
        $dir    = sys_get_temp_dir() . '/skim_cache_test_' . uniqid();
        $driver = new \Skim\Cache\FileDriver($dir);
        $driver->set('hello', 'world');
        expect($driver->get('hello'))->toBe('world');
        $driver->flushAll();
        rmdir($dir);
    });

    test('has() returns false for expired key', function(): void {
        $dir    = sys_get_temp_dir() . '/skim_cache_test_' . uniqid();
        $driver = new \Skim\Cache\FileDriver($dir);
        // Write a file with past expiry manually
        $key  = 'expired_key';
        $file = $dir . '/' . base64_encode($key) . '.cache';
        file_put_contents($file, serialize([microtime(true) - 1, 'old_value']));
        expect($driver->has($key))->toBeFalse();
        rmdir($dir);
    });

});
