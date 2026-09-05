<?php declare(strict_types=1);

use Skim\Dev\Docs\Emitter\JsonEmitter;
use Skim\Dev\Docs\Value\ExtractedClass;
use Skim\Dev\Docs\Value\ExtractedMethod;

describe('JsonEmitter — emit()', function(): void {

    test('writes a valid JSON file with generated_at and classes', function(): void {
        $path   = sys_get_temp_dir() . '/skim_llm_test_' . uniqid() . '.json';
        $class  = new \Skim\Dev\Docs\Value\ExtractedClass('my_class', 'app', $path, 'Summary.');
        (new \Skim\Dev\Docs\Emitter\JsonEmitter())->emit([$class], $path);

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
        (new \Skim\Dev\Docs\Emitter\JsonEmitter())->emit([], $path);

        expect(file_exists($path))->toBeTrue();

        unlink($path);
        rmdir($dir . '/sub');
        rmdir($dir);
    });

    test('serializes method fields into the classes array', function(): void {
        $method = new \Skim\Dev\Docs\Value\ExtractedMethod(
            name:      'save',
            signature: 'public function save(): void',
            owner:     'app\\model',
            contracts: ['persists to DB'],
        );
        $class = new \Skim\Dev\Docs\Value\ExtractedClass('model', 'app', '/src/model.php', methods: [$method]);
        $path  = sys_get_temp_dir() . '/skim_llm_methods_' . uniqid() . '.json';
        (new \Skim\Dev\Docs\Emitter\JsonEmitter())->emit([$class], $path);

        $data    = json_decode(file_get_contents($path), true);
        $methods = $data['classes'][0]['methods'];
        expect($methods)->toHaveCount(1);
        expect($methods[0]['name'])->toBe('save');
        expect($methods[0]['contracts'])->toBe(['persists to DB']);

        unlink($path);
    });

    test('serializes new ExtractedClass fields into classes array', function (): void {
        $cls = new \Skim\Dev\Docs\Value\ExtractedClass(
            className:   'Cache',
            namespace:    'Skim\\Cache',
            file:         '/src/Cache/Cache.php',
            layer:        'cache',
            entryPoints: ['remember', 'get'],
            invariants:   ['driver reused until reset'],
        );
        $path = sys_get_temp_dir() . '/skim_llm_new_fields_' . uniqid() . '.json';
        (new \Skim\Dev\Docs\Emitter\JsonEmitter())->emit([$cls], $path);
        $data = json_decode(file_get_contents($path), true);
        $row  = $data['classes'][0];
        expect($row['layer'])->toBe('cache')
            ->and($row['entry_points'])->toContain('remember')
            ->and($row['invariants'])->toContain('driver reused until reset');
        unlink($path);
    });

    test('serializes new ExtractedMethod fields into methods array', function (): void {
        $method = new \Skim\Dev\Docs\Value\ExtractedMethod(
            name:      'remember',
            signature: 'public static function remember(): mixed',
            owner:     'Skim\\Cache\\Cache',
            group:     'Read API',
            calls:     ['has', 'get', 'set'],
            warnings:  ['may compute expensive callback'],
        );
        $cls = new \Skim\Dev\Docs\Value\ExtractedClass('Cache', 'Skim\\Cache', '/src/Cache/Cache.php', methods: [$method]);
        $path = sys_get_temp_dir() . '/skim_llm_new_method_fields_' . uniqid() . '.json';
        (new \Skim\Dev\Docs\Emitter\JsonEmitter())->emit([$cls], $path);
        $data = json_decode(file_get_contents($path), true);
        $m    = $data['classes'][0]['methods'][0];
        expect($m['group'])->toBe('Read API')
            ->and($m['calls'])->toContain('has')
            ->and($m['warnings'][0])->toContain('may compute expensive callback');
        unlink($path);
    });

});

describe('JsonEmitter — load()', function(): void {

    test('loads and decodes an existing llm.json', function(): void {
        $path = sys_get_temp_dir() . '/skim_load_test_' . uniqid() . '.json';
        file_put_contents($path, json_encode(['generated_at' => 'now', 'classes' => []]));
        $data = (new \Skim\Dev\Docs\Emitter\JsonEmitter())->load($path);
        expect($data['classes'])->toBe([]);
        unlink($path);
    });

    test('throws RuntimeException when file is missing', function(): void {
        expect(fn() => (new \Skim\Dev\Docs\Emitter\JsonEmitter())->load('/tmp/does_not_exist_' . uniqid() . '.json'))
            ->toThrow(\RuntimeException::class);
    });

    test('throws RuntimeException when JSON is malformed', function(): void {
        $path = sys_get_temp_dir() . '/skim_bad_json_' . uniqid() . '.json';
        file_put_contents($path, 'not json at all {{');
        expect(fn() => (new \Skim\Dev\Docs\Emitter\JsonEmitter())->load($path))->toThrow(\RuntimeException::class);
        unlink($path);
    });

});
