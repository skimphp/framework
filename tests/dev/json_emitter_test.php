<?php declare(strict_types=1);

use skim\dev\docs\emitter\json_emitter;
use skim\dev\docs\value\extracted_class;
use skim\dev\docs\value\extracted_method;

describe('json_emitter — emit()', function(): void {

    test('writes a valid JSON file with generated_at and classes', function(): void {
        $path   = sys_get_temp_dir() . '/skim_llm_test_' . uniqid() . '.json';
        $class  = new extracted_class('my_class', 'app', $path, 'Summary.');
        (new json_emitter())->emit([$class], $path);

        expect(file_exists($path))->toBeTrue();
        $data = json_decode(file_get_contents($path), true);
        expect($data)->toHaveKey('generated_at');
        expect($data['classes'])->toHaveCount(1);
        expect($data['classes'][0]['class_name'])->toBe('my_class');

        unlink($path);
    });

    test('creates parent directory if it does not exist', function(): void {
        $dir  = sys_get_temp_dir() . '/skim_emitter_dir_' . uniqid();
        $path = $dir . '/sub/llm.json';
        (new json_emitter())->emit([], $path);

        expect(file_exists($path))->toBeTrue();

        unlink($path);
        rmdir($dir . '/sub');
        rmdir($dir);
    });

    test('serializes method fields into the classes array', function(): void {
        $method = new extracted_method(
            name:      'save',
            signature: 'public function save(): void',
            owner:     'app\\model',
            contracts: ['persists to DB'],
        );
        $class = new extracted_class('model', 'app', '/src/model.php', methods: [$method]);
        $path  = sys_get_temp_dir() . '/skim_llm_methods_' . uniqid() . '.json';
        (new json_emitter())->emit([$class], $path);

        $data    = json_decode(file_get_contents($path), true);
        $methods = $data['classes'][0]['methods'];
        expect($methods)->toHaveCount(1);
        expect($methods[0]['name'])->toBe('save');
        expect($methods[0]['contracts'])->toBe(['persists to DB']);

        unlink($path);
    });

});

describe('json_emitter — load()', function(): void {

    test('loads and decodes an existing llm.json', function(): void {
        $path = sys_get_temp_dir() . '/skim_load_test_' . uniqid() . '.json';
        file_put_contents($path, json_encode(['generated_at' => 'now', 'classes' => []]));
        $data = (new json_emitter())->load($path);
        expect($data['classes'])->toBe([]);
        unlink($path);
    });

    test('throws RuntimeException when file is missing', function(): void {
        expect(fn() => (new json_emitter())->load('/tmp/does_not_exist_' . uniqid() . '.json'))
            ->toThrow(\RuntimeException::class);
    });

    test('throws RuntimeException when JSON is malformed', function(): void {
        $path = sys_get_temp_dir() . '/skim_bad_json_' . uniqid() . '.json';
        file_put_contents($path, 'not json at all {{');
        expect(fn() => (new json_emitter())->load($path))->toThrow(\RuntimeException::class);
        unlink($path);
    });

});
