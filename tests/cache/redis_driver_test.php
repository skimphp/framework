<?php declare(strict_types=1);

use Skim\Cache\RedisDriver;

describe('RedisDriver', function(): void {

    beforeEach(function(): void {
        // dedicated test database — flushDB is exercised here safely
        $this->driver = new RedisDriver(testRedisHost(), 6379, null, 15, 'rt_');
    });

    afterEach(function(): void {
        $this->driver->flushAll();
    });

    test('set/get/has/delete round-trip', function(): void {
        expect($this->driver->set('k', ['a' => 1]))->toBeTrue();
        expect($this->driver->has('k'))->toBeTrue();
        expect($this->driver->get('k'))->toBe(['a' => 1]);
        expect($this->driver->get('missing', 'd'))->toBe('d');

        expect($this->driver->delete('k'))->toBeTrue();
        expect($this->driver->has('k'))->toBeFalse();
    });

    test('set with ttl stores the value', function(): void {
        $this->driver->set('exp', 'v', 60);
        expect($this->driver->get('exp'))->toBe('v');
    });

    test('flush(prefix) removes only matching keys', function(): void {
        $this->driver->set('user:1', 'a');
        $this->driver->set('user:2', 'b');
        $this->driver->set('other', 'c');

        $this->driver->flush('user:');
        expect($this->driver->has('user:1'))->toBeFalse();
        expect($this->driver->has('other'))->toBeTrue();
    });

    test('flushAll clears the test database', function(): void {
        $this->driver->set('x', 1);
        $this->driver->flushAll();
        expect($this->driver->has('x'))->toBeFalse();
    });

    test('tags() returns a tag-scoped proxy', function(): void {
        $proxy = $this->driver->tags(['t1']);
        expect($proxy)->toBeInstanceOf(\Skim\Cache\TaggedRedisDriver::class);
    });

});
