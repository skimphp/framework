<?php declare(strict_types=1);

use skim\log\log;
use skim\log\log_handler;
use skim\log\null_handler;

// Spy handler — captures writes for assertions
class spy_handler implements log_handler {
    public array $records = [];
    public function write(string $level, string $message, array $context): void {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

beforeEach(function(): void {
    log::reset();
});

describe('log facade', function(): void {

    test('info() records correct level and message', function(): void {
        $spy = new spy_handler();
        log::set_handler($spy);
        log::info('User logged in', ['user_id' => 5]);
        expect($spy->records[0]['level'])->toBe('info');
        expect($spy->records[0]['message'])->toBe('User logged in');
        expect($spy->records[0]['context']['user_id'])->toBe(5);
    });

    test('error() records error level', function(): void {
        $spy = new spy_handler();
        log::set_handler($spy);
        log::error('DB connection failed');
        expect($spy->records[0]['level'])->toBe('error');
    });

    test('all level methods write with correct level', function(): void {
        $spy = new spy_handler();
        log::set_handler($spy);

        log::debug('d');
        log::notice('n');
        log::warning('w');
        log::critical('c');
        log::alert('a');
        log::emergency('e');

        $levels = array_column($spy->records, 'level');
        expect($levels)->toBe(['debug', 'notice', 'warning', 'critical', 'alert', 'emergency']);
    });

    test('null_handler discards all writes silently', function(): void {
        log::set_handler(new null_handler());
        log::error('should be discarded');
        expect(true)->toBeTrue();   // no exception thrown
    });

    test('custom handler receives context array', function(): void {
        $spy = new spy_handler();
        log::set_handler($spy);
        log::warning('Rate limit hit', ['ip' => '1.2.3.4', 'route' => '/api/v1/users']);
        expect($spy->records[0]['context'])->toBe(['ip' => '1.2.3.4', 'route' => '/api/v1/users']);
    });

});
