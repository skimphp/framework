<?php declare(strict_types=1);

use Skim\Core\Config;

describe('Config::get()', function(): void {

    test('returns default when key not set', function(): void {
        expect(\Skim\Core\Config::get('nonexistent.key', 'fallback'))->toBe('fallback');
    });

    test('returns null default when not specified', function(): void {
        expect(\Skim\Core\Config::get('nonexistent'))->toBeNull();
    });

    test('returns top-level key value', function(): void {
        \Skim\Core\Config::set('app.name', 'SKIM Test');
        expect(\Skim\Core\Config::get('app.name'))->toBe('SKIM Test');
    });

    test('dot-notation traverses nested arrays', function(): void {
        \Skim\Core\Config::set('db.default.host', '127.0.0.1');
        expect(\Skim\Core\Config::get('db.default.host'))->toBe('127.0.0.1');
    });

    test('returns null when intermediate segment is missing', function(): void {
        \Skim\Core\Config::set('cache.driver', 'redis');
        expect(\Skim\Core\Config::get('cache.missing.deeply.nested'))->toBeNull();
    });

    test('returns full subtree when key points to array', function(): void {
        \Skim\Core\Config::set('app.session.driver', 'redis');
        \Skim\Core\Config::set('app.session.lifetime', 3600);
        $session = \Skim\Core\Config::get('app.session');
        expect($session)->toBeArray()
            ->toHaveKey('driver')
            ->toHaveKey('lifetime');
    });

});

describe('Config::load()', function(): void {

    test('loads php config files from directory', function(): void {
        $dir = sys_get_temp_dir() . '/skim_cfg_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/app.php', '<?php return ["name" => "LoadTest"];');

        \Skim\Core\Config::reset();
        \Skim\Core\Config::load($dir);

        expect(\Skim\Core\Config::get('app.name'))->toBe('LoadTest');

        unlink($dir . '/app.php');
        rmdir($dir);
    });

    test('is idempotent — second load() call is a no-op', function(): void {
        $dir = sys_get_temp_dir() . '/skim_cfg_idem_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/app.php', '<?php return ["env" => "test"];');

        \Skim\Core\Config::reset();
        \Skim\Core\Config::load($dir);
        \Skim\Core\Config::load($dir);   // should not reload

        expect(\Skim\Core\Config::get('app.env'))->toBe('test');

        unlink($dir . '/app.php');
        rmdir($dir);
    });

});
