<?php declare(strict_types=1);

use Skim\Log\Log;
use Skim\Log\LogHandler;
use Skim\Log\NullHandler;

// Spy handler — captures writes for assertions
class SpyHandler implements \Skim\Log\LogHandler {
    public array $records = [];
    public function write(string $level, string $message, array $context): void {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

beforeEach(function(): void {
    \Skim\Log\Log::reset();
});

describe('log facade', function(): void {

    test('info() records correct level and message', function(): void {
        $spy = new SpyHandler();
        \Skim\Log\Log::setHandler($spy);
        \Skim\Log\Log::info('User logged in', ['user_id' => 5]);
        expect($spy->records[0]['level'])->toBe('info');
        expect($spy->records[0]['message'])->toBe('User logged in');
        expect($spy->records[0]['context']['user_id'])->toBe(5);
    });

    test('error() records error level', function(): void {
        $spy = new SpyHandler();
        \Skim\Log\Log::setHandler($spy);
        \Skim\Log\Log::error('DB connection failed');
        expect($spy->records[0]['level'])->toBe('error');
    });

    test('all level methods write with correct level', function(): void {
        $spy = new SpyHandler();
        \Skim\Log\Log::setHandler($spy);

        \Skim\Log\Log::debug('d');
        \Skim\Log\Log::notice('n');
        \Skim\Log\Log::warning('w');
        \Skim\Log\Log::critical('c');
        \Skim\Log\Log::alert('a');
        \Skim\Log\Log::emergency('e');

        $levels = array_column($spy->records, 'level');
        expect($levels)->toBe(['debug', 'notice', 'warning', 'critical', 'alert', 'emergency']);
    });

    test('NullHandler discards all writes silently', function(): void {
        \Skim\Log\Log::setHandler(new \Skim\Log\NullHandler());
        \Skim\Log\Log::error('should be discarded');
        expect(true)->toBeTrue();   // no exception thrown
    });

    test('custom handler receives context array', function(): void {
        $spy = new SpyHandler();
        \Skim\Log\Log::setHandler($spy);
        \Skim\Log\Log::warning('Rate limit hit', ['ip' => '1.2.3.4', 'route' => '/api/v1/users']);
        expect($spy->records[0]['context'])->toBe(['ip' => '1.2.3.4', 'route' => '/api/v1/users']);
    });

});
