<?php declare(strict_types=1);

use Skim\Dev\Docs\Emitter\MdxEmitter;

function sampleMdxData(): array {
    return [
        'generated_at' => '2026-01-01T00:00:00+00:00',
        'classes'      => [
            [
                'class_name' => 'Request',
                'namespace'  => 'Skim\\Core',
                'file'       => '/src/Core/Request.php',
                'summary'    => 'HTTP request abstraction.',
                'lifecycle'  => 'per-request',
                'owner'      => 'platform',
                'methods'    => [
                    [
                        'name'         => 'get',
                        'signature'    => 'public function get(string $key, mixed $default): mixed',
                        'owner'        => 'Skim\\Core\\Request',
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
                'class_name' => 'Response',
                'namespace'  => 'Skim\\Core',
                'file'       => '/src/Core/Response.php',
                'summary'    => 'Fluent HTTP response builder.',
                'lifecycle'  => '',
                'owner'      => '',
                'methods'    => [],
            ],
        ],
    ];
}

describe('MdxEmitter', function(): void {

    test('creates one MDX file per class', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_test_' . uniqid();
        $count = (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(sampleMdxData(), $dir);

        expect($count)->toBe(3);
        expect(file_exists($dir . '/core/request.mdx'))->toBeTrue();
        expect(file_exists($dir . '/core/response.mdx'))->toBeTrue();
        expect(file_exists($dir . '/index.mdx'))->toBeTrue();

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('returns count of files written', function(): void {
        $dir   = sys_get_temp_dir() . '/skim_mdx_count_' . uniqid();
        $count = (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(sampleMdxData(), $dir);
        expect($count)->toBe(3);

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('preserves duplicate class names with source file suffixes', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_duplicate_' . uniqid();
        $data = sampleMdxData();
        $data['classes'][1]['class_name'] = 'Request';
        $data['classes'][1]['file'] = '/src/Core/Response.php';

        $count = (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit($data, $dir);

        expect($count)->toBe(3);
        expect(file_exists($dir . '/core/request-request.mdx'))->toBeTrue();
        expect(file_exists($dir . '/core/request-response.mdx'))->toBeTrue();

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('preserves duplicate class names with duplicate source basenames', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_duplicate_basename_' . uniqid();
        $data = sampleMdxData();
        $data['classes'][1]['class_name'] = 'Request';
        $data['classes'][1]['file'] = '/other/Core/Request.php';

        $count = (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit($data, $dir);

        expect($count)->toBe(3);
        expect(file_exists($dir . '/core/request-request.mdx'))->toBeTrue();
        expect(file_exists($dir . '/core/request-request-2.mdx'))->toBeTrue();

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('MDX file contains frontmatter title and description', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_front_' . uniqid();
        (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(sampleMdxData(), $dir);
        $content = file_get_contents($dir . '/core/request.mdx');

        expect($content)->toContain('title: Request');
        expect($content)->toContain('HTTP request abstraction.');

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('MDX file contains method name and signature', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_method_' . uniqid();
        (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(sampleMdxData(), $dir);
        $content = file_get_contents($dir . '/core/request.mdx');

        expect($content)->toContain('<ApiMethod name="get">')
            ->and($content)->toContain('public function get(string $key, mixed $default): mixed');

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('MDX file contains contracts and non-goals', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_tags_' . uniqid();
        (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(sampleMdxData(), $dir);
        $content = file_get_contents($dir . '/core/request.mdx');

        expect($content)->toContain('returns query param by key');

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('creates output directory if it does not exist', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_newdir_' . uniqid() . '/nested';
        (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(['generated_at' => 'now', 'classes' => []], $dir);
        expect(is_dir($dir))->toBeTrue();
        rmdir($dir);
        rmdir(dirname($dir));
    });

    test('returns 0 for empty classes array', function(): void {
        $dir   = sys_get_temp_dir() . '/skim_mdx_empty_' . uniqid();
        $count = (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit(['generated_at' => 'now', 'classes' => []], $dir);
        expect($count)->toBe(0);
        rmdir($dir);
    });

    test('MDX file escapes HTML-like tags and braces outside backticks', function(): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_escape_' . uniqid();
        $data = [
            'generated_at' => '2026-01-01T00:00:00+00:00',
            'classes'      => [
                [
                    'class_name' => 'Escaper',
                    'namespace'  => 'Skim\\Core',
                    'file'       => '/src/Core/Escaper.php',
                    'summary'    => 'Handles <tags> and {braces} properly, but `keeps <tag> inside backticks`.',
                    'lifecycle'  => '',
                    'owner'      => '',
                    'methods'    => [
                        [
                            'name'         => 'run',
                            'signature'    => 'public function run(): void',
                            'owner'        => 'Skim\\Core\\Escaper',
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

        (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit($data, $dir);
        $content = file_get_contents($dir . '/core/escaper.mdx');

        expect($content)->toContain('Handles &lt;tags&gt; and &#123;braces&#125; properly, but `keeps <tag> inside backticks`.');
        expect($content)->toContain('processes &lt;input&gt; and &#123;values&#125; in description, but `ignores <tag>`');

        array_map('unlink', glob($dir . '/core/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/core');
        rmdir($dir);
    });

    test('frontmatter description escapes backslashes, quotes and colons for YAML', function (): void {
        $dir = sys_get_temp_dir() . '/skim_mdx_yaml_' . uniqid();
        $data = [
            'generated_at' => '2026-01-01T00:00:00+00:00',
            'classes'      => [
                [
                    'class_name'  => 'ErrorPage',
                    'namespace'   => 'Skim\\Dev',
                    'file'        => '/src/Dev/ErrorPage.php',
                    'summary'     => 'Renders fn(\\Throwable $e) when "debug": on',
                    'description' => "Handles \\Throwable, Skim\\Dev\\ErrorPage refs, colons: ok, \"quotes\" kept",
                    'lifecycle'   => '',
                    'owner'       => '',
                    'methods'     => [],
                ],
            ],
        ];

        (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit($data, $dir);
        $content = file_get_contents($dir . '/dev/errorpage.mdx');

        expect($content)->toContain('description: "Handles \\\\Throwable, Skim\\\\Dev\\\\ErrorPage refs, colons: ok, \\"quotes\\" kept"');

        array_map('unlink', glob($dir . '/dev/*.mdx'));
        unlink($dir . '/index.mdx');
        rmdir($dir . '/dev');
        rmdir($dir);
    });

});
