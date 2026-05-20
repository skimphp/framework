<?php declare(strict_types=1);

use skim\dev\docs\extractor\class_extractor;

describe('class_extractor', function(): void {

    function write_php_fixture(string $code): string {
        $path = sys_get_temp_dir() . '/skim_extractor_test_' . uniqid() . '.php';
        file_put_contents($path, "<?php declare(strict_types=1);\n\n" . $code);
        return $path;
    }

    test('extracts class name, namespace and summary from docblock', function(): void {
        $file = write_php_fixture(<<<'PHP'
        namespace app\services;

        /** Handles user authentication. */
        class auth_service {
            public function login(): bool { return true; }
        }
        PHP);

        $result = (new class_extractor())->extract($file);
        unlink($file);

        expect($result)->not->toBeNull();
        expect($result->class_name)->toBe('auth_service');
        expect($result->namespace)->toBe('app\services');
        expect($result->summary)->toBe('Handles user authentication.');
    });

    test('extracts only public methods', function(): void {
        $file = write_php_fixture(<<<'PHP'
        namespace app;

        class my_class {
            public function pub(): void {}
            protected function prot(): void {}
            private function priv(): void {}
        }
        PHP);

        $result = (new class_extractor())->extract($file);
        unlink($file);

        expect($result->methods)->toHaveCount(1);
        expect($result->methods[0]->name)->toBe('pub');
    });

    test('extracts @ai.* tags from method docblocks', function(): void {
        $file = write_php_fixture(<<<'PHP'
        namespace app;

        class my_service {
            /**
             * @ai.contract returns null when not found
             * @ai.non_goal does not cache
             */
            public function find(int $id): ?object { return null; }
        }
        PHP);

        $result = (new class_extractor())->extract($file);
        unlink($file);

        expect($result->methods)->toHaveCount(1);
        $method = $result->methods[0];
        expect($method->contracts)->toBe(['returns null when not found']);
        expect($method->non_goals)->toBe(['does not cache']);
    });

    test('builds correct method signature string', function(): void {
        $file = write_php_fixture(<<<'PHP'
        namespace app;

        class widget {
            public function process(string $input, int $count): bool { return true; }
        }
        PHP);

        $result = (new class_extractor())->extract($file);
        unlink($file);

        expect($result->methods[0]->signature)
            ->toContain('process')
            ->toContain('string $input')
            ->toContain('int $count')
            ->toContain(': bool');
    });

    test('returns null for file with no class', function(): void {
        $file = write_php_fixture('function helper(): void {}');
        $result = (new class_extractor())->extract($file);
        unlink($file);
        expect($result)->toBeNull();
    });

    test('returns null for file with parse error', function(): void {
        $path = sys_get_temp_dir() . '/skim_extractor_broken_' . uniqid() . '.php';
        file_put_contents($path, '<?php this is not valid php {{{{');
        $result = (new class_extractor())->extract($path);
        unlink($path);
        expect($result)->toBeNull();
    });

    test('returns null for non-existent file', function(): void {
        $path = sys_get_temp_dir() . '/skim_never_created_' . uniqid() . '.php';
        expect(file_exists($path))->toBeFalse();
        $result = (new class_extractor())->extract($path);
        expect($result)->toBeNull();
    });

    test('skips anonymous classes', function(): void {
        $file = write_php_fixture(<<<'PHP'
        namespace app;

        $obj = new class {
            public function run(): void {}
        };
        PHP);

        $result = (new class_extractor())->extract($file);
        unlink($file);
        expect($result)->toBeNull();
    });

});
