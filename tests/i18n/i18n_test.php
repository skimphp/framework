<?php declare(strict_types=1);

use Skim\I18n\I18n;

beforeEach(function(): void {
    \Skim\I18n\I18n::reset();
    \Skim\I18n\I18n::setPath(dirname(__DIR__) . '/fixtures/lang');
});

describe('I18n::t() — basic translation', function(): void {

    test('returns translation for existing key', function(): void {
        \Skim\I18n\I18n::setLoader(fn($locale, $key) => match ($key) {
            'auth.login' => 'Sign in',
            default      => null,
        });
        expect(\Skim\I18n\I18n::t('auth.login'))->toBe('Sign in');
    });

    test('returns key unchanged when no translation found', function(): void {
        \Skim\I18n\I18n::setLoader(fn($locale, $key) => null);
        expect(\Skim\I18n\I18n::t('auth.missing'))->toBe('auth.missing');
    });

    test('interpolates :param placeholders', function(): void {
        \Skim\I18n\I18n::setLoader(fn($l, $k) => 'Hello, :name!');
        expect(\Skim\I18n\I18n::t('greet', ['name' => 'Alice']))->toBe('Hello, Alice!');
    });

});

describe('I18n::t() — pluralization', function(): void {

    test('uses singular form when count = 1', function(): void {
        \Skim\I18n\I18n::setLoader(fn($l, $k) => 'One item|Many items');
        expect(\Skim\I18n\I18n::t('items', ['count' => 1]))->toBe('One item');
    });

    test('uses plural form when count > 1', function(): void {
        \Skim\I18n\I18n::setLoader(fn($l, $k) => 'One item|Many items');
        expect(\Skim\I18n\I18n::t('items', ['count' => 5]))->toBe('Many items');
    });

    test('plural with param substitution', function(): void {
        \Skim\I18n\I18n::setLoader(fn($l, $k) => '1 result|:count results');
        expect(\Skim\I18n\I18n::t('results', ['count' => 42]))->toBe('42 results');
    });

});

describe('i18n — locale switching', function(): void {

    test('locale() changes active locale', function(): void {
        $translations = ['en' => 'Welcome', 'fr' => 'Bienvenue'];
        \Skim\I18n\I18n::setLoader(fn($locale, $key) => $translations[$locale] ?? null);

        \Skim\I18n\I18n::locale('fr');
        expect(\Skim\I18n\I18n::t('welcome'))->toBe('Bienvenue');

        \Skim\I18n\I18n::locale('en');
        expect(\Skim\I18n\I18n::t('welcome'))->toBe('Welcome');
    });

    test('currentLocale() returns active locale', function(): void {
        \Skim\I18n\I18n::locale('de');
        expect(\Skim\I18n\I18n::currentLocale())->toBe('de');
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

        \Skim\I18n\I18n::reset();
        \Skim\I18n\I18n::setPath($dir);

        expect(\Skim\I18n\I18n::t('messages.hello'))->toBe('Hello World');

        unlink("{$dir}/en/messages.php");
        rmdir("{$dir}/en");
    });

});
