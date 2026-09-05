<?php declare(strict_types=1);

use Skim\Core\Config;

describe('config lazy loading', function (): void {

    test('config::get() triggers lazy load on first call', function (): void {
        \Skim\Core\Config::reset();
        $value = \Skim\Core\Config::get('app.name', 'fallback_value');
        expect($value)->not->toBe('fallback_value');
    });

    test('config::get() second call uses cached data', function (): void {
        \Skim\Core\Config::reset();

        $start = microtime(true);
        \Skim\Core\Config::get('app.name', 'test');
        $first = microtime(true) - $start;

        $start = microtime(true);
        \Skim\Core\Config::get('app.name', 'test');
        $second = microtime(true) - $start;

        expect($first)->toBeLessThan(0.010)
            ->and($second)->toBeLessThan($first + 0.001);
    });

    test('config::load() is idempotent — second call is a no-op', function (): void {
        \Skim\Core\Config::reset();

        $dir = sys_get_temp_dir() . '/skim_cfg_lazy_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/app.php', '<?php return ["lazy_test" => "first_load"];');

        \Skim\Core\Config::load($dir);
        expect(\Skim\Core\Config::get('app.lazy_test'))->toBe('first_load');

        file_put_contents($dir . '/app.php', '<?php return ["lazy_test" => "second_load"];');
        \Skim\Core\Config::load($dir);

        expect(\Skim\Core\Config::get('app.lazy_test'))->toBe('first_load');

        unlink($dir . '/app.php');
        rmdir($dir);
    });

    test('config::load() with compiled cache skips directory scan', function (): void {
        \Skim\Core\Config::reset();

        $cache_path = storage_path('config_cache/config.php');
        $backup     = is_file($cache_path) ? file_get_contents($cache_path) : null;

        try {
            file_put_contents($cache_path, "<?php\nreturn ['app' => ['compiled_key' => 'from_cache']];\n");

            $value = \Skim\Core\Config::get('app.compiled_key', 'miss');
            expect($value)->toBe('from_cache');
        } finally {
            if ($backup !== null) {
                file_put_contents($cache_path, $backup);
            }
            elseif (is_file($cache_path)) {
                unlink($cache_path);
            }
        }
    });

});
