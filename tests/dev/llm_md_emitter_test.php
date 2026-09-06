<?php declare(strict_types=1);

use Skim\Dev\Docs\Emitter\LlmMdEmitter;

function sampleLlmData(): array {
    return [
        'generated_at' => '2026-01-01T00:00:00+00:00',
        'classes'      => [
            [
                'class_name' => 'Cache',
                'namespace'  => 'Skim\Cache',
                'file'       => '/src/Cache/Cache.php',
                'summary'    => 'Static cache facade.',
                'lifecycle'  => 'boot',
                'owner'      => 'platform',
                'methods'    => [
                    [
                        'name'         => 'get',
                        'signature'    => 'public static function get(string $key, mixed $default): mixed',
                        'owner'        => 'Skim\Cache\Cache',
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

describe('LlmMdEmitter', function(): void {

    test('writes llm.md file to the specified path', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $path);
        expect(file_exists($path))->toBeTrue();
        unlink($path);
    });

    test('generated file contains compact class section', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('## `Skim\Cache\Cache` — Cache');
        unlink($path);
    });

    test('generated file contains method invariant text', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('never throws on miss');
        unlink($path);
    });

    test('generated file contains non-goal text', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('does not warm the cache');
        unlink($path);
    });

    test('generated file contains grouped method contracts', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('### Methods');
        expect($content)->toContain('returns default when key absent');
        unlink($path);
    });

    test('generated file contains lifecycle metadata', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $path);
        $content = file_get_contents($path);
        expect($content)->toContain('boot');
        unlink($path);
    });

    test('throws RuntimeException when parent path is a file not a directory', function(): void {
        $blocker = sys_get_temp_dir() . '/skim_llm_blocker_' . uniqid();
        file_put_contents($blocker, 'I am a file, not a dir');
        $impossible = $blocker . '/nested/llm.md';
        expect(fn() => (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(sampleLlmData(), $impossible))
            ->toThrow(\RuntimeException::class);
        unlink($blocker);
    });

    test('handles empty classes array without error', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_empty_' . uniqid() . '.md';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit(['generated_at' => 'now', 'classes' => []], $path);
        expect(file_exists($path))->toBeTrue();
        unlink($path);
    });

    test('groups methods without explicit group under Methods heading', function(): void {
        $path = sys_get_temp_dir() . '/skim_llm_md_group_' . uniqid() . '.md';
        $data = sampleLlmData();
        $data['classes'][0]['methods'][0]['group'] = '';
        (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit($data, $path);
        $content = file_get_contents($path);
        expect($content)->toContain('### Methods');
        expect($content)->not->toMatch('/^### $/m');
        unlink($path);
    });

});
