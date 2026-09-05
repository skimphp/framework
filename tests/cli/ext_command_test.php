<?php declare(strict_types=1);

use Skim\Cli\Cli;
use Skim\Cli\Commands\ExtListCommand;
use Skim\Ext\ExtRegistry;

describe('extension CLI commands', function(): void {
    $root = '';
    $remove = null;

    beforeEach(function() use (&$root, &$remove): void {
        \Skim\Cli\Cli::forcePlain(true);
        $root = sys_get_temp_dir() . '/skim_ext_command_' . bin2hex(random_bytes(4));
        mkdir($root . '/vendor/skim/mailer', 0777, true);

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
    });

    test('ext:list prints installed extensions from local registry', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/mailer/composer.json', json_encode([
            'name' => 'skim/mailer',
            'version' => '1.0.1',
            'description' => 'Email transport',
            'extra' => ['skim' => ['extension' => 'skim\\mailer\\mailer_extension']],
        ]));

        $command = new \Skim\Cli\Commands\ExtListCommand(new \Skim\Ext\ExtRegistry($root));

        ob_start();
        $code = $command->handle();
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect($out)->toContain('Installed SKIM extensions');
        expect($out)->toContain('skim/mailer');
        expect($out)->toContain('Email transport');
    });
});
