<?php declare(strict_types=1);

use skim\dev\docs\extractor\class_extractor;

describe('class_extractor — basic behavior', function(): void {

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

// ---------------------------------------------------------------------------
// class_extractor — end-to-end fixture tests
// ---------------------------------------------------------------------------

function find_method(array $methods, string $name) {
    foreach ($methods as $m) {
        if ($m->name === $name) {
            return $m;
        }
    }
    return null;
}

describe('class_extractor — fixture: array_driver', function () {

    beforeEach(function () {
        $this->extractor = new class_extractor();
        $this->fixture   = __DIR__ . '/../../src/cache/array_driver.php';
    });

    it('returns null for non-existent file', function () {
        $result = $this->extractor->extract('/no/such/file.php');
        expect($result)->toBeNull();
    });

    it('returns null for file with no class', function () {
        $tmp = tempnam(sys_get_temp_dir(), 'skim_test_');
        file_put_contents($tmp, '<?php $x = 1;');
        expect($this->extractor->extract($tmp))->toBeNull();
        unlink($tmp);
    });

    it('extracts class_name and namespace', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->class_name)->toBe('array_driver')
            ->and($result->namespace)->toBe('skim\\cache');
    });

    it('extracts summary from inline comments before class', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->summary)->toContain('In-memory array driver');
    });

    it('extracts public methods only', function () {
        $result  = $this->extractor->extract($this->fixture);
        $names   = array_map(fn($m) => $m->name, $result->methods);
        expect($names)->toContain('get')
            ->and($names)->toContain('set')
            ->and($names)->toContain('has')
            ->and($names)->toContain('delete')
            ->and($names)->toContain('flush');
    });

    it('builds correct method signature', function () {
        $result = $this->extractor->extract($this->fixture);
        $get    = find_method($result->methods, 'get');
        expect($get->signature)->toContain('public function get(')
            ->and($get->signature)->toContain('string $key')
            ->and($get->signature)->toContain('mixed $default')
            ->and($get->signature)->toContain(': mixed');
    });

    it('sets method owner to fully qualified class name', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->methods[0]->owner)->toBe('skim\\cache\\array_driver');
    });

});

describe('class_extractor — fixture: cache facade (full #AI block)', function () {

    beforeEach(function () {
        $this->extractor = new class_extractor();
        $this->fixture   = __DIR__ . '/../../src/cache/cache.php';
    });

    it('extracts class-level #AI layer field', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->layer)->toBe('cache');
    });

    it('extracts class-level entry_points as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->entry_points)->toContain('remember')
            ->and($result->entry_points)->toContain('get')
            ->and($result->entry_points)->toContain('set');
    });

    it('extracts class-level config_reads as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->config_reads)->toContain('cache.driver')
            ->and($result->config_reads)->toContain('cache.ttl');
    });

    it('extracts class-level invariants as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->invariants)->not->toBeEmpty()
            ->and(implode(' ', $result->invariants))->toContain('reused until reset');
    });

    it('extracts class-level non_goals as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->non_goals)->not->toBeEmpty();
    });

    it('extracts remember() method contracts from #AI lines in docblock', function () {
        $result   = $this->extractor->extract($this->fixture);
        $remember = find_method($result->methods, 'remember');
        expect($remember->contracts)->not->toBeEmpty();
        expect(implode(' ', $remember->contracts))->toContain('stores the returned value');
    });

    it('extracts remember() param_details field', function () {
        $result   = $this->extractor->extract($this->fixture);
        $remember = find_method($result->methods, 'remember');
        expect($remember->param_details)->not->toBeEmpty();
    });

    it('extracts flush() warning field', function () {
        $result = $this->extractor->extract($this->fixture);
        $flush  = find_method($result->methods, 'flush');
        expect($flush->warnings)->not->toBeEmpty()
            ->and($flush->warnings[0])->toContain('Prefix is required');
    });

    it('extracts flush_all() warning field', function () {
        $result    = $this->extractor->extract($this->fixture);
        $flush_all = find_method($result->methods, 'flush_all');
        expect($flush_all->warnings)->not->toBeEmpty();
    });

    it('extracts tags() throws_details field', function () {
        $result = $this->extractor->extract($this->fixture);
        $tags   = find_method($result->methods, 'tags');
        expect($tags->throws_details)->not->toBeEmpty();
    });

    it('extracts set() param_details field', function () {
        $result = $this->extractor->extract($this->fixture);
        $set    = find_method($result->methods, 'set');
        expect($set->param_details)->not->toBeEmpty();
    });

    it('private methods are included when documented in detached #AI block', function () {
        $result = $this->extractor->extract($this->fixture);
        $names  = array_map(fn($m) => $m->name, $result->methods);
        // Private architecture methods documented in the detached #AI block ARE included
        expect($names)->toContain('make_driver')
            ->and($names)->toContain('resolve_driver');
    });

});
