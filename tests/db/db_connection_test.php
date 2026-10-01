<?php declare(strict_types=1);

use Skim\Db\Db;

// Connection-level behaviours: lastInsertId without liveness ping, and
// reconnect-and-retry-once on a dead pooled socket.
// SQLite :memory: everywhere except the reconnect test, which needs a
// temp file — a fresh :memory: connection would lose the schema.

function connPoolProp(string $name): ReflectionProperty {
    return new ReflectionProperty(Db::class, $name);
}

describe('Db::lastInsertId()', function(): void {

    beforeEach(function(): void {
        Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
        Db::query('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, v TEXT)');
    });

    test('returns the id of the last INSERT', function(): void {
        Db::query('INSERT INTO t %values%', ['values' => ['v' => 'a']]);
        expect(Db::lastInsertId())->toBe('1');
        Db::query('INSERT INTO t %values%', ['values' => ['v' => 'b']]);
        expect(Db::lastInsertId())->toBe('2');
    });

    test('does not go through pdo() — a ping would reset mysql_insert_id', function(): void {
        Db::query('INSERT INTO t %values%', ['values' => ['v' => 'a']]);
        $lastUsed = connPoolProp('lastUsed');
        $lastUsed->setValue(null, ['default' => 123.0]);   // stale sentinel

        expect(Db::lastInsertId())->toBe('1');
        expect($lastUsed->getValue()['default'])->toBe(123.0); // pdo() never ran
    });

    test('returns 0 when no connection is open', function(): void {
        Db::reset();
        expect(Db::lastInsertId())->toBe('0');
    });

});

describe('dead-socket retry', function(): void {

    test('query reconnects and retries once on driver error 2006', function(): void {
        $file = tempnam(sys_get_temp_dir(), 'skim_db_');
        \Skim\Core\Config::set('db.default', ['driver' => 'sqlite', 'database' => $file]);

        Db::connect('default', ['driver' => 'sqlite', 'database' => $file]);
        Db::query('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        Db::query('INSERT INTO t %values%', ['values' => ['v' => 'ok']]);

        // swap in a PDO that dies once on prepare — like a conn killed by wait_timeout
        $flaky = new class('sqlite:' . $file) extends \PDO {
            public bool $armed = true;
            public function prepare(string $query, array $options = []): \PDOStatement|false {
                if ($this->armed) {
                    $this->armed = false;
                    $e = new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
                    $e->errorInfo = ['HY000', 2006, 'gone'];
                    throw $e;
                }
                return parent::prepare($query, $options);
            }
        };

        connPoolProp('pool')->setValue(null, ['default' => $flaky]);
        connPoolProp('lastUsed')->setValue(null, ['default' => microtime(true)]);

        $rows = Db::all('SELECT * FROM t');
        expect($rows)->toHaveCount(1);
        expect($rows[0]['v'])->toBe('ok');

        unlink($file);
    });

    test('does not retry ordinary statement errors', function(): void {
        Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
        expect(fn() => Db::query('SELECT * FROM missing_table'))->toThrow(\PDOException::class);
    });

});

describe('idle liveness ping', function(): void {

    test('connection still works after a forced idle ping', function(): void {
        Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
        Db::query('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');

        // mark the pooled conn as long-idle → next pdo() pings SELECT 1
        connPoolProp('lastUsed')->setValue(null, ['default' => 0.0]);
        expect(Db::pdo())->toBeInstanceOf(\PDO::class);
        expect(Db::val('SELECT 42'))->toBe(42);
    });

});
