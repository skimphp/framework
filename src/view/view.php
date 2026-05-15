<?php declare(strict_types=1);

namespace skim\view;

use skim\dev\profiler;

// Static facade for rendering PHP templates.
// Templates live in app/views/ by default; override via view::set_path().
//
// Fragment extraction: parses <!-- @fragment name -->...<!-- @end --> blocks.
// Why HTML comment syntax: no runtime overhead, no DOM changes, zero bytes in browser output.
// Why not Twig/Blade: stack traces point to real PHP files, opcache handles compilation,
// PHP templates are ~3x faster than template engines with opcache enabled.
final class view {
    private static string $views_path  = '';
    private static array  $shared_data = [];

    /**
     * @ai-contract renders full template with $data merged into shared data
     * @ai-contract if $fragment set: extracts only the named @fragment block
     * @ai-contract throws view_exception if template file not found
     * @ai-contract throws view_exception if $fragment name not found in output
     * @ai-contract side-effect records render in profiler when APP_DEBUG=true
     */
    public static function render(string $template, array $data = [], ?string $fragment = null): string {
        $t    = microtime(true);
        $path = self::views_path();
        $ctx  = new template($path, array_merge(self::$shared_data, $data));

        $html = $ctx->render_file($template);

        if ($fragment !== null) {
            $html = self::extract_fragment($html, $fragment, $template);
        }

        profiler::view($template, $fragment, (microtime(true) - $t) * 1000);

        return $html;
    }

    /**
     * @ai-contract renders only the named @fragment block — same as render() with $fragment set
     */
    public static function render_fragment(string $template, array $data, string $fragment): string {
        return self::render($template, $data, $fragment);
    }

    /**
     * @ai-contract injects $key=$value into every subsequent template render for this request
     * @ai-contract use for current_user, app_name etc — not for per-template data
     */
    public static function share(string $key, mixed $value): void {
        self::$shared_data[$key] = $value;
    }

    /**
     * @ai-contract sets the root directory for template resolution
     * @ai-contract called during app boot — defaults to SKIM_ROOT/app/views
     */
    public static function set_path(string $path): void {
        self::$views_path = rtrim($path, '/');
    }

    /**
     * @ai-contract for tests — reset shared data and path
     */
    public static function reset(): void {
        self::$shared_data = [];
        self::$views_path  = '';
    }

    // --- internals ---

    private static function views_path(): string {
        if (self::$views_path !== '') {
            return self::$views_path;
        }
        $root = defined('SKIM_ROOT') ? \SKIM_ROOT : dirname(__DIR__, 3);
        return $root . '/app/views';
    }

    /**
     * Extracts <!-- @fragment name -->...<!-- @end --> from rendered HTML.
     * Regex is applied to the final rendered string — works with any nesting depth.
     *
     * @ai-contract returns trimmed fragment content (whitespace stripped)
     * @ai-contract throws view_exception if fragment name not found
     */
    private static function extract_fragment(string $html, string $name, string $template): string {
        $pattern = '/<!--\s*@fragment\s+' . preg_quote($name, '/') . '\s*-->(.*?)<!--\s*@end\s*-->/s';

        if (!preg_match($pattern, $html, $m)) {
            throw new exceptions\view_exception(
                "Fragment '{$name}' not found in template '{$template}'."
            );
        }

        return trim($m[1]);
    }
}
