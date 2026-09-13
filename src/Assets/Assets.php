<?php declare(strict_types=1);

namespace Skim\Assets;

/**
 * Vite asset integration — resolves hashed URLs and generates script/style tags. #AI:class
 *
 * Use in templates to reference JS/CSS built by Vite. In dev mode (APP_DEBUG=true),
 * proxies through Vite's HMR dev server. In production, reads manifest.json
 * for cache-busted hashed filenames.
 *
 * Example:
 *   <?= Assets::js('js/app.js') ?>
 *   <?= Assets::css('css/app.css') ?>
 *
 * Testing: Use setManifest() to inject a fake manifest, reset() in tearDown().
 *
 * #AI:class
 */
final class Assets {
    private static ?array  $manifest    = null;
    private static string  $viteUrl    = 'http://localhost:5173';
    private static string  $publicPath = '';

    /**
     * Returns the URL for a given asset path. #AI:url
     *
     * In dev mode: returns Vite HMR proxy URL. In production: returns the
     * hashed filename from manifest.json under the configured build path.
     *
     * @param string $path Asset path relative to the Vite project root.
     * @throws \RuntimeException In production when the asset is not in the manifest.
     */
    public static function url(string $path): string {
        $path = ltrim($path, '/');

        if ((bool) \Skim\Core\Config::get('app.debug', false)) {
            return self::$viteUrl . '/' . $path;
        }

        $manifest = self::manifest();
        if (!isset($manifest[$path])) {
            throw new \RuntimeException("Asset '{$path}' not found in Vite manifest.");
        }

        $buildPath = (string) \Skim\Core\Config::get('assets.build_path', '/build');
        return rtrim($buildPath, '/') . '/' . $manifest[$path]['file'];
    }

    /**
     * Returns a <script type="module"> tag for a JS entrypoint. #AI:js
     *
     * In dev mode, also injects the Vite HMR client script for hot reload.
     *
     * @param string $path JS entrypoint path relative to Vite project root.
     */
    public static function js(string $path): string {
        $url     = self::url($path);
        $devHmr = (bool) \Skim\Core\Config::get('app.debug', false)
            ? '<script type="module" src="' . self::$viteUrl . '/@vite/client"></script>' . "\n"
            : '';
        return $devHmr . '<script type="module" src="' . e($url) . '"></script>';
    }

    /**
     * Returns a <link rel="stylesheet"> tag for a CSS file. #AI:css
     *
     * In dev mode, returns empty string — Vite injects CSS via HMR JS.
     *
     * @param string $path CSS file path relative to Vite project root.
     */
    public static function css(string $path): string {
        if ((bool) \Skim\Core\Config::get('app.debug', false)) {
            return '';
        }
        $url = self::url($path);
        return '<link rel="stylesheet" href="' . e($url) . '">';
    }

    /**
     * Sets the Vite dev server URL (testing only). #AI:setViteUrl
     *
     * @param string $url Vite dev server base URL.
     */
    public static function setViteUrl(string $url): void {
        self::$viteUrl = rtrim($url, '/');
    }

    /**
     * Injects a manifest array directly (testing only). #AI:setManifest
     *
     * Bypasses file-based manifest loading. Call reset() in tearDown().
     *
     * @param array $manifest Fake Vite manifest for testing.
     */
    public static function setManifest(array $manifest): void {
        self::$manifest = $manifest;
    }

    /**
     * Clears cached manifest state (testing only). #AI:reset
     *
     * Forces re-read from disk on the next url() call.
     */
    public static function reset(): void {
        self::$manifest = null;
    }

    // --- internals ---

    private static function manifest(): array {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = (self::$publicPath ?: basePath('public')) . '/build/.vite/manifest.json';

        if (!is_file($path)) {
            $path = (self::$publicPath ?: basePath('public')) . '/build/manifest.json';
        }

        if (!is_file($path)) {
            throw new \RuntimeException('Vite manifest not found. Run: npm run build');
        }

        $data = json_decode((string) file_get_contents($path), true);
        return self::$manifest = is_array($data) ? $data : [];
    }
}

#AI:class
#AI symbol: Skim\Assets\Assets
#AI source_path: src/Assets/Assets.php
#AI title: assets
#AI description: Vite asset integration — resolves hashed URLs from manifest.json and generates script/style tags with HMR support.
#AI role: Vite asset resolver
#AI layer: assets
#AI badges: [vite; assets; hmr; manifest]
#AI intro: `assets` resolves asset paths to Vite-built URLs. In dev mode (APP_DEBUG=true), it proxies through Vite's HMR dev server at localhost:5173. In production, it reads the Vite manifest.json to return cache-busted hashed filenames.
#AI lifecycle: static facade, manifest loaded and cached on first production url() call
#AI fallback: checks .vite/manifest.json then build/manifest.json for older Vite versions
#AI test_seam: setManifest(), setViteUrl(), reset()
#AI invariants: [dev mode always proxies to Vite URL; production requires manifest.json; css() returns empty string in dev mode (Vite injects via HMR)]
#AI core_behaviors: [Manifest is loaded lazily on first production url() call and cached; url() output is HTML-escaped via e() in js()/css() tags]
#AI warnings: [Production throws RuntimeException if manifest.json is missing — run npm run build first]
#AI notes: The manifest cache is process-local. Call reset() after changing manifest files in tests.
#AI scope_items: []
#AI owns: manifest cache, vite URL, public path
#AI entry_points: [url; js; css]
#AI config_reads: [app.debug; assets.build_path]
#AI non_goals: [Does not run Vite build; Does not handle image/font assets; Does not support multiple Vite projects]
#AI side_effects: [Reads manifest.json from disk on first production call]
#AI flow: Assets::url(path) -> debug? viteUrl/path : manifest()[path] -> buildPath/file
#AI lifecycle_steps: [Assets::url/js/css(); -> debug check; -> dev: viteUrl + path; -> prod: manifest() loads from disk; -> manifest[path].file; -> buildPath + hashed filename]
#AI section_order: [Asset Resolution; Tag Generation; Testing Hooks]
#AI architectural_notes: Thin integration layer — all intelligence is in Vite's manifest format. The facade exists to provide a single API for templates.

#AI:url
#AI group: Asset Resolution
#AI frequency: high
#AI signature: public static function url(string $path): string
#AI contract: Returns the URL for a given asset path. In dev mode, returns the Vite HMR proxy URL. In production, returns the hashed filename from manifest.json.
#AI param_details: [{name: $path | type: string | required: true | desc: Asset path relative to Vite project root.}]
#AI return_detail: {type: string | desc: Resolved asset URL.}
#AI throws_details: [{type: \RuntimeException | desc: In production when asset is not found in manifest.}]

#AI:js
#AI group: Tag Generation
#AI frequency: high
#AI signature: public static function js(string $path): string
#AI contract: Returns a <script type="module"> tag. In dev mode, also injects the Vite HMR client script.
#AI param_details: [{name: $path | type: string | required: true | desc: JS entrypoint path.}]
#AI return_detail: {type: string | desc: HTML script tag(s).}

#AI:css
#AI group: Tag Generation
#AI frequency: high
#AI signature: public static function css(string $path): string
#AI contract: Returns a <link rel="stylesheet"> tag. In dev mode, returns empty string because Vite injects CSS via HMR JS.
#AI param_details: [{name: $path | type: string | required: true | desc: CSS file path.}]
#AI return_detail: {type: string | desc: HTML link tag or empty string in dev mode.}

#AI:setViteUrl
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function setViteUrl(string $url): void
#AI contract: Overrides the Vite dev server URL. Default is http://localhost:5173.
#AI param_details: [{name: $url | type: string | required: true | desc: Vite dev server base URL.}]
#AI side_effects: Mutates static state.

#AI:setManifest
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function setManifest(array $manifest): void
#AI contract: Injects a manifest array directly, bypassing file-based loading. Use in tests.
#AI param_details: [{name: $manifest | type: array | required: true | desc: Fake Vite manifest.}]
#AI side_effects: Mutates static manifest cache.

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the cached manifest. Forces re-read from disk on the next url() call.
#AI side_effects: Clears static manifest cache.
