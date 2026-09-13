<?php declare(strict_types=1);

use Skim\Cli\Commands\MigrateCommand;

// Installed-layout regression: the framework lives in vendor/skim/framework
// while SKIM_ROOT points at a consumer application root. migrate:make must
// read the scaffold from the framework package (__DIR__-relative) and write
// the migration into the application root — never the reverse.
describe('migrate:make installed layout', function (): void {

    test('scaffold resolves package-relative, not from application root', function (): void {
        $classFile = (new \ReflectionClass(MigrateCommand::class))->getFileName();
        $scaffold  = dirname($classFile) . '/../Scaffolds/Migration.php';

        expect(is_file($scaffold))->toBeTrue();

        $src = file_get_contents($classFile);
        expect($src)->toContain("'/../Scaffolds/Migration.php'");
    });

    test('make creates migration in application root from package scaffold', function (): void {
        $appRoot = sys_get_temp_dir() . '/skim_app_' . uniqid();
        mkdir($appRoot, 0755, true);
        $prev = getcwd();
        chdir($appRoot);
        try {
            $cmd = new MigrateCommand();
            $cmd->setInput(['make', 'create_posts'], []);
            $code = $cmd->handle();

            expect($code)->toBe(0);
            $files = glob($appRoot . '/migrations/*.php');
            expect($files)->toHaveCount(1);

            $content = file_get_contents($files[0]);
            expect($content)->toContain('use Skim\\Db\\Migration;');
            expect($content)->toContain('extends Migration');

            exec('php -l ' . escapeshellarg($files[0]) . ' 2>&1', $out, $exit);
            expect($exit)->toBe(0);
        } finally {
            chdir($prev);
            foreach (glob($appRoot . '/migrations/*.php') ?: [] as $f) {
                unlink($f);
            }
            if (is_dir($appRoot . '/migrations')) {
                rmdir($appRoot . '/migrations');
            }
            rmdir($appRoot);
        }
    });

});
