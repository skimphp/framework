<?php declare(strict_types=1);

use Skim\Db\Db;
use Skim\Db\MigrationExecutor;

function setupExecutorDb(): void {
    Db::reset();
    Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
}

describe('MigrationExecutor', function(): void {

    test('executes SQL string via Db::query', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $executor->run('CREATE TABLE test1 (id INTEGER PRIMARY KEY)', 'default');

        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='test1'");
        expect($tables)->not->toBeEmpty();
    });

    test('executes array of SQL strings', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $executor->run([
            'CREATE TABLE test2 (id INTEGER PRIMARY KEY)',
            'INSERT INTO test2 (id) VALUES (1)',
        ], 'default');

        $count = Db::val('SELECT COUNT(*) FROM test2');
        expect((int) $count)->toBe(1);
    });

    test('executes callable and passes PDO', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $called = false;
        $executor->run(function(\PDO $pdo) use (&$called): void {
            $called = true;
            $pdo->query('CREATE TABLE test3 (id INTEGER PRIMARY KEY)');
        }, 'default');

        expect($called)->toBeTrue();
        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='test3'");
        expect($tables)->not->toBeEmpty();
    });

    test('ignores empty SQL strings', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $executor->run(['', '   '], 'default');
        expect(true)->toBeTrue();
    });

    test('collects SQL in pretend mode instead of executing', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $executor->pretend(true);
        $executor->run('CREATE TABLE pretend_test (id INTEGER PRIMARY KEY)', 'default');

        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='pretend_test'");
        expect($tables)->toBeEmpty();

        expect($executor->collected())->toContain('CREATE TABLE pretend_test (id INTEGER PRIMARY KEY)');
    });

    test('collects callable descriptor in pretend mode', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $executor->pretend(true);
        $fn = function(\PDO $pdo): void {};
        $executor->run($fn, 'default');

        $collected = $executor->collected();
        expect($collected)->toHaveCount(1);
        expect($collected[0])->toStartWith('callable: Closure@');
    });

    test('pretend returns static for chaining', function(): void {
        $executor = new MigrationExecutor();
        expect($executor->pretend())->toBeInstanceOf(MigrationExecutor::class);
    });

    test('handles mixed string and callable steps', function(): void {
        setupExecutorDb();
        $executor = new MigrationExecutor();
        $called = false;
        $executor->run([
            'CREATE TABLE test4 (id INTEGER PRIMARY KEY)',
            function(\PDO $pdo) use (&$called): void {
                $called = true;
                $pdo->query("INSERT INTO test4 (id) VALUES (2)");
            },
        ], 'default');

        expect($called)->toBeTrue();
        $count = Db::val('SELECT COUNT(*) FROM test4');
        expect((int) $count)->toBe(1);
    });

});
