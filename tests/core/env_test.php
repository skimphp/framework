<?php declare(strict_types=1);

use Skim\Core\Env;

describe('Env::get()', function(): void {

    test('returns default when key is not set', function(): void {
        expect(\Skim\Core\Env::get('UNDEFINED_KEY_XYZ', 'default'))->toBe('default');
    });

    test('returns value after Env::set()', function(): void {
        \Skim\Core\Env::set('TEST_KEY', 'hello');
        expect(\Skim\Core\Env::get('TEST_KEY'))->toBe('hello');
    });

    test('casts "true" string to boolean true', function(): void {
        \Skim\Core\Env::set('BOOL_TRUE', 'true');
        expect(\Skim\Core\Env::get('BOOL_TRUE'))->toBeTrue();
    });

    test('casts "false" string to boolean false', function(): void {
        \Skim\Core\Env::set('BOOL_FALSE', 'false');
        expect(\Skim\Core\Env::get('BOOL_FALSE'))->toBeFalse();
    });

    test('casts "null" string to PHP null', function(): void {
        \Skim\Core\Env::set('NULL_VAL', 'null');
        expect(\Skim\Core\Env::get('NULL_VAL'))->toBeNull();
    });

    test('preserves numeric strings as strings', function(): void {
        \Skim\Core\Env::set('PORT', '3306');
        expect(\Skim\Core\Env::get('PORT'))->toBe('3306');
    });

});

describe('Env::load()', function(): void {

    test('silently skips missing .env file', function(): void {
        \Skim\Core\Env::load('/nonexistent/path/.env');
        expect(\Skim\Core\Env::get('ANYTHING'))->toBeNull();
    });

    test('parses key=value pairs from .env file', function(): void {
        $tmp = sys_get_temp_dir() . '/skim_env_test_' . uniqid() . '.env';
        file_put_contents($tmp, "APP_NAME=\"Test App\"\nAPP_DEBUG=true\n");

        \Skim\Core\Env::reset();
        \Skim\Core\Env::load($tmp);

        expect(\Skim\Core\Env::get('APP_NAME'))->toBe('Test App');
        expect(\Skim\Core\Env::get('APP_DEBUG'))->toBeTrue();

        unlink($tmp);
    });

    test('skips comment lines starting with #', function(): void {
        $tmp = sys_get_temp_dir() . '/skim_env_comment_' . uniqid() . '.env';
        file_put_contents($tmp, "# this is a comment\nVALID_KEY=yes\n");

        \Skim\Core\Env::reset();
        \Skim\Core\Env::load($tmp);

        expect(\Skim\Core\Env::get('VALID_KEY'))->toBe('yes');
        unlink($tmp);
    });

    test('is idempotent — second load() call is a no-op', function(): void {
        $tmp = sys_get_temp_dir() . '/skim_env_idempotent_' . uniqid() . '.env';
        file_put_contents($tmp, "IDEM_KEY=first\n");

        \Skim\Core\Env::reset();
        \Skim\Core\Env::load($tmp);
        \Skim\Core\Env::load($tmp);   // second call must not reload

        expect(\Skim\Core\Env::get('IDEM_KEY'))->toBe('first');
        unlink($tmp);
    });

});
