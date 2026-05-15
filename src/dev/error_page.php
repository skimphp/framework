<?php declare(strict_types=1);

namespace skim\dev;

// Developer-friendly error page rendered when APP_DEBUG=true.
// Active only when set_exception_handler is invoked from app::run().
// In production (APP_DEBUG=false) the handler logs + shows a generic 500 page.
final class error_page {
    /**
     * @ai-contract renders a full HTML error page to stdout and exits
     * @ai-contract shows: exception class, message, file, line, code context, stack trace
     * @ai-contract only called when APP_DEBUG=true — never expose internals in production
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
