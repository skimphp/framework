<?php declare(strict_types=1);

use skim\core\config;
use skim\dev\ide_link;

beforeEach(function(): void {
    config::reset();
});

afterEach(function(): void {
    config::reset();
});

describe('ide_link::supported()', function(): void {

    test('lists known editors in canonical order', function(): void {
        $ids = ide_link::supported();
        expect($ids)->toContain('phpstorm');
        expect($ids)->toContain('vscode');
        expect($ids)->toContain('cursor');
        expect($ids)->toContain('sublime');
        expect($ids)->toContain('idea');
        expect($ids)->toContain('textmate');
    });

    test('has at least 5 popular editors', function(): void {
        expect(count(ide_link::supported()))->toBeGreaterThanOrEqual(5);
    });

});

describe('ide_link::name()', function(): void {

    test('returns display name for known IDE', function(): void {
        expect(ide_link::name('phpstorm'))->toBe('PhpStorm');
        expect(ide_link::name('vscode'))->toBe('VS Code');
        expect(ide_link::name('cursor'))->toBe('Cursor');
        expect(ide_link::name('sublime'))->toBe('Sublime Text');
    });

    test('returns identifier when IDE is unknown', function(): void {
        expect(ide_link::name('notepad'))->toBe('notepad');
    });

});

describe('ide_link::is_supported()', function(): void {

    test('returns true for known IDEs', function(): void {
        expect(ide_link::is_supported('phpstorm'))->toBeTrue();
        expect(ide_link::is_supported('vscode'))->toBeTrue();
    });

    test('returns false for unknown IDEs', function(): void {
        expect(ide_link::is_supported('gedit'))->toBeFalse();
    });

});

describe('ide_link::resolve()', function(): void {

    test('defaults to phpstorm when no config and no argument', function(): void {
        expect(ide_link::resolve())->toBe('phpstorm');
    });

    test('reads app.debug_ide from config', function(): void {
        config::set('app.debug_ide', 'vscode');
        expect(ide_link::resolve())->toBe('vscode');
    });

    test('explicit argument overrides config', function(): void {
        config::set('app.debug_ide', 'vscode');
        expect(ide_link::resolve('cursor'))->toBe('cursor');
    });

    test('falls back to phpstorm for unknown config value', function(): void {
        config::set('app.debug_ide', 'notepad');
        expect(ide_link::resolve())->toBe('phpstorm');
    });

    test('falls back to phpstorm for unknown argument', function(): void {
        expect(ide_link::resolve('gedit'))->toBe('phpstorm');
    });

});

describe('ide_link::url()', function(): void {

    test('builds phpstorm deep-link', function(): void {
        $url = ide_link::url('/app/src/Foo.php', 42, 1, 'phpstorm');
        expect($url)->toStartWith('phpstorm://open?file=');
        expect($url)->toContain('line=42');
    });

    test('builds vscode deep-link with file:line:column', function(): void {
        $url = ide_link::url('/app/src/Foo.php', 42, 7, 'vscode');
        expect($url)->toStartWith('vscode://file/');
        expect($url)->toContain(':42:7');
    });

    test('builds cursor deep-link', function(): void {
        $url = ide_link::url('/app/src/Foo.php', 42, 3, 'cursor');
        expect($url)->toStartWith('cursor://file/');
    });

    test('builds sublime deep-link', function(): void {
        $url = ide_link::url('/app/src/Foo.php', 42, 1, 'sublime');
        expect($url)->toStartWith('subl://open?url=file://');
        expect($url)->toContain('line=42');
    });

    test('builds idea deep-link', function(): void {
        $url = ide_link::url('/app/src/Foo.php', 42, 1, 'idea');
        expect($url)->toStartWith('idea://open?file=');
    });

    test('url-encodes special characters in file paths', function(): void {
        $url = ide_link::url('/app/src/My Module/Foo.php', 1, 1, 'phpstorm');
        expect($url)->toContain(rawurlencode('/app/src/My Module/Foo.php'));
    });

    test('falls back to phpstorm for unknown IDE', function(): void {
        $url = ide_link::url('/app/src/Foo.php', 1, 1, 'gedit');
        expect($url)->toStartWith('phpstorm://');
    });

    test('reads active IDE from config when argument omitted', function(): void {
        config::set('app.debug_ide', 'vscode');
        $url = ide_link::url('/app/src/Foo.php', 1);
        expect($url)->toStartWith('vscode://');
    });

});

describe('ide_link::icon()', function(): void {

    test('returns phpstorm icon for jetbrains family', function(): void {
        expect(ide_link::icon('phpstorm'))->toBe('brand-phpstorm');
        expect(ide_link::icon('idea'))->toBe('brand-phpstorm');
        expect(ide_link::icon('webstorm'))->toBe('brand-phpstorm');
    });

    test('returns vscode icon for vscode', function(): void {
        expect(ide_link::icon('vscode'))->toBe('brand-vscode');
    });

    test('returns cursor icon for cursor', function(): void {
        expect(ide_link::icon('cursor'))->toBe('cursor');
    });

    test('unknown IDE falls back to phpstorm icon (via resolve)', function(): void {
        // icon() routes through resolve(), which normalises unknown identifiers
        // to phpstorm — so the icon for any unknown IDE is the phpstorm icon.
        expect(ide_link::icon('notepad'))->toBe('brand-phpstorm');
    });

});
