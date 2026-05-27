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

    test('preserves duplicate class names with source file suffixes', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_duplicate_' . uniqid();
        $data = sample_mdx_data();
        $data['classes'][1]['class_name'] = 'request';
        $data['classes'][1]['file'] = '/src/core/response.php';

        $count = (new mdx_emitter())->emit($data, $dir);

        expect($count)->toBe(2);
        expect(file_exists($dir . '/request-request.mdx'))->toBeTrue();
        expect(file_exists($dir . '/request-response.mdx'))->toBeTrue();

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('preserves duplicate class names with duplicate source basenames', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_duplicate_basename_' . uniqid();
        $data = sample_mdx_data();
        $data['classes'][1]['class_name'] = 'request';
        $data['classes'][1]['file'] = '/other/core/request.php';

        $count = (new mdx_emitter())->emit($data, $dir);

        expect($count)->toBe(2);
        expect(file_exists($dir . '/request-request.mdx'))->toBeTrue();
        expect(file_exists($dir . '/request-request-2.mdx'))->toBeTrue();

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

        expect($content)->toContain('<ApiMethod name="get">')
            ->and($content)->toContain('public function get(string $key, mixed $default): mixed');

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

    test('MDX file contains contracts and non-goals', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_tags_' . uniqid();
        (new mdx_emitter())->emit(sample_mdx_data(), $dir);
        $content = file_get_contents($dir . '/request.mdx');

        expect($content)->toContain('returns query param by key');

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

    test('MDX file escapes HTML-like tags and braces outside backticks', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_escape_' . uniqid();
        $data = [
            'generated_at' => '2026-01-01T00:00:00+00:00',
            'classes'      => [
                [
                    'class_name' => 'escaper',
                    'namespace'  => 'skim\\core',
                    'file'       => '/src/core/escaper.php',
                    'summary'    => 'Handles <tags> and {braces} properly, but `keeps <tag> inside backticks`.',
                    'lifecycle'  => '',
                    'owner'      => '',
                    'methods'    => [
                        [
                            'name'         => 'run',
                            'signature'    => 'public function run(): void',
                            'owner'        => 'skim\\core\\escaper',
                            'contracts'    => ['processes <input> and {values} in description, but `ignores <tag>`'],
                            'invariants'   => [],
                            'non_goals'    => [],
                            'side_effects' => [],
                            'lifecycle'    => '',
                            'perf'         => '',
                            'throws'       => [],
                        ],
                    ],
                ],
            ],
        ];

        (new mdx_emitter())->emit($data, $dir);
        $content = file_get_contents($dir . '/escaper.mdx');

        expect($content)->toContain('Handles &lt;tags&gt; and &#123;braces&#125; properly, but `keeps <tag> inside backticks`.');
        expect($content)->toContain('processes &lt;input&gt; and &#123;values&#125; in description, but `ignores <tag>`');

        array_map('unlink', glob($dir . '/*.mdx'));
        rmdir($dir);
    });

});
