<?php declare(strict_types=1);

use skim\dev\docs\emitter\llm_md_emitter;

function sample_llm_data(): array {
    return [
        'generated_at' => '2026-01-01T00:00:00+00:00',
        'classes'      => [
            [
                'class_name' => 'cache',
                'namespace'  => 'skim\\cache',
                'file'       => '/src/cache/cache.php',
                'summary'    => 'Static cache facade.',
                'lifecycle'  => 'boot',
                'owner'      => 'platform',
                'methods'    => [
                    [
                        'name'         => 'get',
                        'signature'    => 'public static function get(string $key, mixed $default): mixed',
                        'owner'        => 'skim\\cache\\cache',
                        'contracts'    => ['returns default when key absent'],
                        'invariants'   => ['never throws on miss'],
                        'non_goals'    => ['does not warm the cache'],
                        'side_effects' => [],
                        'lifecycle'    => '',
                        'perf'         => 'O(1)',
                        'throws'       => [],
                    ],
                ],
            ],
        ],
    ];
}

describe('llm_md_emitter', function(): void {

    test('writes llm.md file to the specified path', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(sample_llm_data(), $path);
        expect(file_exists($path))->toBeTrue();
        unlink($path);
    });

    test('generated file contains class index section', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(sample_llm_data(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('## Class index');
        expect($content)->toContain('`cache`');
        unlink($path);
    });

    test('generated file contains key invariants section', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(sample_llm_data(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('## Key invariants');
        expect($content)->toContain('never throws on miss');
        unlink($path);
    });

    test('generated file contains non-goals section', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(sample_llm_data(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('## Non-goals');
        expect($content)->toContain('does not warm the cache');
        unlink($path);
    });

    test('generated file contains method contracts section', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(sample_llm_data(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('## Method contracts');
        expect($content)->toContain('returns default when key absent');
        unlink($path);
    });

    test('generated file contains lifecycle map section', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(sample_llm_data(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('## Lifecycle map');
        expect($content)->toContain('boot');
        unlink($path);
    });

    test('throws RuntimeException when parent path is a file not a directory', function(): void {
        $blocker = sys_get_temp_dir() . '/skim_llm_blocker_' . uniqid();
        file_put_contents($blocker, 'I am a file, not a dir');
        $impossible = $blocker . '/nested/llm.md';
        expect(fn() => (new llm_md_emitter())->emit(sample_llm_data(), $impossible))
            ->toThrow(\RuntimeException::class);
        unlink($blocker);
    });

    test('handles empty classes array without error', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_empty_' . uniqid() . '.md';
        (new llm_md_emitter())->emit(['generated_at' => 'now', 'classes' => []], $path);
        expect(file_exists($path))->toBeTrue();
        unlink($path);
    });

});
