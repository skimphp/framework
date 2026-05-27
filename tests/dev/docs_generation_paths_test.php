<?php declare(strict_types=1);

use skim\core\config;
use skim\dev\docs\commands\docs_command;
use skim\dev\docs\value\docs_generation_paths;

function remove_docs_generation_dir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iter as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($dir);
}

describe('docs_generation_paths', function(): void {

    test('resolves source and output flags relative to base_path', function(): void {
        $paths = docs_generation_paths::from_flags([
            'source' => '.agents/skills/better-commenting/test/5',
            'output' => '.agents/skills/better-commenting/test/5/res_mdx',
        ]);

        expect($paths->scan_paths())->toBe([base_path('.agents/skills/better-commenting/test/5')]);
        expect($paths->json_path())->toBe(base_path('.agents/skills/better-commenting/test/5/res_mdx/llm.json'));
        expect($paths->llm_md_path())->toBe(base_path('.agents/skills/better-commenting/test/5/res_mdx/llm.md'));
        expect($paths->mdx_dir())->toBe(base_path('.agents/skills/better-commenting/test/5/res_mdx'));
    });

    test('keeps config defaults when no flags are supplied', function(): void {
        config::set('docs.scan_paths', [base_path('src')]);
        config::set('docs.output.json', base_path('custom/llm.json'));
        config::set('docs.output.llm_md', base_path('custom/llm.md'));
        config::set('docs.output.mdx_dir', base_path('custom/mdx'));

        $paths = docs_generation_paths::from_flags([]);

        expect($paths->scan_paths())->toBe([base_path('src')]);
        expect($paths->json_path())->toBe(base_path('custom/llm.json'));
        expect($paths->llm_md_path())->toBe(base_path('custom/llm.md'));
        expect($paths->mdx_dir())->toBe(base_path('custom/mdx'));
    });

});

describe('docs command path overrides', function(): void {

    test('writes json markdown and mdx into output override from source override', function(): void {
        $output = sys_get_temp_dir() . '/skim_docs_generation_' . uniqid();
        $cmd = new docs_command();
        $cmd->set_input([], [
            'source' => '.agents/skills/better-commenting/test/5',
            'output' => $output,
        ]);

        $code = $cmd->handle();

        expect($code)->toBe(0);
        expect(file_exists($output . '/llm.json'))->toBeTrue();
        expect(file_exists($output . '/llm.md'))->toBeTrue();
        expect(glob($output . '/*.mdx'))->toHaveCount(5);

        $data = json_decode((string) file_get_contents($output . '/llm.json'), associative: true);
        expect($data['classes'])->not->toBeEmpty();
        expect($data['classes'][0]['class_name'])->toBe('app');

        remove_docs_generation_dir($output);
    });

});
