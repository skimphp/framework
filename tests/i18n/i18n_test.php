<?php declare(strict_types=1);

use skim\i18n\i18n;

beforeEach(function(): void {
    i18n::reset();
    i18n::set_path(dirname(__DIR__) . '/fixtures/lang');
});

describe('i18n::t() — basic translation', function(): void {

    test('returns translation for existing key', function(): void {
        i18n::set_loader(fn($locale, $key) => match ($key) {
            'auth.login' => 'Sign in',
            default      => null,
        });
        expect(i18n::t('auth.login'))->toBe('Sign in');
    });

    test('returns key unchanged when no translation found', function(): void {
        i18n::set_loader(fn($locale, $key) => null);
        expect(i18n::t('auth.missing'))->toBe('auth.missing');
    });

    test('interpolates :param placeholders', function(): void {
        i18n::set_loader(fn($l, $k) => 'Hello, :name!');
        expect(i18n::t('greet', ['name' => 'Alice']))->toBe('Hello, Alice!');
    });

});

describe('i18n::t() — pluralization', function(): void {

    test('uses singular form when count = 1', function(): void {
        i18n::set_loader(fn($l, $k) => 'One item|Many items');
        expect(i18n::t('items', ['count' => 1]))->toBe('One item');
    });

    test('uses plural form when count > 1', function(): void {
        i18n::set_loader(fn($l, $k) => 'One item|Many items');
        expect(i18n::t('items', ['count' => 5]))->toBe('Many items');
    });

    test('plural with param substitution', function(): void {
        i18n::set_loader(fn($l, $k) => '1 result|:count results');
        expect(i18n::t('results', ['count' => 42]))->toBe('42 results');
    });

});

describe('i18n — locale switching', function(): void {

    test('locale() changes active locale', function(): void {
        $translations = ['en' => 'Welcome', 'fr' => 'Bienvenue'];
        i18n::set_loader(fn($locale, $key) => $translations[$locale] ?? null);

        i18n::locale('fr');
        expect(i18n::t('welcome'))->toBe('Bienvenue');

        i18n::locale('en');
        expect(i18n::t('welcome'))->toBe('Welcome');
    });

    test('current_locale() returns active locale', function(): void {
        i18n::locale('de');
        expect(i18n::current_locale())->toBe('de');
    });

});

describe('i18n — file-based translations', function(): void {

    test('loads translation from PHP array file', function(): void {
        // tests/fixtures/lang/en/messages.php must exist
        $dir = dirname(__DIR__) . '/fixtures/lang';
        if (!is_dir("{$dir}/en")) {
            mkdir("{$dir}/en", 0755, true);
        }
        file_put_contents("{$dir}/en/messages.php", "<?php return ['hello' => 'Hello World'];");

        i18n::reset();
        i18n::set_path($dir);

        expect(i18n::t('messages.hello'))->toBe('Hello World');

        unlink("{$dir}/en/messages.php");
        rmdir("{$dir}/en");
    });

});
