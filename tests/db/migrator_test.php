<?php declare(strict_types=1);

use skim\db\db;
use skim\db\migrator;
use skim\db\migration;

// Creates temporary migration files on disk, runs them against SQLite :memory:.
// Cleaned up after each test.

function make_migration_dir(): string {
    $dir = sys_get_temp_dir() . '/skim_mig_' . uniqid();
    mkdir($dir);
    return $dir;
}

function write_migration(string $dir, string $name, string $up, string $down): string {
    $file    = $dir . '/' . $name . '.php';
    $content = <<<PHP
    <?php
    return new class extends \\skim\\db\\migration {
        public function up(): string   { return "{$up}"; }
        public function down(): string { return "{$down}"; }
    };
    PHP;
    file_put_contents($file, $content);
    return $file;
}

function setup_migrator_db(): void {
    db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
}

describe('migrator::run()', function(): void {

    test('runs pending migrations and records them in _migrations table', function(): void {
        setup_migrator_db();
        $dir = make_migration_dir();
        write_migration($dir, '2024_01_01_000001_create_posts',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY)',
            'DROP TABLE posts',
        );

        $mig = new migrator($dir);
        $ran = $mig->run();

        expect($ran)->toHaveCount(1)
                     ->toContain('2024_01_01_000001_create_posts.php');

        // Table should now exist
        $tables = db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        expect($tables)->not->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('is idempotent — already-run migrations are skipped', function(): void {
        setup_migrator_db();
        $dir = make_migration_dir();
        write_migration($dir, '2024_01_01_000001_create_tags',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY)',
            'DROP TABLE tags',
        );

        $mig = new migrator($dir);
        $mig->run();
        $ran_again = $mig->run();   // second call should return []

        expect($ran_again)->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('returns empty array when no migration files present', function(): void {
        setup_migrator_db();
        $dir = make_migration_dir();
        $mig = new migrator($dir);
        expect($mig->run())->toBeEmpty();
        rmdir($dir);
    });

});

describe('migrator::down()', function(): void {

    test('rolls back last batch', function(): void {
        setup_migrator_db();
        $dir = make_migration_dir();
        write_migration($dir, '2024_01_01_000001_create_orders',
            'CREATE TABLE orders (id INTEGER PRIMARY KEY)',
            'DROP TABLE orders',
        );

        $mig = new migrator($dir);
        $mig->run();
        $rolled = $mig->down();

        expect($rolled)->toContain('2024_01_01_000001_create_orders.php');

        // Table should be gone
        $tables = db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='orders'");
        expect($tables)->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('returns empty array when nothing to roll back', function(): void {
        setup_migrator_db();
        $dir = make_migration_dir();
        $mig = new migrator($dir);
        expect($mig->down())->toBeEmpty();
        rmdir($dir);
    });

});

describe('migrator::status()', function(): void {

    test('reports pending and applied status for each migration', function(): void {
        setup_migrator_db();
        $dir = make_migration_dir();
        write_migration($dir, '2024_01_01_000001_create_items',
            'CREATE TABLE items (id INTEGER PRIMARY KEY)',
            'DROP TABLE items',
        );
        write_migration($dir, '2024_01_01_000002_create_cats',
            'CREATE TABLE cats (id INTEGER PRIMARY KEY)',
            'DROP TABLE cats',
        );

        $mig = new migrator($dir);
        $mig->run();   // runs both

        $status = $mig->status();
        $statuses = array_column($status, 'status');

        expect($statuses)->toContain('applied');

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

});
