<?php declare(strict_types=1);

use skim\db\db;
use skim\db\model;
use skim\db\exceptions\not_found_exception;

// All DB tests use SQLite :memory: — no real MySQL required.
// Schema created inline before each test group.

function setup_test_db(): void {
    db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
    db::query('CREATE TABLE users (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT NOT NULL,
        email      TEXT NOT NULL,
        status     TEXT NOT NULL DEFAULT \'active\',
        created_at TEXT,
        updated_at TEXT
    )');
}

class test_user extends model {
    protected static string $table   = 'users';
    protected static array  $guarded = ['id', 'created_at', 'updated_at'];
}

describe('model::find()', function(): void {

    beforeEach(function(): void {
        setup_test_db();
    });

    test('returns null when record does not exist', function(): void {
        expect(test_user::find(999))->toBeNull();
    });

    test('returns model instance when record exists', function(): void {
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'John', 'email' => 'j@j.com', 'status' => 'active']]);
        $user = test_user::find(1);
        expect($user)->toBeInstanceOf(test_user::class);
        expect($user->name)->toBe('John');
    });

});

describe('model::find_or_fail()', function(): void {

    beforeEach(function(): void {
        setup_test_db();
    });

    test('throws not_found_exception when record missing', function(): void {
        expect(fn() => test_user::find_or_fail(999))
            ->toThrow(not_found_exception::class);
    });

    test('returns model instance when found', function(): void {
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'Jane', 'email' => 'j@j.com', 'status' => 'active']]);
        $user = test_user::find_or_fail(1);
        expect($user)->toBeInstanceOf(test_user::class);
    });

});

describe('model::create() and save() INSERT', function(): void {

    beforeEach(function(): void {
        setup_test_db();
    });

    test('create() inserts a row and returns hydrated model', function(): void {
        $user = test_user::create(['name' => 'Alice', 'email' => 'a@a.com', 'status' => 'active']);
        expect($user->id)->toBe(1);
        expect($user->name)->toBe('Alice');
    });

    test('create() ignores guarded fields', function(): void {
        $user = test_user::create(['name' => 'Bob', 'email' => 'b@b.com', 'id' => 999]);
        // id should be auto-assigned, not 999
        expect($user->id)->not->toBe(999);
    });

});

describe('model::save() UPDATE with dirty tracking', function(): void {

    beforeEach(function(): void {
        setup_test_db();
    });

    test('UPDATE executes only when attribute changes', function(): void {
        $user = test_user::create(['name' => 'Carol', 'email' => 'c@c.com', 'status' => 'active']);
        $user->name = 'Caroline';
        $user->save();

        $reloaded = test_user::find($user->id);
        expect($reloaded->name)->toBe('Caroline');
    });

    test('save() is a no-op when nothing changed', function(): void {
        $user = test_user::create(['name' => 'Dave', 'email' => 'd@d.com', 'status' => 'active']);
        // Should not throw or execute unnecessary query
        $user->save();
        expect($user->name)->toBe('Dave');
    });

});

describe('model::delete()', function(): void {

    beforeEach(function(): void {
        setup_test_db();
    });

    test('removes the record from the database', function(): void {
        $user = test_user::create(['name' => 'Eve', 'email' => 'e@e.com', 'status' => 'active']);
        $user->delete();
        expect(test_user::find($user->id))->toBeNull();
    });

});

describe('model::schema() — schema fetch', function(): void {

    beforeEach(function(): void {
        setup_test_db();
    });

    test('column_names() returns all column names from the table', function(): void {
        $cols = test_user::column_names();
        expect($cols)->toContain('id')
                     ->toContain('name')
                     ->toContain('email')
                     ->toContain('status');
    });

    test('schema() returns structured column definitions with Field key', function(): void {
        $schema = test_user::schema();
        expect($schema)->toBeArray()->not->toBeEmpty();
        expect($schema[0])->toHaveKey('Field');
    });

    test('schema() result is cached — second call returns same array', function(): void {
        $first  = test_user::schema();
        $second = test_user::schema();
        expect($first)->toBe($second);
    });

});

describe('model::delete_where()', function(): void {

    beforeEach(function(): void {
        setup_test_db();
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'A', 'email' => 'a@a.com', 'status' => 'active']]);
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'B', 'email' => 'b@b.com', 'status' => 'inactive']]);
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'C', 'email' => 'c@c.com', 'status' => 'active']]);
    });

    test('deletes only rows matching conditions', function(): void {
        $affected = test_user::delete_where(['status' => 'inactive']);
        expect($affected)->toBe(1);
        expect(test_user::where([])->count())->toBe(2);
    });

    test('does not delete unmatched rows', function(): void {
        test_user::delete_where(['status' => 'inactive']);
        $remaining = test_user::where(['status' => 'active'])->all();
        expect(count($remaining))->toBe(2);
    });

});

describe('model::find_by()', function(): void {

    beforeEach(function(): void {
        setup_test_db();
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']]);
    });

    test('returns model when column matches', function(): void {
        $user = test_user::find_by('email', 'alice@example.com');
        expect($user)->toBeInstanceOf(test_user::class);
        expect($user->name)->toBe('Alice');
    });

    test('returns null when no match', function(): void {
        expect(test_user::find_by('email', 'nobody@example.com'))->toBeNull();
    });

    test('throws on invalid column name', function(): void {
        expect(fn() => test_user::find_by('bad-col!', 'val'))
            ->toThrow(\InvalidArgumentException::class);
    });

});

describe('model::where() query scope', function(): void {

    beforeEach(function(): void {
        setup_test_db();
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'A', 'email' => 'a@a.com', 'status' => 'active']]);
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'B', 'email' => 'b@b.com', 'status' => 'inactive']]);
        db::query('INSERT INTO users %values%', ['values' => ['name' => 'C', 'email' => 'c@c.com', 'status' => 'active']]);
    });

    test('filters by condition array', function(): void {
        $active = test_user::where(['status' => 'active'])->all();
        expect(count($active))->toBe(2);
    });

    test('count() returns number of matching rows', function(): void {
        expect(test_user::where(['status' => 'active'])->count())->toBe(2);
    });

    test('order() sorts results', function(): void {
        $users = test_user::where([])->order('name DESC')->all();
        expect($users[0]->name)->toBe('C');
    });

    test('limit() restricts result count', function(): void {
        $users = test_user::where([])->limit(1)->all();
        expect(count($users))->toBe(1);
    });

    test('paginate() returns pagination object with correct totals', function(): void {
        $page = test_user::where([])->paginate(page: 1, per_page: 2);
        expect($page->total)->toBe(3);
        expect($page->pages)->toBe(2);
        expect(count($page->items))->toBe(2);
    });

});
