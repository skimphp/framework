<?php declare(strict_types=1);

use Skim\Ext\ExtRegistry;

describe('ext_registry', function(): void {
    $root = '';
    $remove = null;

    beforeEach(function() use (&$root, &$remove): void {
        $root = sys_get_temp_dir() . '/skim_ext_registry_' . bin2hex(random_bytes(4));
        mkdir($root . '/vendor/skim/auth', 0777, true);
        mkdir($root . '/vendor/not-an-extension/pkg', 0777, true);

        $remove = function(string $path) use (&$remove): void {
            if (!file_exists($path)) {
                return;
            }
            if (is_file($path)) {
                unlink($path);
                return;
            }
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
                $remove($path . '/' . $child);
            }
            rmdir($path);
        };
    });

    afterEach(function() use (&$root, &$remove): void {
        $remove($root);
    });

    test('discovers packages with extra.skim.extension metadata', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'version' => '1.2.0',
            'description' => 'Authentication',
            'extra' => [
                'skim' => [
                    'extension' => 'skim\\auth\\auth_extension',
                    'type' => 'replacement',
                    'priority' => 80,
                    'conflicts' => ['auth'],
                    'env' => ['AUTH_SECRET'],
                    'post_install' => ['php skim auth:publish views'],
                ],
            ],
        ]));

        file_put_contents($root . '/vendor/not-an-extension/pkg/composer.json', json_encode([
            'name' => 'not-an-extension/pkg',
        ]));

        $extensions = (new \Skim\Ext\ExtRegistry($root))->installed();

        expect($extensions)->toHaveCount(1);
        expect($extensions[0]['name'])->toBe('skim/auth');
        expect($extensions[0]['class'])->toBe('skim\\auth\\auth_extension');
        expect($extensions[0]['type'])->toBe('replacement');
        expect($extensions[0]['priority'])->toBe(80);
        expect($extensions[0]['conflicts'])->toBe(['auth']);
        expect($extensions[0]['env'])->toBe(['AUTH_SECRET']);
    });

    test('scan result is cached until refresh is called', function() use (&$root): void {
        $registry = new \Skim\Ext\ExtRegistry($root);

        expect($registry->installed())->toBe([]);

        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => ['extension' => 'skim\\auth\\auth_extension']],
        ]));

        expect($registry->installed())->toBe([]);

        $registry->refresh();
        expect($registry->installed())->toHaveCount(1);
    });

    test('writes compiled cache after scanning vendor', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'version' => '1.0.0',
            'extra' => ['skim' => ['extension' => 'skim\\auth\\auth_extension']],
        ]));

        $cachePath = $root . '/.skim/config_cache/extensions.php';
        expect(is_file($cachePath))->toBeFalse();

        $registry = new \Skim\Ext\ExtRegistry($root);
        $registry->installed();

        expect(is_file($cachePath))->toBeTrue();
        $cached = require $cachePath;
        expect($cached)->toBeArray()->toHaveCount(1);
        expect($cached[0]['name'])->toBe('skim/auth');
    });

    test('loads from compiled cache without re-scanning vendor', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'version' => '1.0.0',
            'extra' => ['skim' => ['extension' => 'skim\\auth\\auth_extension']],
        ]));

        // First scan writes cache
        $registry = new \Skim\Ext\ExtRegistry($root);
        $first = $registry->installed();

        // Delete vendor to prove second call uses cache, not filesystem
        $remove = function(string $path) use (&$remove): void {
            if (!file_exists($path)) return;
            if (is_file($path)) { unlink($path); return; }
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $child) {
                $remove($path . '/' . $child);
            }
            rmdir($path);
        };
        $remove($root . '/vendor');

        // Fresh registry should still load from cache
        $registry2 = new \Skim\Ext\ExtRegistry($root);
        $second = $registry2->installed();

        expect($second)->toHaveCount(1);
        expect($second[0]['name'])->toBe('skim/auth');
    });

    test('writes cache regardless of project root', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => ['extension' => 'skim\\auth\\auth_extension']],
        ]));

        // Create a subdirectory that is NOT base_path
        $subRoot = $root . '/subproject';
        mkdir($subRoot . '/vendor/skim/auth', 0777, true);
        file_put_contents($subRoot . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => ['extension' => 'skim\\auth\\auth_extension']],
        ]));

        $cachePath = $subRoot . '/.skim/config_cache/extensions.php';
        $registry = new \Skim\Ext\ExtRegistry($subRoot);
        $registry->installed();

        expect(is_file($cachePath))->toBeTrue('Cache is written for any project root');
    });
});
