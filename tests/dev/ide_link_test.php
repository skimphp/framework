<?php declare(strict_types=1);

use Skim\Core\Config;
use Skim\Dev\IdeLink;

beforeEach(function(): void {
    \Skim\Core\Config::reset();
});

afterEach(function(): void {
    \Skim\Core\Config::reset();
});

describe('IdeLink::supported()', function(): void {

    test('lists known editors in canonical order', function(): void {
        $ids = \Skim\Dev\IdeLink::supported();
        expect($ids)->toContain('phpstorm');
        expect($ids)->toContain('vscode');
        expect($ids)->toContain('cursor');
        expect($ids)->toContain('sublime');
        expect($ids)->toContain('idea');
        expect($ids)->toContain('textmate');
    });

    test('has at least 5 popular editors', function(): void {
        expect(count(\Skim\Dev\IdeLink::supported()))->toBeGreaterThanOrEqual(5);
    });

});

describe('IdeLink::name()', function(): void {

    test('returns display name for known IDE', function(): void {
        expect(\Skim\Dev\IdeLink::name('phpstorm'))->toBe('PhpStorm');
        expect(\Skim\Dev\IdeLink::name('vscode'))->toBe('VS Code');
        expect(\Skim\Dev\IdeLink::name('cursor'))->toBe('Cursor');
        expect(\Skim\Dev\IdeLink::name('sublime'))->toBe('Sublime Text');
    });

    test('returns identifier when IDE is unknown', function(): void {
        expect(\Skim\Dev\IdeLink::name('notepad'))->toBe('notepad');
    });

});

describe('IdeLink::isSupported()', function(): void {

    test('returns true for known IDEs', function(): void {
        expect(\Skim\Dev\IdeLink::isSupported('phpstorm'))->toBeTrue();
        expect(\Skim\Dev\IdeLink::isSupported('vscode'))->toBeTrue();
    });

    test('returns false for unknown IDEs', function(): void {
        expect(\Skim\Dev\IdeLink::isSupported('gedit'))->toBeFalse();
    });

});

describe('IdeLink::resolve()', function(): void {

    test('defaults to phpstorm when no config and no argument', function(): void {
        expect(\Skim\Dev\IdeLink::resolve())->toBe('phpstorm');
    });

    test('reads app.debug_ide from config', function(): void {
        \Skim\Core\Config::set('app.debug_ide', 'vscode');
        expect(\Skim\Dev\IdeLink::resolve())->toBe('vscode');
    });

    test('explicit argument overrides config', function(): void {
        \Skim\Core\Config::set('app.debug_ide', 'vscode');
        expect(\Skim\Dev\IdeLink::resolve('cursor'))->toBe('cursor');
    });

    test('falls back to phpstorm for unknown config value', function(): void {
        \Skim\Core\Config::set('app.debug_ide', 'notepad');
        expect(\Skim\Dev\IdeLink::resolve())->toBe('phpstorm');
    });

    test('falls back to phpstorm for unknown argument', function(): void {
        expect(\Skim\Dev\IdeLink::resolve('gedit'))->toBe('phpstorm');
    });

});

describe('IdeLink::url()', function(): void {

    test('builds phpstorm deep-link', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 42, 1, 'phpstorm');
        expect($url)->toStartWith('phpstorm://open?file=');
        expect($url)->toContain('line=42');
    });

    test('builds vscode deep-link with file:line:column', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 42, 7, 'vscode');
        expect($url)->toStartWith('vscode://file/');
        expect($url)->toContain(':42:7');
    });

    test('builds cursor deep-link', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 42, 3, 'cursor');
        expect($url)->toStartWith('cursor://file/');
    });

    test('builds sublime deep-link', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 42, 1, 'sublime');
        expect($url)->toStartWith('subl://open?url=file://');
        expect($url)->toContain('line=42');
    });

    test('builds idea deep-link', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 42, 1, 'idea');
        expect($url)->toStartWith('idea://open?file=');
    });

    test('url-encodes special characters in file paths', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/My Module/Foo.php', 1, 1, 'phpstorm');
        expect($url)->toContain(rawurlencode('/app/src/My Module/Foo.php'));
    });

    test('falls back to phpstorm for unknown IDE', function(): void {
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 1, 1, 'gedit');
        expect($url)->toStartWith('phpstorm://');
    });

    test('reads active IDE from config when argument omitted', function(): void {
        \Skim\Core\Config::set('app.debug_ide', 'vscode');
        $url = \Skim\Dev\IdeLink::url('/app/src/Foo.php', 1);
        expect($url)->toStartWith('vscode://');
    });

});

describe('IdeLink::icon()', function(): void {

    test('returns phpstorm icon for jetbrains family', function(): void {
        expect(\Skim\Dev\IdeLink::icon('phpstorm'))->toBe('brand-phpstorm');
        expect(\Skim\Dev\IdeLink::icon('idea'))->toBe('brand-phpstorm');
        expect(\Skim\Dev\IdeLink::icon('webstorm'))->toBe('brand-phpstorm');
    });

    test('returns vscode icon for vscode', function(): void {
        expect(\Skim\Dev\IdeLink::icon('vscode'))->toBe('brand-vscode');
    });

    test('returns cursor icon for cursor', function(): void {
        expect(\Skim\Dev\IdeLink::icon('cursor'))->toBe('cursor');
    });

    test('unknown IDE falls back to phpstorm icon (via resolve)', function(): void {
        // icon() routes through resolve(), which normalises unknown identifiers
        // to phpstorm — so the icon for any unknown IDE is the phpstorm icon.
        expect(\Skim\Dev\IdeLink::icon('notepad'))->toBe('brand-phpstorm');
    });

});
