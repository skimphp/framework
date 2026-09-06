<?php declare(strict_types=1);

use Skim\Db\Db;
use Skim\Db\Migrator;
use Skim\Db\Migration;

function makeMigrationDir(): string {
    $dir = sys_get_temp_dir() . '/skim_mig_' . uniqid();
    mkdir($dir);
    return $dir;
}

function writeMigration(string $dir, string $name, string $up, string $down, bool $transactional = true, bool $reversible = true): string {
    $file    = $dir . '/' . $name . '.php';
    $t       = $transactional ? 'true' : 'false';
    $r       = $reversible ? 'true' : 'false';
    $content = <<<PHP
    <?php
    return new class extends \\Skim\\Db\\Migration {
        public bool \$transactional = {$t};
        public bool \$reversible    = {$r};
        public function up(): string|array|callable   { return "{$up}"; }
        public function down(): string|array|callable { return "{$down}"; }
    };
    PHP;
    file_put_contents($file, $content);
    return $file;
}

function setupMigratorDb(): void {
    Db::reset();
    Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
}

describe('Migrator::run()', function(): void {

    test('runs pending migrations and records them in _migrations table', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_posts',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY)',
            'DROP TABLE posts',
        );

        $mig = new Migrator($dir);
        $ran = $mig->run();

        expect($ran)->toHaveCount(1)
                     ->toContain('2024_01_01_000001_create_posts.php');

        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        expect($tables)->not->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('is idempotent — already-run migrations are skipped', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_tags',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY)',
            'DROP TABLE tags',
        );

        $mig = new Migrator($dir);
        $mig->run();
        $ranAgain = $mig->run();

        expect($ranAgain)->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('returns empty array when no migration files present', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        $mig = new Migrator($dir);
        expect($mig->run())->toBeEmpty();
        rmdir($dir);
    });

    test('records checksum and execution_ms for each migration', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_logs',
            'CREATE TABLE logs (id INTEGER PRIMARY KEY)',
            'DROP TABLE logs',
        );

        $mig = new Migrator($dir);
        $mig->run();

        $row = Db::row('SELECT * FROM _migrations', connection: 'default');
        expect($row['checksum'])->not->toBe('');
        expect((int) $row['execution_ms'])->toBeGreaterThanOrEqual(0);

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('respects transactional = false', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_notx',
            'CREATE TABLE notx (id INTEGER PRIMARY KEY)',
            'DROP TABLE notx',
            transactional: false,
        );

        $mig = new Migrator($dir);
        $ran = $mig->run();
        expect($ran)->toHaveCount(1);

        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='notx'");
        expect($tables)->not->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('fires before and after hooks', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_hooks',
            'CREATE TABLE hooks (id INTEGER PRIMARY KEY)',
            'DROP TABLE hooks',
        );

        $before = [];
        $after  = [];
        $mig = new Migrator($dir);
        $mig->before(function(Migration $m) use (&$before): void {
            $before[] = $m->filename;
        });
        $mig->after(function(Migration $m, int $ms) use (&$after): void {
            $after[] = [$m->filename, $ms];
        });

        $mig->run();
        expect($before)->toContain('2024_01_01_000001_create_hooks.php');
        expect($after)->toHaveCount(1);
        expect($after[0][0])->toBe('2024_01_01_000001_create_hooks.php');
        expect($after[0][1])->toBeGreaterThanOrEqual(0);

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('pretend mode collects SQL without executing', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_pretend',
            'CREATE TABLE pretend (id INTEGER PRIMARY KEY)',
            'DROP TABLE pretend',
        );

        $mig = new Migrator($dir);
        $ran = $mig->run(pretend: true);

        expect($ran)->toHaveCount(1);
        expect($ran[0]['filename'])->toBe('2024_01_01_000001_create_pretend.php');
        expect($ran[0]['sql'])->toContain('CREATE TABLE pretend (id INTEGER PRIMARY KEY)');

        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='pretend'");
        expect($tables)->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('checksum guard blocks changed migrations', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        $file = writeMigration($dir, '2024_01_01_000001_create_guard',
            'CREATE TABLE guard (id INTEGER PRIMARY KEY)',
            'DROP TABLE guard',
        );

        $mig = new Migrator($dir);
        $mig->run();

        file_put_contents($file, str_replace('guard', 'guard2', file_get_contents($file)));

        expect(fn() => $mig->run())->toThrow(\RuntimeException::class, 'changed after it was applied');

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('force flag bypasses checksum guard', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        $file = writeMigration($dir, '2024_01_01_000001_create_force',
            'CREATE TABLE force_tbl (id INTEGER PRIMARY KEY)',
            'DROP TABLE force_tbl',
        );

        $mig = new Migrator($dir);
        $mig->run();
        file_put_contents($file, str_replace('force_tbl', 'force_tbl2', file_get_contents($file)));

        $ran = $mig->run(force: true);
        expect($ran)->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

});

describe('Migrator::down()', function(): void {

    test('rolls back last batch', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_orders',
            'CREATE TABLE orders (id INTEGER PRIMARY KEY)',
            'DROP TABLE orders',
        );

        $mig = new Migrator($dir);
        $mig->run();
        $rolled = $mig->down();

        expect($rolled)->toContain('2024_01_01_000001_create_orders.php');

        $tables = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name='orders'");
        expect($tables)->toBeEmpty();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('returns empty array when nothing to roll back', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        $mig = new Migrator($dir);
        expect($mig->down())->toBeEmpty();
        rmdir($dir);
    });

    test('throws on missing migration file by default', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_missing',
            'CREATE TABLE missing (id INTEGER PRIMARY KEY)',
            'DROP TABLE missing',
        );

        $mig = new Migrator($dir);
        $mig->run();
        array_map('unlink', glob($dir . '/*.php') ?: []);

        expect(fn() => $mig->down())->toThrow(\RuntimeException::class, 'not found, cannot rollback');
        rmdir($dir);
    });

    test('skips missing file when skipMissing is true', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_skip',
            'CREATE TABLE skip_tbl (id INTEGER PRIMARY KEY)',
            'DROP TABLE skip_tbl',
        );

        $mig = new Migrator($dir);
        $mig->run();
        array_map('unlink', glob($dir . '/*.php') ?: []);

        $rolled = $mig->down(skipMissing: true);
        expect($rolled)->toBeEmpty();

        rmdir($dir);
    });

    test('respects transactional = false', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_down_notx',
            'CREATE TABLE down_notx (id INTEGER PRIMARY KEY)',
            'DROP TABLE down_notx',
            transactional: false,
        );

        $mig = new Migrator($dir);
        $mig->run();
        $rolled = $mig->down();
        expect($rolled)->toHaveCount(1);

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('fires before and after hooks', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_down_hooks',
            'CREATE TABLE down_hooks (id INTEGER PRIMARY KEY)',
            'DROP TABLE down_hooks',
        );

        $before = [];
        $after  = [];
        $mig = new Migrator($dir);
        $mig->before(function(Migration $m) use (&$before): void {
            $before[] = $m->filename;
        });
        $mig->after(function(Migration $m, int $ms) use (&$after): void {
            $after[] = $m->filename;
        });

        $mig->run();
        $mig->down();

        expect($before)->toContain('2024_01_01_000001_create_down_hooks.php');
        expect($after)->toContain('2024_01_01_000001_create_down_hooks.php');

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

});

describe('Migrator::status()', function(): void {

    test('reports pending and applied status for each migration', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_items',
            'CREATE TABLE items (id INTEGER PRIMARY KEY)',
            'DROP TABLE items',
        );
        writeMigration($dir, '2024_01_01_000002_create_cats',
            'CREATE TABLE cats (id INTEGER PRIMARY KEY)',
            'DROP TABLE cats',
        );

        $mig = new Migrator($dir);
        $mig->run();

        writeMigration($dir, '2024_01_01_000003_create_dogs',
            'CREATE TABLE dogs (id INTEGER PRIMARY KEY)',
            'DROP TABLE dogs',
        );

        $status = $mig->status();
        $statuses = array_column($status, 'status');

        expect($statuses)->toContain('applied');
        expect($statuses)->toContain('pending');

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('includes checksum_ok, applied_at, and execution_ms', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_meta',
            'CREATE TABLE meta (id INTEGER PRIMARY KEY)',
            'DROP TABLE meta',
        );

        $mig = new Migrator($dir);
        $mig->run();

        $status = $mig->status();
        $applied = array_values(array_filter($status, fn($s) => $s['status'] === 'applied'))[0];

        expect($applied['checksum_ok'])->toBeTrue();
        expect($applied['applied_at'])->not->toBeNull();
        expect((int) $applied['execution_ms'])->toBeGreaterThanOrEqual(0);

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    test('checksum_ok is false when file is missing', function(): void {
        setupMigratorDb();
        $dir = makeMigrationDir();
        writeMigration($dir, '2024_01_01_000001_create_missing_file',
            'CREATE TABLE missing_file (id INTEGER PRIMARY KEY)',
            'DROP TABLE missing_file',
        );

        $mig = new Migrator($dir);
        $mig->run();
        array_map('unlink', glob($dir . '/*.php') ?: []);

        $status = $mig->status();
        $applied = array_values(array_filter($status, fn($s) => $s['status'] === 'applied'))[0];

        expect($applied['checksum_ok'])->toBeFalse();

        rmdir($dir);
    });

});

describe('migration base class', function(): void {

    test('default down() returns empty string', function(): void {
        $m = new class extends Migration {
            public function up(): string|array|callable { return ''; }
        };
        expect($m->down())->toBe('');
    });

    test('irreversible migration throws on down()', function(): void {
        $m = new class extends Migration {
            public bool $reversible = false;
            public function up(): string|array|callable { return ''; }
        };
        $m->filename = 'test.php';
        expect(fn() => $m->down())->toThrow(\LogicException::class, 'irreversible');
    });

});
