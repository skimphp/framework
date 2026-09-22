<?php declare(strict_types=1);

use Skim\Ext\ExtRegistry;

describe('ExtRegistry', function(): void {
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

    test('capability map resolves providers and answers dynamic lookups', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => [
                'extension'    => 'Acme\\Auth',
                'provides'     => ['auth'],
                'capabilities' => ['session'],
            ]],
        ]));

        $registry = new \Skim\Ext\ExtRegistry($root);

        expect($registry->capabilityMap())->toBe(['auth' => 'skim/auth', 'session' => 'skim/auth']);
        expect($registry->has_capability('auth'))->toBeTrue();
        expect($registry->has_capability('nope'))->toBeFalse();
        expect($registry->who_provides('auth'))->toBe('skim/auth');
        expect($registry->who_provides('nope'))->toBeNull();
        expect($registry->conflicts())->toBe([]);
    });

    test('duplicate capability provider is recorded as conflict', function() use (&$root): void {
        mkdir($root . '/vendor/skim/auth2', 0777, true);
        foreach (['skim/auth', 'skim/auth2'] as $pkg) {
            file_put_contents($root . '/vendor/' . $pkg . '/composer.json', json_encode([
                'name' => $pkg,
                'extra' => ['skim' => [
                    'extension' => 'Acme\\' . basename($pkg),
                    'provides'  => ['auth'],
                ]],
            ]));
        }

        $registry = new \Skim\Ext\ExtRegistry($root);
        $conflicts = $registry->conflicts();

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]['capabilities'])->toBe(['auth']);
        expect($conflicts[0]['extensions'])->toBe(['skim/auth', 'skim/auth2']);
    });

    test('declared conflict against installed provider is recorded', function() use (&$root): void {
        mkdir($root . '/vendor/acme/replacer', 0777, true);
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => [
                'extension' => 'Acme\\Auth',
                'provides'  => ['auth'],
            ]],
        ]));
        file_put_contents($root . '/vendor/acme/replacer/composer.json', json_encode([
            'name' => 'acme/replacer',
            'extra' => ['skim' => [
                'extension' => 'Acme\\Replacer',
                'conflicts' => ['auth'],
            ]],
        ]));

        $conflicts = (new \Skim\Ext\ExtRegistry($root))->conflicts();

        expect($conflicts)->toHaveCount(1);
        expect($conflicts[0]['message'])->toContain('acme/replacer conflicts with skim/auth');
    });

    test('find returns metadata for installed package and null otherwise', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => ['extension' => 'Acme\\Auth']],
        ]));

        $registry = new \Skim\Ext\ExtRegistry($root);

        expect($registry->find('skim/auth')['name'])->toBe('skim/auth');
        expect($registry->find('acme/none'))->toBeNull();
    });

    test('unknown dynamic method throws bad method call', function() use (&$root): void {
        (new \Skim\Ext\ExtRegistry($root))->nonsense();
    })->throws(\BadMethodCallException::class);

    test('static dispatch answers lookups against the given root', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => [
                'extension' => 'Acme\\Auth',
                'provides'  => ['auth'],
            ]],
        ]));

        expect(\Skim\Ext\ExtRegistry::has_capability('auth', $root))->toBeTrue();
        expect(\Skim\Ext\ExtRegistry::who_provides('auth', $root))->toBe('skim/auth');
        expect(\Skim\Ext\ExtRegistry::conflicts(null, $root))->toBe([]);
    });

    test('skim.json manifest takes precedence over composer extra', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => [
                'extension' => 'Acme\\Auth',
                'provides'  => ['from-extra'],
            ]],
        ]));
        file_put_contents($root . '/vendor/skim/auth/skim.json', json_encode([
            'name'     => 'skim/auth',
            'requires' => ['dep-one'],
            'provides' => ['from-manifest'],
        ]));

        $meta = (new \Skim\Ext\ExtRegistry($root))->installed()[0];

        expect($meta['provides'])->toBe(['from-manifest']);
        expect($meta['requires'])->toBe(['dep-one']);
    });

    test('autoloadable extension class provides manifest from instance', function() use (&$root): void {
        file_put_contents($root . '/vendor/skim/auth/composer.json', json_encode([
            'name' => 'skim/auth',
            'extra' => ['skim' => ['extension' => RegistryFixtureExtension::class]],
        ]));

        $meta = (new \Skim\Ext\ExtRegistry($root))->installed()[0];

        expect($meta['provides'])->toBe(['fixture-cap']);
        expect($meta['version'])->toBe('9.9.9');
    });
});

class RegistryFixtureExtension extends \Skim\Ext\Extension {
    public string $version = '9.9.9';
    public array $provides = ['fixture-cap'];

    public function register(\Skim\Core\App $app): void {}
    public function boot(\Skim\Core\App $app): void {}
}
