<?php declare(strict_types=1);

use Skim\Assets\Assets;
use Skim\Core\Config;
use Skim\Ext\CapabilityVocabulary;

describe('Assets', function(): void {

    afterEach(function(): void {
        Assets::reset();
        Assets::setViteUrl('http://localhost:5173');
    });

    test('dev mode proxies url through vite server', function(): void {
        Config::set('app.debug', true);
        Assets::setViteUrl('http://vite.test:5173/');

        expect(Assets::url('/app.js'))->toBe('http://vite.test:5173/app.js');
    });

    test('prod resolves hashed file from injected manifest', function(): void {
        Config::set('app.debug', false);
        Assets::setManifest(['app.js' => ['file' => 'assets/app-a1b2.js']]);

        expect(Assets::url('app.js'))->toBe('/build/assets/app-a1b2.js');
    });

    test('prod throws when asset missing from manifest', function(): void {
        Config::set('app.debug', false);
        Assets::setManifest([]);

        Assets::url('nope.js');
    })->throws(\RuntimeException::class);

    test('js() emits module script tag with hmr client in dev', function(): void {
        Config::set('app.debug', true);
        $tag = Assets::js('app.js');

        expect($tag)->toContain('@vite/client');
        expect($tag)->toContain('type="module"');
        expect($tag)->toContain('app.js');
    });

    test('js() emits plain module tag in prod', function(): void {
        Config::set('app.debug', false);
        Assets::setManifest(['app.js' => ['file' => 'assets/app-x.js']]);

        $tag = Assets::js('app.js');
        expect($tag)->toContain('/build/assets/app-x.js');
        expect($tag)->not->toContain('@vite/client');
    });

    test('css() returns empty in dev, link tag in prod', function(): void {
        Config::set('app.debug', true);
        expect(Assets::css('app.css'))->toBe('');

        Config::set('app.debug', false);
        Assets::setManifest(['app.css' => ['file' => 'assets/app-y.css']]);
        expect(Assets::css('app.css'))->toContain('<link rel="stylesheet"');
    });

});

describe('CapabilityVocabulary', function(): void {

    test('isKnown recognizes declared capability terms', function(): void {
        expect(CapabilityVocabulary::isKnown(array_key_first(CapabilityVocabulary::TERMS)))->toBeTrue();
        expect(CapabilityVocabulary::isKnown('not-a-real-cap'))->toBeFalse();
    });

});
