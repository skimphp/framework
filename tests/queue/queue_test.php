<?php declare(strict_types=1);

use Skim\Queue\BaseJob;
use Skim\Queue\Queue;

class TestQueueJob extends BaseJob {
    public function __construct(public int $value = 0) {}
    public function handle(): void {}
}

class TestDelayedJob extends BaseJob {
    public function delay(): int { return 3600; }
    public function handle(): void {}
}

describe('Queue', function(): void {

    beforeEach(function(): void {
        Queue::redis(); // connect via cache.redis config (redis service)
        Queue::flush('default');
        Queue::flush('testq');
        Queue::redis()->del('skim:queue:delayed');
    });

    afterEach(function(): void {
        Queue::flush('default');
        Queue::flush('testq');
        Queue::redis()->del('skim:queue:delayed');
    });

    test('serializeJob produces decodable payload', function(): void {
        $raw  = Queue::serializeJob(new TestQueueJob(5), 'default');
        $data = Queue::deserialize($raw);

        expect($data['class'])->toBe(TestQueueJob::class);
        expect($data['queue'])->toBe('default');
        expect($data['tries'])->toBe(3);
        expect($data['attempts'])->toBe(0);
        expect(unserialize($data['payload']))->toBeInstanceOf(TestQueueJob::class);
    });

    test('push and size reflect pending jobs', function(): void {
        Queue::push(new TestQueueJob(1));
        Queue::push(new TestQueueJob(2));

        expect(Queue::size())->toBe(2);
        Queue::flush();
        expect(Queue::size())->toBe(0);
    });

    test('pushMany enqueues a batch', function(): void {
        Queue::pushMany([new TestQueueJob(), new TestQueueJob(), new TestQueueJob()], 'testq');
        expect(Queue::size('testq'))->toBe(3);
    });

    test('delayed job sits in sorted set until due', function(): void {
        Queue::push(new TestDelayedJob(), 'testq');

        expect(Queue::size('testq'))->toBe(0);
        expect(Queue::promoteDelayed())->toBe(0); // not due yet
        expect(Queue::redis()->zcard('skim:queue:delayed'))->toBe(1);
    });

    test('promoteDelayed moves due jobs to their queue', function(): void {
        $payload = Queue::serializeJob(new TestQueueJob(9), 'testq');
        Queue::redis()->zadd('skim:queue:delayed', time() - 10, $payload);

        expect(Queue::promoteDelayed())->toBe(1);
        expect(Queue::size('testq'))->toBe(1);
        expect(Queue::redis()->zcard('skim:queue:delayed'))->toBe(0);
    });

});

describe('BaseJob', function(): void {

    test('defaults: 3 tries, no delay, silent failed()', function(): void {
        $job = new TestQueueJob();
        expect($job->tries())->toBe(3);
        expect($job->delay())->toBe(0);
        $job->failed(new \RuntimeException('x')); // must not rethrow
        expect(true)->toBeTrue();
    });

});
