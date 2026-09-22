<?php declare(strict_types=1);

use Skim\Cli\Commands\MigrateCommand;
use Skim\Db\Db;

function setupMigrateApp(): array {
    $root = sys_get_temp_dir() . '/skim_migapp_' . uniqid();
    mkdir($root . '/migrations', 0755, true);
    file_put_contents($root . '/migrations/2024_01_01_000001_create_posts.php', <<<'PHP'
    <?php
    return new class extends \Skim\Db\Migration {
        public function up(): string|array|callable   { return 'CREATE TABLE posts (id INTEGER PRIMARY KEY)'; }
        public function down(): string|array|callable { return 'DROP TABLE posts'; }
    };
    PHP);
    Db::reset();
    Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
    return [$root, getcwd()];
}

function cleanupMigrateApp(string $root, string $prev): void {
    chdir($prev);
    foreach (glob($root . '/migrations/*.php') ?: [] as $f) {
        unlink($f);
    }
    @rmdir($root . '/migrations');
    @rmdir($root);
}

describe('migrate command', function(): void {

    test('run executes pending migrations and reports files', function(): void {
        [$root, $prev] = setupMigrateApp();
        chdir($root);
        try {
            $cmd = new MigrateCommand();
            $cmd->setInput(['run'], []);
            ob_start();
            $code = $cmd->handle();
            $out = ob_get_clean();

            expect($code)->toBe(0);
            expect($out)->toContain('create_posts');
            expect(Db::val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='posts'"))->toBe(1);
        } finally {
            cleanupMigrateApp($root, $prev);
        }
    });

    test('run reports nothing pending when all applied', function(): void {
        [$root, $prev] = setupMigrateApp();
        chdir($root);
        try {
            (new \Skim\Db\Migrator($root . '/migrations'))->run();

            $cmd = new MigrateCommand();
            $cmd->setInput(['run'], []);
            ob_start();
            $code = $cmd->handle();
            $out = ob_get_clean();

            expect($code)->toBe(0);
            expect($out)->toContain('Nothing to migrate');
        } finally {
            cleanupMigrateApp($root, $prev);
        }
    });

    test('status prints applied and pending rows', function(): void {
        [$root, $prev] = setupMigrateApp();
        chdir($root);
        try {
            $cmd = new MigrateCommand();
            $cmd->setInput(['status'], []);
            ob_start();
            $code = $cmd->handle();
            $out = ob_get_clean();

            expect($code)->toBe(0);
            expect($out)->toContain('create_posts');
            expect($out)->toContain('pending');
        } finally {
            cleanupMigrateApp($root, $prev);
        }
    });

    test('down rolls back the applied migration', function(): void {
        [$root, $prev] = setupMigrateApp();
        chdir($root);
        try {
            (new \Skim\Db\Migrator($root . '/migrations'))->run();

            $cmd = new MigrateCommand();
            $cmd->setInput(['down'], []);
            ob_start();
            $code = $cmd->handle();
            $out = ob_get_clean();

            expect($code)->toBe(0);
            expect($out)->toContain('Rolled back');
            expect(Db::val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='posts'"))->toBe(0);
        } finally {
            cleanupMigrateApp($root, $prev);
        }
    });

    test('missing migrations directory warns and exits 0', function(): void {
        $root = sys_get_temp_dir() . '/skim_nomig_' . uniqid();
        mkdir($root);
        $prev = getcwd();
        chdir($root);
        try {
            $cmd = new MigrateCommand();
            $cmd->setInput(['run'], []);
            ob_start();
            $code = $cmd->handle();
            $out = ob_get_clean();

            expect($code)->toBe(0);
            expect($out)->toContain('not found');
        } finally {
            chdir($prev);
            rmdir($root);
        }
    });

    test('make without name returns error code', function(): void {
        $cmd = new MigrateCommand();
        $cmd->setInput(['make'], []);
        ob_start();
        $code = $cmd->handle();
        ob_end_clean();

        expect($code)->toBe(1);
    });

});
