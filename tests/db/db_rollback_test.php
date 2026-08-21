<?php declare(strict_types=1);

use skim\db\db;

describe('db::rollback_all()', function (): void {

    test('rolls back open transactions on all pooled connections', function (): void {
        db::reset();
        db::connect('default', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        db::query('CREATE TABLE test (id INTEGER PRIMARY KEY)');

        $pdo = db::pdo();
        $pdo->beginTransaction();
        db::query('INSERT INTO test (id) VALUES (1)');

        expect($pdo->inTransaction())->toBeTrue();

        db::rollback_all();

        expect($pdo->inTransaction())->toBeFalse();
    });

    test('is a no-op when no transactions are open', function (): void {
        db::reset();
        db::connect('default', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        expect(fn() => db::rollback_all())->not->toThrow(\Throwable::class);
    });

    test('rolls back transactions on multiple named connections', function (): void {
        db::reset();
        db::connect('default', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        db::connect('analytics', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $default = db::pdo('default');
        $analytics = db::pdo('analytics');

        $default->beginTransaction();
        $analytics->beginTransaction();

        db::rollback_all();

        expect($default->inTransaction())->toBeFalse();
        expect($analytics->inTransaction())->toBeFalse();
    });

});
