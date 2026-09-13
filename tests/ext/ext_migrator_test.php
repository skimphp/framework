<?php declare(strict_types=1);

use skim\db\db;
use skim\ext\ext_migrator;

describe('ext_migrator', function(): void {
    $root = '';
    $migrations = '';
    $remove = null;

    beforeEach(function() use (&$root, &$migrations, &$remove): void {
        db::connect('default', [
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
        db::reset();
    });

    test('runs pending migrations with namespaced filenames', function() use (&$migrations): void {
        file_put_contents($migrations . '/001_create_ext_items.php', <<<'PHP'
<?php declare(strict_types=1);

return new class extends \skim\db\migration {
    public function up(): string {
        return 'CREATE TABLE ext_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)';
    }

    public function down(): string {
        return 'DROP TABLE IF EXISTS ext_items';
    }
};
PHP);

        $migrator = new ext_migrator();
        $applied = $migrator->run('skim/auth', $migrations);

        expect($applied)->toHaveCount(1);
        expect($applied[0]['tracking_filename'])->toBe('skim/auth: 001_create_ext_items.php');
        expect(db::val("SELECT COUNT(*) FROM _migrations WHERE filename = :filename", [
            ':filename' => 'skim/auth: 001_create_ext_items.php',
        ]))->toBe(1);
        expect($migrator->run('skim/auth', $migrations))->toBe([]);
    });

    test('rollback_session reverses only migrations from the current session', function() use (&$migrations): void {
        file_put_contents($migrations . '/001_create_ext_items.php', <<<'PHP'
<?php declare(strict_types=1);

return new class extends \skim\db\migration {
    public function up(): string {
        return 'CREATE TABLE ext_items (id INTEGER PRIMARY KEY AUTOINCREMENT)';
    }

    public function down(): string {
        return 'DROP TABLE IF EXISTS ext_items';
    }
};
PHP);

        $migrator = new ext_migrator();
        $applied = $migrator->run('skim/auth', $migrations);
        $rolled_back = $migrator->rollback_session($applied);

        expect($rolled_back)->toBe(['skim/auth: 001_create_ext_items.php']);
        expect(db::row("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'ext_items'"))->toBeNull();
        expect(db::val('SELECT COUNT(*) FROM _migrations'))->toBe(0);
    });
});
