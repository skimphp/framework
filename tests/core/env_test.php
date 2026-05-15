<?php declare(strict_types=1);

use skim\core\env;

describe('env::get()', function(): void {

    test('returns default when key is not set', function(): void {
        expect(env::get('UNDEFINED_KEY_XYZ', 'default'))->toBe('default');
    });

    test('returns value after env::set()', function(): void {
        env::set('TEST_KEY', 'hello');
        expect(env::get('TEST_KEY'))->toBe('hello');
    });

    test('casts "true" string to boolean true', function(): void {
        env::set('BOOL_TRUE', 'true');
        expect(env::get('BOOL_TRUE'))->toBeTrue();
    });

    test('casts "false" string to boolean false', function(): void {
        env::set('BOOL_FALSE', 'false');
        expect(env::get('BOOL_FALSE'))->toBeFalse();
    });

    test('casts "null" string to PHP null', function(): void {
        env::set('NULL_VAL', 'null');
        expect(env::get('NULL_VAL'))->toBeNull();
    });

    test('preserves numeric strings as strings', function(): void {
        env::set('PORT', '3306');
        expect(env::get('PORT'))->toBe('3306');
    });

});

describe('env::load()', function(): void {

    test('silently skips missing .env file', function(): void {
        env::load('/nonexistent/path/.env');
        expect(env::get('ANYTHING'))->toBeNull();
    });

    test('parses key=value pairs from .env file', function(): void {
        $tmp = sys_get_temp_dir() . '/skim_env_test_' . uniqid() . '.env';
        file_put_contents($tmp, "APP_NAME=\"Test App\"\nAPP_DEBUG=true\n");

        env::reset();
        env::load($tmp);

        expect(env::get('APP_NAME'))->toBe('Test App');
        expect(env::get('APP_DEBUG'))->toBeTrue();

        unlink($tmp);
    });

    test('skips comment lines starting with #', function(): void {
        $tmp = sys_get_temp_dir() . '/skim_env_comment_' . uniqid() . '.env';
        file_put_contents($tmp, "# this is a comment\nVALID_KEY=yes\n");

        env::reset();
        env::load($tmp);

        expect(env::get('VALID_KEY'))->toBe('yes');
        unlink($tmp);
    });

    test('is idempotent — second load() call is a no-op', function(): void {
        $tmp = sys_get_temp_dir() . '/skim_env_idempotent_' . uniqid() . '.env';
        file_put_contents($tmp, "IDEM_KEY=first\n");

        env::reset();
        env::load($tmp);
        env::load($tmp);   // second call must not reload

        expect(env::get('IDEM_KEY'))->toBe('first');
        unlink($tmp);
    });

});
