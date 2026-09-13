<?php declare(strict_types=1);

use Skim\Dev\Docs\Extractor\ClassExtractor;

describe('ClassExtractor — basic behavior', function(): void {

    function writePhpFixture(string $code): string {
        $path = sys_get_temp_dir() . '/skim_extractor_test_' . uniqid() . '.php';
        file_put_contents($path, "<?php declare(strict_types=1);\n\n" . $code);
        return $path;
    }

    test('extracts class name, namespace and summary from docblock', function(): void {
        $file = writePhpFixture(<<<'PHP'
        namespace app\services;

        /** Handles user authentication. */
        class auth_service {
            public function login(): bool { return true; }
        }
        PHP);

        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($file);
        unlink($file);

        expect($result)->not->toBeNull();
        expect($result->className)->toBe('auth_service');
        expect($result->namespace)->toBe('app\services');
        expect($result->summary)->toBe('Handles user authentication.');
    });

    test('extracts only public methods', function(): void {
        $file = writePhpFixture(<<<'PHP'
        namespace app;

        class my_class {
            public function pub(): void {}
            protected function prot(): void {}
            private function priv(): void {}
        }
        PHP);

        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($file);
        unlink($file);

        expect($result->methods)->toHaveCount(1);
        expect($result->methods[0]->name)->toBe('pub');
    });

    test('extracts @ai.* tags from method docblocks', function(): void {
        $file = writePhpFixture(<<<'PHP'
        namespace app;

        class my_service {
            /**
             * @ai.contract returns null when not found
             * @ai.non_goal does not cache
             */
            public function find(int $id): ?object { return null; }
        }
        PHP);

        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($file);
        unlink($file);

        expect($result->methods)->toHaveCount(1);
        $method = $result->methods[0];
        expect($method->contracts)->toBe(['returns null when not found']);
        expect($method->nonGoals)->toBe(['does not cache']);
    });

    test('builds correct method signature string', function(): void {
        $file = writePhpFixture(<<<'PHP'
        namespace app;

        class widget {
            public function process(string $input, int $count): bool { return true; }
        }
        PHP);

        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($file);
        unlink($file);

        expect($result->methods[0]->signature)
            ->toContain('process')
            ->toContain('string $input')
            ->toContain('int $count')
            ->toContain(': bool');
    });

    test('returns null for file with no class', function(): void {
        $file = writePhpFixture('function helper(): void {}');
        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($file);
        unlink($file);
        expect($result)->toBeNull();
    });

    test('returns null for file with parse error', function(): void {
        $path = sys_get_temp_dir() . '/skim_extractor_broken_' . uniqid() . '.php';
        file_put_contents($path, '<?php this is not valid php {{{{');
        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($path);
        unlink($path);
        expect($result)->toBeNull();
    });

    test('returns null for non-existent file', function(): void {
        $path = sys_get_temp_dir() . '/skim_never_created_' . uniqid() . '.php';
        expect(file_exists($path))->toBeFalse();
        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($path);
        expect($result)->toBeNull();
    });

    test('skips anonymous classes', function(): void {
        $file = writePhpFixture(<<<'PHP'
        namespace app;

        $obj = new class {
            public function run(): void {}
        };
        PHP);

        $result = (new \Skim\Dev\Docs\Extractor\ClassExtractor())->extract($file);
        unlink($file);
        expect($result)->toBeNull();
    });

});

// ---------------------------------------------------------------------------
// class_extractor — end-to-end fixture tests
// ---------------------------------------------------------------------------

function findMethod(array $methods, string $name) {
    foreach ($methods as $m) {
        if ($m->name === $name) {
            return $m;
        }
    }
    return null;
}

describe('ClassExtractor — fixture: ArrayDriver', function () {

    beforeEach(function () {
        $this->extractor = new \Skim\Dev\Docs\Extractor\ClassExtractor();
        $this->fixture   = __DIR__ . '/../../src/Cache/ArrayDriver.php';
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

    it('extracts className and namespace', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->className)->toBe('ArrayDriver')
            ->and($result->namespace)->toBe('Skim\\Cache');
    });

    it('extracts summary from inline comments before class', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->summary)->toContain('In-memory cache driver for tests');
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
        $get    = findMethod($result->methods, 'get');
        expect($get->signature)->toContain('public function get(')
            ->and($get->signature)->toContain('string $key')
            ->and($get->signature)->toContain('mixed $default')
            ->and($get->signature)->toContain(': mixed');
    });

    it('sets method owner to fully qualified class name', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->methods[0]->owner)->toBe('Skim\\Cache\\ArrayDriver');
    });

});

describe('ClassExtractor — fixture: cache facade (full #AI block)', function () {

    beforeEach(function () {
        $this->extractor = new \Skim\Dev\Docs\Extractor\ClassExtractor();
        $this->fixture   = __DIR__ . '/../../src/Cache/Cache.php';
    });

    it('extracts class-level #AI layer field', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->layer)->toBe('cache');
    });

    it('extracts class-level entry_points as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->entryPoints)->toContain('remember')
            ->and($result->entryPoints)->toContain('get')
            ->and($result->entryPoints)->toContain('set');
    });

    it('extracts class-level config_reads as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->configReads)->toContain('cache.driver')
            ->and($result->configReads)->toContain('cache.ttl');
    });

    it('extracts class-level invariants as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->invariants)->not->toBeEmpty()
            ->and(implode(' ', $result->invariants))->toContain('reused until reset');
    });

    it('extracts class-level non_goals as array', function () {
        $result = $this->extractor->extract($this->fixture);
        expect($result->nonGoals)->not->toBeEmpty();
    });

    it('extracts remember() method contracts from #AI lines in docblock', function () {
        $result   = $this->extractor->extract($this->fixture);
        $remember = findMethod($result->methods, 'remember');
        expect($remember->contracts)->not->toBeEmpty();
        expect(implode(' ', $remember->contracts))->toContain('stores the returned value');
    });

    it('extracts remember() param_details field', function () {
        $result   = $this->extractor->extract($this->fixture);
        $remember = findMethod($result->methods, 'remember');
        expect($remember->paramDetails)->not->toBeEmpty();
    });

    it('extracts flush() warning field', function () {
        $result = $this->extractor->extract($this->fixture);
        $flush  = findMethod($result->methods, 'flush');
        expect($flush->warnings)->not->toBeEmpty()
            ->and($flush->warnings[0])->toContain('Prefix is required');
    });

    it('extracts flushAll() warning field', function () {
        $result    = $this->extractor->extract($this->fixture);
        $flushAll = findMethod($result->methods, 'flushAll');
        expect($flushAll->warnings)->not->toBeEmpty();
    });

    it('extracts tags() throws_details field', function () {
        $result = $this->extractor->extract($this->fixture);
        $tags   = findMethod($result->methods, 'tags');
        expect($tags->throwsDetails)->not->toBeEmpty();
    });

    it('extracts set() param_details field', function () {
        $result = $this->extractor->extract($this->fixture);
        $set    = findMethod($result->methods, 'set');
        expect($set->paramDetails)->not->toBeEmpty();
    });

    it('private methods are included when documented in detached #AI block', function () {
        $result = $this->extractor->extract($this->fixture);
        $names  = array_map(fn($m) => $m->name, $result->methods);
        // Private architecture methods documented in the detached #AI block ARE included
        expect($names)->toContain('makeDriver')
            ->and($names)->toContain('resolveDriver');
    });

});
