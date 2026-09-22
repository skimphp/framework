<?php declare(strict_types=1);

use Skim\Helpers\Str;

describe('Str', function(): void {

    test('slug lowercases and replaces separators', function(): void {
        expect(Str::slug('Hello World!'))->toBe('hello-world');
        expect(Str::slug('  multiple   spaces  '))->toBe('multiple-spaces');
    });

    test('slug is unicode-aware', function(): void {
        expect(Str::slug('Привет Мир'))->toBe('привет-мир');
    });

    test('excerpt truncates with suffix', function(): void {
        expect(Str::excerpt('short', 100))->toBe('short');
        expect(Str::excerpt('one two three four', 10))->toBe('one two...');
    });

    test('excerpt can cut mid-word', function(): void {
        expect(Str::excerpt('abcdefghij', 5, '…', false))->toBe('abcde…');
    });

    test('random returns requested length of alphanumerics', function(): void {
        $s = Str::random(16);
        expect(strlen($s))->toBe(16);
        expect(ctype_alnum($s))->toBeTrue();
        expect(Str::random(8))->not->toBe(Str::random(8));
    });

    test('uuid matches RFC 4122 v4 format', function(): void {
        expect(Str::uuid())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });

    test('contains/startsWith/endsWith delegate to str_*', function(): void {
        expect(Str::contains('hello world', 'world'))->toBeTrue();
        expect(Str::contains('hello', 'x'))->toBeFalse();
        expect(Str::startsWith('skim', 'sk'))->toBeTrue();
        expect(Str::endsWith('file.php', '.php'))->toBeTrue();
    });

    test('toSnake handles consecutive capitals', function(): void {
        expect(Str::toSnake('HomeController'))->toBe('home_controller');
        expect(Str::toSnake('HTTPResponse'))->toBe('http_response');
        expect(Str::toSnake('userId'))->toBe('user_id');
    });

    test('toCamel converts snake_case', function(): void {
        expect(Str::toCamel('home_controller'))->toBe('homeController');
        expect(Str::toCamel('simple'))->toBe('simple');
    });

});
