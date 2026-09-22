<?php declare(strict_types=1);

use Skim\Db\Db;
use Skim\Ext\ExtMigrator;

describe('ExtMigrator', function(): void {
    $root = '';
    $migrations = '';
    $remove = null;

    beforeEach(function() use (&$root, &$migrations, &$remove): void {
        \Skim\Db\Db::connect('default', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $root = sys_get_temp_dir() . '/skim_ext_migrator_' . bin2hex(random_bytes(4));
        $migrations = $root . '/migrations';
        mkdir($migrations, 0777, true);

        $remove = function(string $path) use (&$remove): void {
            if (!file_exists($path)) {
                return;
            }
            if (is_file($path)) {
                unlink($path);
                return;
            }
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
                $remove($path . '/' . $child);
            }
            rmdir($path);
        };
    });

    afterEach(function() use (&$root, &$remove): void {
        $remove($root);
        \Skim\Db\Db::reset();
    });

    test('runs pending migrations with namespaced filenames', function() use (&$migrations): void {
        file_put_contents($migrations . '/001_create_ext_items.php', <<<'PHP'
<?php declare(strict_types=1);

return new class extends \Skim\Db\Migration {
    public function up(): string {
        return 'CREATE TABLE ext_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)';
    }

    public function down(): string {
        return 'DROP TABLE IF EXISTS ext_items';
    }
};
PHP);

        $migrator = new \Skim\Ext\ExtMigrator();
        $applied = $migrator->run('skim/auth', $migrations);

        expect($applied)->toHaveCount(1);
        expect($applied[0]['tracking_filename'])->toBe('skim/auth: 001_create_ext_items.php');
        expect(\Skim\Db\Db::val("SELECT COUNT(*) FROM _migrations WHERE filename = :filename", [
            ':filename' => 'skim/auth: 001_create_ext_items.php',
        ]))->toBe(1);
        expect($migrator->run('skim/auth', $migrations))->toBe([]);
    });

    test('rollbackSession reverses only migrations from the current session', function() use (&$migrations): void {
        file_put_contents($migrations . '/001_create_ext_items.php', <<<'PHP'
<?php declare(strict_types=1);

return new class extends \Skim\Db\Migration {
    public function up(): string {
        return 'CREATE TABLE ext_items (id INTEGER PRIMARY KEY AUTOINCREMENT)';
    }

    public function down(): string {
        return 'DROP TABLE IF EXISTS ext_items';
    }
};
PHP);

        $migrator = new \Skim\Ext\ExtMigrator();
        $applied = $migrator->run('skim/auth', $migrations);
        $rolledBack = $migrator->rollbackSession($applied);

        expect($rolledBack)->toBe(['skim/auth: 001_create_ext_items.php']);
        expect(\Skim\Db\Db::row("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'ext_items'"))->toBeNull();
        expect(\Skim\Db\Db::val('SELECT COUNT(*) FROM _migrations'))->toBe(0);
    });

    test('rejects extension names containing a colon', function() use (&$migrations): void {
        (new \Skim\Ext\ExtMigrator())->run('bad:name', $migrations);
    })->throws(\InvalidArgumentException::class, '":"');

    test('rejects empty extension name', function() use (&$migrations): void {
        (new \Skim\Ext\ExtMigrator())->run('   ', $migrations);
    })->throws(\InvalidArgumentException::class);

    test('returns empty list and empty session when migrations dir missing', function(): void {
        $migrator = new \Skim\Ext\ExtMigrator();

        expect($migrator->run('skim/auth', '/nonexistent/path'))->toBe([]);
        expect($migrator->appliedThisSession())->toBe([]);
    });
});
