<?php declare(strict_types=1);

use skim\dev\docs\emitter\mdx_emitter;

function sample_mdx_data(): array {
    return [
        'generated_at' => '2026-01-01T00:00:00+00:00',
        'classes'      => [
            [
                'class_name' => 'request',
                'namespace'  => 'skim\\core',
                'file'       => '/src/core/request.php',
                'summary'    => 'HTTP request abstraction.',
                'lifecycle'  => 'per-request',
                'owner'      => 'platform',
                'methods'    => [
                    [
                        'name'         => 'get',
                        'signature'    => 'public function get(string $key, mixed $default): mixed',
                        'owner'        => 'skim\\core\\request',
                        'contracts'    => ['returns query param by key'],
                        'invariants'   => [],
                        'non_goals'    => ['does not validate types'],
                        'side_effects' => [],
                        'lifecycle'    => '',
                        'perf'         => '',
                        'throws'       => [],
                    ],
                ],
            ],
            [
                'class_name' => 'response',
                'namespace'  => 'skim\\core',
                'file'       => '/src/core/response.php',
                'summary'    => 'Fluent HTTP response builder.',
                'lifecycle'  => '',
                'owner'      => '',
                'methods'    => [],
            ],
        ],
    ];
}

describe('mdx_emitter', function(): void {

    test('creates one MDX file per class', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_test_' . uniqid();
        $count = (new mdx_emitter())->emit(sample_mdx_data(), $dir);

        expect($count)->toBe(2);
        expect(file_exists($dir . '/request.mdx'))->toBeTrue();
        expect(file_exists($dir . '/response.mdx'))->toBeTrue();

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('returns count of files written', function(): void {
        $dir   = sys_get_temp_dir() . '/skim_mdx_count_' . uniqid();
        $count = (new mdx_emitter())->emit(sample_mdx_data(), $dir);
        expect($count)->toBe(2);

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('MDX file contains frontmatter title and description', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_front_' . uniqid();
        (new mdx_emitter())->emit(sample_mdx_data(), $dir);
        $content = file_get_contents($dir . '/request.mdx');

        expect($content)->toContain('title: request');
        expect($content)->toContain('HTTP request abstraction.');

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('MDX file contains method name and signature', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_method_' . uniqid();
        (new mdx_emitter())->emit(sample_mdx_data(), $dir);
        $content = file_get_contents($dir . '/request.mdx');

        expect($content)->toContain('### `get`');
        expect($content)->toContain('public function get(string $key, mixed $default): mixed');

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('MDX file contains contracts and non-goals', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_tags_' . uniqid();
        (new mdx_emitter())->emit(sample_mdx_data(), $dir);
        $content = file_get_contents($dir . '/request.mdx');

        expect($content)->toContain('returns query param by key');
        expect($content)->toContain('does not validate types');

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('creates output directory if it does not exist', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_newdir_' . uniqid() . '/nested';
        (new mdx_emitter())->emit(['generated_at' => 'now', 'classes' => []], $dir);
        expect(is_dir($dir))->toBeTrue();
        rmdir($dir);
        rmdir(dirname($dir));
    });

    test('returns 0 for empty classes array', function(): void {
        $dir   = sys_get_temp_dir() . '/skim_mdx_empty_' . uniqid();
        $count = (new mdx_emitter())->emit(['generated_at' => 'now', 'classes' => []], $dir);
        expect($count)->toBe(0);
        rmdir($dir);
    });

});
