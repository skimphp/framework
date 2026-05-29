<?php declare(strict_types=1);

namespace skim\dev;

/**
 * Developer-friendly error page rendered when APP_DEBUG=true. #AI:class
 *
 * Use only via the exception handler registered in app::run(). Shows full
 * exception details including code context and stack trace. Never expose
 * this in production — APP_DEBUG=false shows a generic 500 page instead.
 *
 * Example:
 *   // Registered by app::run() when APP_DEBUG=true:
 *   set_exception_handler(fn(\Throwable $e) => error_page::render($e));
 *
 * Testing: Call render() directly with a test Throwable; output goes to stdout.
 *
 * #AI:class
 */
final class error_page {
    /**
     * Renders a full HTML error page to stdout with code context and stack trace. #AI:render
     *
     * WARNING: Exposes exception details, file paths, and source code. Only
     * call when APP_DEBUG=true — never in production.
     *
     * @param \Throwable $e The exception to render.
     */
    public static function render(\Throwable $e): void {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        $class   = get_class($e);
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file    = $e->getFile();
        $line    = $e->getLine();
        $trace   = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $context = self::code_context($file, $line);

        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Error — {$class}</title>
            <style>
                * { box-sizing: border-box; }
                body { font-family: monospace; background: #1e1e2e; color: #cdd6f4; margin: 0; padding: 2rem; }
                .header { background: #313244; border-left: 4px solid #f38ba8; padding: 1rem 1.5rem; border-radius: 6px; margin-bottom: 1.5rem; }
                .class  { color: #f38ba8; font-size: 1.1em; font-weight: bold; }
                .msg    { color: #cdd6f4; margin-top: .5rem; font-size: 1em; }
                .loc    { color: #a6e3a1; font-size: .85em; margin-top: .5rem; }
                pre     { background: #181825; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: .85em; line-height: 1.5; }
                .arrow  { color: #f38ba8; font-weight: bold; }
                h2      { color: #89b4fa; font-size: .9em; text-transform: uppercase; letter-spacing: 1px; margin-top: 2rem; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="class">{$class}</div>
                <div class="msg">{$message}</div>
                <div class="loc">{$file} : {$line}</div>
            </div>
            <h2>Code context</h2>
            <pre>{$context}</pre>
            <h2>Stack trace</h2>
            <pre>{$trace}</pre>
        </body>
        </html>
        HTML;
    }

    private static function code_context(string $file, int $line, int $context = 5): string {
        if (!is_file($file)) {
            return '';
        }
        $lines  = file($file) ?: [];
        $start  = max(0, $line - $context - 1);
        $end    = min(count($lines) - 1, $line + $context - 1);
        $output = '';

        for ($i = $start; $i <= $end; $i++) {
            $num     = $i + 1;
            $content = htmlspecialchars(rtrim($lines[$i] ?? ''), ENT_QUOTES, 'UTF-8');
            $arrow   = $num === $line ? '→' : ' ';
            $output .= sprintf("<span%s>%s %3d │ %s</span>\n",
                $num === $line ? ' class="arrow"' : '',
                $arrow,
                $num,
                $content,
            );
        }

        return $output;
    }
}

#AI:class
#AI symbol: skim\dev\error_page
#AI source_path: src/dev/error_page.php
#AI title: error_page
#AI description: Developer-friendly HTML error page rendered when APP_DEBUG=true, showing exception details, code context, and stack trace.
#AI role: debug error renderer
#AI layer: dev
#AI badges: [dev; debug; error-page; html]
#AI intro: `error_page` renders a styled HTML error page with exception class, message, file location, surrounding code context, and full stack trace. It is only active when APP_DEBUG=true; production uses a generic 500 page.
#AI lifecycle: called by exception handler registered in app::run(), outputs directly to stdout
#AI fallback: none — always renders full details when called
#AI test_seam: call render() directly with a test Throwable
#AI invariants: [only called when APP_DEBUG=true; outputs HTTP 500 status; all output is HTML-escaped]
#AI core_behaviors: [Sets HTTP 500 status code; Renders exception class, message, file, and line; Shows ±5 lines of code context around the error; Displays full stack trace]
#AI warnings: [Exposes exception details, file paths, and source code — never call in production]
#AI owns: none — stateless
#AI entry_points: [render]
#AI config_reads: []
#AI non_goals: [Does not log errors; Does not handle production error pages; Does not format JSON error responses]
#AI side_effects: [sets HTTP response code to 500; writes HTML to stdout]
#AI flow: render(Throwable) -> http_response_code(500) -> code_context() -> echo HTML
#AI lifecycle_steps: [render(); -> set 500 status; -> extract exception details; -> code_context(); -> echo HTML template]
#AI section_order: [Rendering; Architecture]
#AI architectural_notes: Registered as exception handler by app::run() only when APP_DEBUG=true.

#AI:render
#AI group: Rendering
#AI frequency: low
#AI signature: public static function render(\Throwable $e): void
#AI contract: Renders a full HTML error page to stdout with exception class, message, file location, ±5 lines of code context, and full stack trace. Sets HTTP 500 status.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception to render.}]
#AI warnings: [Exposes internal file paths and source code — only safe when APP_DEBUG=true]
#AI side_effects: [sets HTTP 500 status; writes HTML to stdout]
