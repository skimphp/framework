<?php declare(strict_types=1);

use skim\core\config;

describe('config::get()', function(): void {

    test('returns default when key not set', function(): void {
        expect(config::get('nonexistent.key', 'fallback'))->toBe('fallback');
    });

    test('returns null default when not specified', function(): void {
        expect(config::get('nonexistent'))->toBeNull();
    });

    test('returns top-level key value', function(): void {
        config::set('app.name', 'SKIM Test');
        expect(config::get('app.name'))->toBe('SKIM Test');
    });

    test('dot-notation traverses nested arrays', function(): void {
        config::set('db.default.host', '127.0.0.1');
        expect(config::get('db.default.host'))->toBe('127.0.0.1');
    });

    test('returns null when intermediate segment is missing', function(): void {
        config::set('cache.driver', 'redis');
        expect(config::get('cache.missing.deeply.nested'))->toBeNull();
    });

    test('returns full subtree when key points to array', function(): void {
        config::set('app.session.driver', 'redis');
        config::set('app.session.lifetime', 3600);
        $session = config::get('app.session');
        expect($session)->toBeArray()
            ->toHaveKey('driver')
            ->toHaveKey('lifetime');
    });

});

describe('config::load()', function(): void {

    test('loads php config files from directory', function(): void {
        $dir = sys_get_temp_dir() . '/skim_cfg_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/app.php', '<?php return ["name" => "LoadTest"];');

        config::reset();
        config::load($dir);

        expect(config::get('app.name'))->toBe('LoadTest');

        unlink($dir . '/app.php');
        rmdir($dir);
    });

    test('is idempotent — second load() call is a no-op', function(): void {
        $dir = sys_get_temp_dir() . '/skim_cfg_idem_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/app.php', '<?php return ["env" => "test"];');

        config::reset();
        config::load($dir);
        config::load($dir);   // should not reload

        expect(config::get('app.env'))->toBe('test');

        unlink($dir . '/app.php');
        rmdir($dir);
    });

});
