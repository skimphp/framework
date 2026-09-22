<?php declare(strict_types=1);

use Skim\Db\Db;

describe('Db::rollbackAll()', function (): void {

    test('rolls back open transactions on all pooled connections', function (): void {
        \Skim\Db\Db::reset();
        \Skim\Db\Db::connect('default', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        \Skim\Db\Db::query('CREATE TABLE test (id INTEGER PRIMARY KEY)');

        $pdo = \Skim\Db\Db::pdo();
        $pdo->beginTransaction();
        \Skim\Db\Db::query('INSERT INTO test (id) VALUES (1)');

        expect($pdo->inTransaction())->toBeTrue();

        \Skim\Db\Db::rollbackAll();

        expect($pdo->inTransaction())->toBeFalse();
    });

    test('is a no-op when no transactions are open', function (): void {
        \Skim\Db\Db::reset();
        \Skim\Db\Db::connect('default', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        expect(fn() => \Skim\Db\Db::rollbackAll())->not->toThrow(\Throwable::class);
    });

    test('rolls back transactions on multiple named connections', function (): void {
        \Skim\Db\Db::reset();
        \Skim\Db\Db::connect('default', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        \Skim\Db\Db::connect('alt', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $default = \Skim\Db\Db::pdo('default');
        $alt = \Skim\Db\Db::pdo('alt');

        $default->beginTransaction();
        $alt->beginTransaction();

        \Skim\Db\Db::rollbackAll();

        expect($default->inTransaction())->toBeFalse();
        expect($alt->inTransaction())->toBeFalse();
    });

});
