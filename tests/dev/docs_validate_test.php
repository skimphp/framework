<?php declare(strict_types=1);

use Skim\Dev\Docs\Commands\DocsValidateCommand;
use Skim\Cli\Cli;

describe('DocsValidateCommand reference validation', function(): void {
    $tempDir = '';

    beforeEach(function() use (&$tempDir): void {
        \Skim\Cli\Cli::forcePlain(true);
        $tempDir = sys_get_temp_dir() . '/skim_docs_validate_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Good class with valid references
        file_put_contents($tempDir . '/good.php', "<?php\n/**\n * #AI:class\n * #AI see_also: [target_class]\n */\nclass good_class {\n    /**\n     * #AI:foo\n     * #AI see_also: [target_class::bar]\n     * #AI aliases: [f]\n     */\n    public function foo(): void {}\n}\n");

        // Target class referenced by good_class
        file_put_contents($tempDir . '/target.php', "<?php\n/**\n * #AI:class\n */\nclass target_class {\n    /**\n     * #AI:bar\n     */\n    public function bar(): void {}\n}\n");

        // Bad class with dangling see_also and alias collision
        file_put_contents($tempDir . '/bad.php', "<?php\n/**\n * #AI:class\n * #AI see_also: [nonexistent_class]\n */\nclass bad_class {\n    /**\n     * #AI:doThing\n     * #AI see_also: [nonexistent_class::missing_method]\n     * #AI aliases: [do_thing]\n     */\n    public function do_thing(): void {}\n}\n");

        \Skim\Core\Config::set('docs.scan_paths', [$tempDir]);
        \Skim\Core\Config::set('docs.validate.min_coverage', 0.0);
    });

    afterEach(function() use (&$tempDir): void {
        if (is_dir($tempDir)) {
            foreach (glob($tempDir . '/*') ?: [] as $f) {
                unlink($f);
            }
            rmdir($tempDir);
        }
    });

    test('returns 1 when dangling see_also or alias collisions exist', function(): void {
        $command = new \Skim\Dev\Docs\Commands\DocsValidateCommand();
        ob_start();
        $code = $command->handle();
        $out = ob_get_clean();

        expect($code)->toBe(1);
        expect($out)->toContain('dangling see_also: bad_class -> nonexistent_class');
        expect($out)->toContain('dangling see_also: bad_class::do_thing -> nonexistent_class::missing_method');
        expect($out)->toContain("alias collision: bad_class::do_thing aliases 'do_thing' collides with real method name");
    });

    test('returns 0 when all references are valid', function(): void {
        // Remove bad file so only good classes remain
        $tempDir = sys_get_temp_dir() . '/skim_docs_validate_' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/good.php', "<?php\n/**\n * #AI:class\n * #AI see_also: [target_class2]\n */\nclass good_class2 {\n    /**\n     * #AI:foo\n     * #AI see_also: [target_class2::bar]\n     * #AI aliases: [f]\n     */\n    public function foo(): void {}\n}\n");
        file_put_contents($tempDir . '/target.php', "<?php\n/**\n * #AI:class\n */\nclass target_class2 {\n    /**\n     * #AI:bar\n     */\n    public function bar(): void {}\n}\n");
        \Skim\Core\Config::set('docs.scan_paths', [$tempDir]);

        $command = new \Skim\Dev\Docs\Commands\DocsValidateCommand();
        ob_start();
        $code = $command->handle();
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect($out)->not->toContain('dangling');
        expect($out)->not->toContain('collision');
    });
});
