<?php declare(strict_types=1);

use skim\core\config;
use skim\dev\docs\value\docs_generation_paths;

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
