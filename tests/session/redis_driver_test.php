<?php declare(strict_types=1);

use Skim\Session\RedisSessionDriver;

describe('RedisSessionDriver', function(): void {

    beforeEach(function(): void {
        $this->redis = new Redis();
        $this->redis->connect(testRedisHost(), 6379);
        $this->driver = new RedisSessionDriver(testRedisHost(), 6379, null, 'testsess_', 7200);
    });

    afterEach(function(): void {
        foreach ($this->redis->keys('testsess_*') as $k) {
            $this->redis->del($k);
        }
        unset($_COOKIE['PHPSESSID']);
    });

    test('start generates an id and set/get/has/delete work', function(): void {
        $this->driver->start();
        expect($this->driver->id())->not->toBe('');

        $this->driver->set('name', 'bob');
        expect($this->driver->has('name'))->toBeTrue();
        expect($this->driver->get('name'))->toBe('bob');
        expect($this->driver->get('x', 'def'))->toBe('def');

        $this->driver->delete('name');
        expect($this->driver->has('name'))->toBeFalse();
    });

    test('data persists to redis under prefixed key', function(): void {
        $this->driver->start();
        $this->driver->set('k', 'v');

        $raw = $this->redis->get('testsess_' . $this->driver->id());
        expect(json_decode((string) $raw, true))->toBe(['k' => 'v']);
    });

    test('regenerate changes id and migrates data', function(): void {
        $this->driver->start();
        $this->driver->set('k', 'v');
        $old = $this->driver->id();

        $this->driver->regenerate();
        expect($this->driver->id())->not->toBe($old);
        expect($this->redis->exists('testsess_' . $old))->toBe(0);
        expect(json_decode((string) $this->redis->get('testsess_' . $this->driver->id()), true))->toBe(['k' => 'v']);
    });

    test('flush deletes session data', function(): void {
        $this->driver->start();
        $this->driver->set('k', 'v');
        $id = $this->driver->id();

        $this->driver->flush();
        expect($this->driver->get('k'))->toBeNull();
        expect($this->redis->exists('testsess_' . $id))->toBe(0);
    });

    test('existing cookie id resumes session data', function(): void {
        $this->redis->setex('testsess_existing1', 7200, json_encode(['a' => 1]));
        $_COOKIE['PHPSESSID'] = 'existing1';

        $this->driver->start();
        expect($this->driver->id())->toBe('existing1');
        expect($this->driver->get('a'))->toBe(1);
    });

});

describe('TaggedRedisDriver', function(): void {

    beforeEach(function(): void {
        $this->redis = new Redis();
        $this->redis->connect(testRedisHost(), 6379);
        $this->base = new \Skim\Cache\RedisDriver(testRedisHost(), 6379, null, 0, 'tagt_');
        $this->tagged = new \Skim\Cache\TaggedRedisDriver($this->base, $this->redis, 'tagt_', ['users']);
    });

    afterEach(function(): void {
        foreach ($this->redis->keys('tagt_*') as $k) {
            $this->redis->del($k);
        }
    });

    test('set registers key in tag set and get reads it', function(): void {
        $this->tagged->set('user:1', 'data');

        expect($this->tagged->get('user:1'))->toBe('data');
        expect($this->redis->smembers('tagt_tag:users'))->toContain('tagt_user:1');
    });

    test('flush deletes all tagged keys and the tag set', function(): void {
        $this->tagged->set('a', 1);
        $this->tagged->set('b', 2);
        $this->base->set('untagged', 'x');

        expect($this->tagged->flush())->toBeTrue();
        expect($this->tagged->get('a'))->toBeNull();
        expect($this->tagged->get('b'))->toBeNull();
        expect($this->base->get('untagged'))->toBe('x');
        expect($this->redis->exists('tagt_tag:users'))->toBe(0);
    });

});
