<?php declare(strict_types=1);

use skim\db\db;
use skim\cli\commands\ide_command;

describe('ide_command schema generation with SQLite', function(): void {
    $modelsDir = base_path('app/models');
    $modelFile = $modelsDir . '/post.php';
    $helperFile = storage_path('ide-helper.php');
    $hadModelsDir = false;
    $hadHelperFile = false;
    $oldHelperContent = '';

    beforeEach(function() use ($modelsDir, $modelFile, $helperFile, &$hadModelsDir, &$hadHelperFile, &$oldHelperContent): void {
        // Connect to an in-memory SQLite DB
        db::connect('default', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Create a test table
        db::query('CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            views INTEGER DEFAULT 0,
            is_active BOOLEAN
        )');

        // Prepare app/models directory
        if (is_dir($modelsDir)) {
            $hadModelsDir = true;
        } else {
            mkdir($modelsDir, 0755, true);
        }

        // Keep existing helper file if any
        if (file_exists($helperFile)) {
            $hadHelperFile = true;
            $oldHelperContent = file_get_contents($helperFile);
        }

        // Write a test model
        file_put_contents($modelFile, '<?php
namespace app\models;
class post extends \skim\db\model {
    protected static string $table = "posts";
}
');

        // Force reload/include model
        require_once $modelFile;
    });

    afterEach(function() use ($modelsDir, $modelFile, $helperFile, &$hadModelsDir, &$hadHelperFile, &$oldHelperContent): void {
        // Clean up written model file
        if (file_exists($modelFile)) {
            unlink($modelFile);
        }

        // Clean up models dir if we created it
        if (!$hadModelsDir && is_dir($modelsDir)) {
            rmdir($modelsDir);
        }

        // Restore helper file
        if ($hadHelperFile) {
            file_put_contents($helperFile, $oldHelperContent);
        } else if (file_exists($helperFile)) {
            unlink($helperFile);
        }

        // Reset db connection
        db::reset();
    });

    test('generates typed property stubs for SQLite models', function() use ($helperFile): void {
        $cmd = new ide_command();
        $cmd->set_input(['generate'], []);
        
        ob_start();
        $exitCode = $cmd->handle();
        $out = ob_get_clean();

        expect($exitCode)->toBe(0);
        expect(file_exists($helperFile))->toBeTrue();

        $content = file_get_contents($helperFile);
        expect($content)->toContain('namespace app\models;');
        expect($content)->toContain('class post {');
        expect($content)->toContain('public string $title;');
        expect($content)->toContain('public ?int $id;');
        expect($content)->toContain('public ?int $views;');
        expect($content)->toContain('public ?bool $is_active;');
    });
});
