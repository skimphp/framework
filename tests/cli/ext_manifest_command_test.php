<?php declare(strict_types=1);

use Skim\Cli\Commands\CacheBuildCommand;
use Skim\Cli\Commands\ExtManifestCommand;
use Skim\Core\App;

class CmdManifestExt extends \Skim\Ext\Extension {
    public string $name = 'acme/x';
    public string $version = '1.0.0';
    public function register(App $app): void {}
    public function boot(App $app): void {}
}

describe('ext:manifest command', function(): void {

    test('missing or invalid class returns 1', function(): void {
        $cmd = new ExtManifestCommand();
        $cmd->setInput([], []);
        ob_start();
        $code = $cmd->handle();
        ob_end_clean();
        expect($code)->toBe(1);

        $cmd = new ExtManifestCommand();
        $cmd->setInput([\stdClass::class], []);
        ob_start();
        $code = $cmd->handle();
        ob_end_clean();
        expect($code)->toBe(1);
    });

    test('valid extension writes skim.json to app root', function(): void {
        $root = sys_get_temp_dir() . '/skim_extm_' . uniqid();
        mkdir($root);
        $prev = getcwd();
        chdir($root);
        try {
            $cmd = new ExtManifestCommand();
            $cmd->setInput([CmdManifestExt::class], []);
            ob_start();
            $code = $cmd->handle();
            ob_end_clean();

            expect($code)->toBe(0);
            $data = json_decode(file_get_contents($root . '/skim.json'), true);
            expect($data['name'])->toBe('acme/x');
        } finally {
            chdir($prev);
            @unlink($root . '/skim.json');
            @rmdir($root);
        }
    });

});

describe('cache:build command', function(): void {

    test('writes compiled env, config and extensions caches', function(): void {
        $root = sys_get_temp_dir() . '/skim_cb_' . uniqid();
        mkdir($root . '/config', 0755, true);
        file_put_contents($root . '/.env', "APP_ENV=local\n");
        file_put_contents($root . '/config/app.php', "<?php return ['name' => 'testapp'];\n");
        $prev = getcwd();
        chdir($root);
        try {
            $cmd = new CacheBuildCommand();
            $cmd->setInput([], []);
            ob_start();
            $code = $cmd->handle();
            ob_end_clean();

            expect($code)->toBe(0);
            $dir = $root . '/.skim/config_cache';
            expect(is_file($dir . '/env.php'))->toBeTrue();
            expect(is_file($dir . '/config.php'))->toBeTrue();
            expect(is_file($dir . '/extensions.php'))->toBeTrue();

            $env = require $dir . '/env.php';
            expect($env['APP_ENV'])->toBe('local');
            $config = require $dir . '/config.php';
            expect($config['app']['name'])->toBe('testapp');
        } finally {
            chdir($prev);
            foreach (glob($root . '/.skim/config_cache/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($root . '/.skim/config_cache');
            @rmdir($root . '/.skim');
            @unlink($root . '/.env');
            @unlink($root . '/config/app.php');
            @rmdir($root . '/config');
            @rmdir($root);
        }
    });

});
