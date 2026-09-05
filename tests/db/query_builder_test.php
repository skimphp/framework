<?php declare(strict_types=1);

use Skim\Db\QueryBuilder;
use Skim\Db\NullMarker;

// These tests exercise query_builder directly — no PDO, no database required.
// Db::query() with debug:true delegates to QueryBuilder::interpolate() for assertions.

describe('QueryBuilder — %where% removal', function(): void {

    test('removed entirely when where array is empty', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM users %where%', ['where' => []]);
        expect($sql)->not->toContain('WHERE')
                         ->not->toContain('%where%');
    });

    test('removed when where key is absent from params', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM users %where% %limit%', ['limit' => 10]);
        expect($sql)->not->toContain('WHERE');
        expect($sql)->toContain('LIMIT 10');
    });

    test('injected with flat condition list', function(): void {
        [$sql, $params] = \Skim\Db\QueryBuilder::build('SELECT * FROM users %where%', [
            'where'   => ['status = :status'],
            ':status' => 'active',
        ]);
        expect($sql)->toContain('WHERE status = :status');
        expect($params[':status'])->toBe('active');
    });

    test('multiple flat conditions joined with AND', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM u %where%', [
            'where' => ['a = :a', 'b = :b'],
            ':a'    => 1,
            ':b'    => 2,
        ]);
        expect($sql)->toContain('a = :a AND b = :b');
    });

    test('or/and nesting generates grouped clauses', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM products %where%', [
            'where' => [
                'and' => [['category = :cat']],
                'or'  => [['name LIKE :s'], ['description LIKE :s']],
            ],
            ':cat' => 'electronics',
            ':s'   => '%phone%',
        ]);
        expect($sql)->toContain('OR')
                     ->toContain('category =');
    });

    test('null PDO param values are excluded from returned params array', function(): void {
        [, $params] = \Skim\Db\QueryBuilder::build('SELECT * FROM u %where%', [
            'where'   => ['status = :status'],
            ':status' => null,
            ':role'   => 'admin',
        ]);
        expect($params)->not->toHaveKey(':status');
        expect($params)->toHaveKey(':role');
    });

});

describe('QueryBuilder — %set%', function(): void {

    test('null values are silently skipped', function(): void {
        [$sql, $params] = \Skim\Db\QueryBuilder::build('UPDATE users %set% WHERE id = :id', [
            'set' => ['name' => 'John', 'avatar' => null],
            ':id' => 1,
        ]);
        expect($sql)->toContain('name = :name')
                     ->not->toContain('avatar');
        expect($params)->not->toHaveKey(':avatar');
    });

    test('NullMarker instance generates SET col = NULL', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('UPDATE users %set% WHERE id = :id', [
            'set' => ['avatar' => \Skim\Db\NullMarker::make()],
            ':id' => 1,
        ]);
        expect($sql)->toContain('avatar = NULL');
    });

    test('multiple columns generate correct SET clause', function(): void {
        [$sql, $params] = \Skim\Db\QueryBuilder::build('UPDATE u %set% WHERE id = :id', [
            'set' => ['name' => 'Jane', 'email' => 'j@j.com'],
            ':id' => 5,
        ]);
        expect($sql)->toContain('SET name = :name, email = :email');
        expect($params)->toHaveKey(':name')->toHaveKey(':email');
    });

});

describe('QueryBuilder — %values%', function(): void {

    test('generates correct INSERT column list and placeholders', function(): void {
        [$sql, $params] = \Skim\Db\QueryBuilder::build('INSERT INTO users %values%', [
            'values' => ['name' => 'John', 'email' => 'j@j.com'],
        ]);
        expect($sql)->toContain('(name, email) VALUES (:name, :email)');
        expect($params[':name'])->toBe('John');
        expect($params[':email'])->toBe('j@j.com');
    });

});

describe('QueryBuilder — %limit% and %offset%', function(): void {

    test('limit is inlined as integer', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM u %limit%', ['limit' => 20]);
        expect($sql)->toContain('LIMIT 20');
    });

    test('offset is inlined as integer', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM u %limit% %offset%', [
            'limit'  => 10,
            'offset' => 30,
        ]);
        expect($sql)->toContain('LIMIT 10')->toContain('OFFSET 30');
    });

    test('unused %placeholders% are stripped silently', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM u %where% %limit% %order_by%', []);
        expect($sql)->not->toContain('%')
                         ->not->toContain('WHERE')
                         ->not->toContain('LIMIT')
                         ->not->toContain('ORDER BY');
    });

});

describe('QueryBuilder — %order_by% and %group_by%', function(): void {

    test('order_by is substituted correctly', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT * FROM u %order_by%', ['order_by' => 'created_at DESC']);
        expect($sql)->toContain('ORDER BY created_at DESC');
    });

    test('group_by is substituted correctly', function(): void {
        [$sql] = \Skim\Db\QueryBuilder::build('SELECT status, COUNT(*) FROM u %group_by%', ['group_by' => 'status']);
        expect($sql)->toContain('GROUP BY status');
    });

});

describe('QueryBuilder::interpolate()', function(): void {

    test('substitutes named params with quoted string values', function(): void {
        $sql = \Skim\Db\QueryBuilder::interpolate("SELECT * FROM u WHERE status = :status", [':status' => 'active']);
        expect($sql)->toBe("SELECT * FROM u WHERE status = 'active'");
    });

    test('substitutes integer values without quotes', function(): void {
        $sql = \Skim\Db\QueryBuilder::interpolate("SELECT * FROM u WHERE id = :id", [':id' => 42]);
        expect($sql)->toBe("SELECT * FROM u WHERE id = 42");
    });

    test('substitutes null values as NULL keyword', function(): void {
        $sql = \Skim\Db\QueryBuilder::interpolate("SELECT * FROM u WHERE x = :x", [':x' => null]);
        expect($sql)->toBe("SELECT * FROM u WHERE x = NULL");
    });

    test('longer param names replaced before shorter (key-length sort)', function(): void {
        // :user_id must be replaced before :user to avoid partial replacement
        $sql = \Skim\Db\QueryBuilder::interpolate(
            "SELECT * FROM t WHERE user_id = :user_id AND tag = :user",
            [':user' => 'admin', ':user_id' => 5],
        );
        expect($sql)->toContain("user_id = 5")
                     ->toContain("tag = 'admin'");
    });

});
