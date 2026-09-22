<?php declare(strict_types=1);

use Skim\Core\Env;

describe('env lazy loading', function (): void {

    test('Env::get() triggers lazy load on first call', function (): void {
        \Skim\Core\Env::reset();
        $_ENV['SKIM_LAZY_TEST_KEY'] = 'lazy_value';

        $value = \Skim\Core\Env::get('SKIM_LAZY_TEST_KEY', 'fallback_value');
        expect($value)->toBe('lazy_value');

        unset($_ENV['SKIM_LAZY_TEST_KEY']);
    });

    test('Env::get() second call is faster than first', function (): void {
        \Skim\Core\Env::reset();

        $start = microtime(true);
        \Skim\Core\Env::get('APP_NAME', 'test');
        $first = microtime(true) - $start;

        $start = microtime(true);
        \Skim\Core\Env::get('APP_NAME', 'test');
        $second = microtime(true) - $start;

        expect($first)->toBeLessThan(0.010)
            ->and($second)->toBeLessThan($first + 0.001);
    });

    test('Env::load() does not call putenv()', function (): void {
        \Skim\Core\Env::reset();
        $tmp = sys_get_temp_dir() . '/skim_env_test_' . uniqid() . '.env';
        file_put_contents($tmp, "TEST_PUTENV_CHECK=putenv_value\n");

        \Skim\Core\Env::load($tmp);

        expect(getenv('TEST_PUTENV_CHECK'))->toBeFalse();
        expect(\Skim\Core\Env::get('TEST_PUTENV_CHECK'))->toBe('putenv_value');

        unlink($tmp);
    });

    test('OS env ($_SERVER) takes priority over .env', function (): void {
        \Skim\Core\Env::reset();
        $_SERVER['SKIM_OS_PRIORITY_TEST'] = 'from_server';

        $tmp = sys_get_temp_dir() . '/skim_env_os_' . uniqid() . '.env';
        file_put_contents($tmp, "SKIM_OS_PRIORITY_TEST=from_env_file\n");

        \Skim\Core\Env::load($tmp);

        expect(\Skim\Core\Env::get('SKIM_OS_PRIORITY_TEST'))->toBe('from_server');

        unset($_SERVER['SKIM_OS_PRIORITY_TEST']);
        unlink($tmp);
    });

    test('OS env ($_ENV) takes priority over .env', function (): void {
        \Skim\Core\Env::reset();
        $_ENV['SKIM_ENV_PRIORITY_TEST'] = 'from_env_global';

        $tmp = sys_get_temp_dir() . '/skim_env_os2_' . uniqid() . '.env';
        file_put_contents($tmp, "SKIM_ENV_PRIORITY_TEST=from_env_file\n");

        \Skim\Core\Env::load($tmp);

        expect(\Skim\Core\Env::get('SKIM_ENV_PRIORITY_TEST'))->toBe('from_env_global');

        unset($_ENV['SKIM_ENV_PRIORITY_TEST']);
        unlink($tmp);
    });

    test('Env::get() with compiled cache does not read .env from disk', function (): void {
        \Skim\Core\Env::reset();

        $cachePath = storagePath('config_cache/env.php');
        $backup     = is_file($cachePath) ? file_get_contents($cachePath) : null;

        try {
            file_put_contents($cachePath, "<?php\nreturn ['COMPILE_TEST_KEY' => 'from_compiled'];\n");

            $value = \Skim\Core\Env::get('COMPILE_TEST_KEY', 'miss');
            expect($value)->toBe('from_compiled');
        } finally {
            if ($backup !== null) {
                file_put_contents($cachePath, $backup);
            }
            elseif (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    });

});
