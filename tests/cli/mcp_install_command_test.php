<?php declare(strict_types=1);

use Skim\Dev\Docs\Commands\McpInstallCommand;

describe('mcp:install command', function(): void {

    test('skips download when binary already exists', function(): void {
        $binDir = basePath('.skim/bin');
        $binPath = $binDir . '/skim-mcp' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        if (!is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }
        file_put_contents($binPath, 'fake-binary');

        try {
            $cmd = new McpInstallCommand();
            $cmd->setInput([], []);
            ob_start();
            $code = $cmd->handle();
            $out = ob_get_clean();

            expect($code)->toBe(0);
            expect($out)->toContain('already exists');
        } finally {
            @unlink($binPath);
        }
    });

});
