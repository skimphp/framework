<?php declare(strict_types=1);

use skim\core\env;

describe('env lazy loading', function (): void {

    test('env::get() triggers lazy load on first call', function (): void {
        env::reset();
        $value = env::get('APP_NAME', 'fallback_value');
        expect($value)->not->toBe('fallback_value');
    });

    test('env::get() second call is faster than first', function (): void {
        env::reset();

        $start = microtime(true);
        env::get('APP_NAME', 'test');
        $first = microtime(true) - $start;

        $start = microtime(true);
        env::get('APP_NAME', 'test');
        $second = microtime(true) - $start;

        expect($first)->toBeLessThan(0.010)
            ->and($second)->toBeLessThan($first + 0.001);
    });

    test('env::load() does not call putenv()', function (): void {
        env::reset();
        $tmp = sys_get_temp_dir() . '/skim_env_test_' . uniqid() . '.env';
        file_put_contents($tmp, "TEST_PUTENV_CHECK=putenv_value\n");

        env::load($tmp);

        expect(getenv('TEST_PUTENV_CHECK'))->toBeFalse();
        expect(env::get('TEST_PUTENV_CHECK'))->toBe('putenv_value');

        unlink($tmp);
    });

    test('OS env ($_SERVER) takes priority over .env', function (): void {
        env::reset();
        $_SERVER['SKIM_OS_PRIORITY_TEST'] = 'from_server';

        $tmp = sys_get_temp_dir() . '/skim_env_os_' . uniqid() . '.env';
        file_put_contents($tmp, "SKIM_OS_PRIORITY_TEST=from_env_file\n");

        env::load($tmp);

        expect(env::get('SKIM_OS_PRIORITY_TEST'))->toBe('from_server');

        unset($_SERVER['SKIM_OS_PRIORITY_TEST']);
        unlink($tmp);
    });

    test('OS env ($_ENV) takes priority over .env', function (): void {
        env::reset();
        $_ENV['SKIM_ENV_PRIORITY_TEST'] = 'from_env_global';

        $tmp = sys_get_temp_dir() . '/skim_env_os2_' . uniqid() . '.env';
        file_put_contents($tmp, "SKIM_ENV_PRIORITY_TEST=from_env_file\n");

        env::load($tmp);

        expect(env::get('SKIM_ENV_PRIORITY_TEST'))->toBe('from_env_global');

        unset($_ENV['SKIM_ENV_PRIORITY_TEST']);
        unlink($tmp);
    });

    test('env::get() with compiled cache does not read .env from disk', function (): void {
        env::reset();

        $cache_path = storage_path('config_cache/env.php');
        $backup     = is_file($cache_path) ? file_get_contents($cache_path) : null;

        try {
            file_put_contents($cache_path, "<?php\nreturn ['COMPILE_TEST_KEY' => 'from_compiled'];\n");

            $value = env::get('COMPILE_TEST_KEY', 'miss');
            expect($value)->toBe('from_compiled');
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
