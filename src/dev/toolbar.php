<?php declare(strict_types=1);

namespace skim\dev;

use skim\core\request;

// Debug toolbar — appended before </body> for text/html responses.
// Only rendered when APP_DEBUG=true AND request is not JSON/AJAX.
// Toolbar middleware (toolbar_middleware) injects this; it's not called directly.
final class toolbar {
    /**
     * @ai-contract generates the debug toolbar HTML string
     * @ai-contract returns empty string when APP_DEBUG=false (should not be called, but safe)
     */
    public static function render(request $req): string {
        if (!\skim\core\config::get('app.debug', false)) {
            return '';
        }

        $summary = profiler::summary();
        $events  = profiler::events();

        $db_count    = $summary['db']['count'];
        $db_ms       = $summary['db']['ms'];
        $cache_hits  = $summary['cache']['hits'];
        $cache_miss  = $summary['cache']['misses'];
        $view_count  = $summary['views'];
        $peak_mem    = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $method      = htmlspecialchars($req->method(), ENT_QUOTES, 'UTF-8');
        $path        = htmlspecialchars($req->path(), ENT_QUOTES, 'UTF-8');

        $db_rows  = array_filter($events, fn($e) => $e['type'] === 'db');
        $db_html  = self::build_db_panel($db_rows);
        $db_warn  = $db_count > 20 ? ' warn' : '';

        return <<<HTML
        <style>
        #skim-toolbar{position:fixed;bottom:0;left:0;right:0;background:#1e1e2e;color:#cdd6f4;font:12px/1.4 monospace;z-index:999999;border-top:2px solid #313244}
        #skim-toolbar .bar{display:flex;gap:16px;padding:4px 12px;align-items:center}
        #skim-toolbar .badge{padding:2px 6px;border-radius:3px;background:#313244;color:#cdd6f4;cursor:pointer}
        #skim-toolbar .badge.warn{background:#f38ba8;color:#1e1e2e}
        #skim-toolbar .panel{display:none;padding:12px;background:#181825;max-height:300px;overflow:auto}
        #skim-toolbar .panel pre{margin:0;white-space:pre-wrap;word-break:break-all;font-size:11px}
        </style>
        <div id="skim-toolbar">
            <div class="bar">
                <span style="color:#89b4fa;font-weight:bold">SKIM</span>
                <span class="badge">{$method} {$path}</span>
                <span class="badge{$db_warn}"
                      onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'">
                    🗄 {$db_count} queries ({$db_ms}ms)
                </span>
                <div class="panel">
                    {$db_html}
                </div>
                <span class="badge">💾 cache {$cache_hits}h/{$cache_miss}m</span>
                <span class="badge">🖼 {$view_count} views</span>
                <span class="badge">📦 {$peak_mem}MB peak</span>
            </div>
        </div>
        HTML;
    }

    private static function build_db_panel(array $events): string {
        if ($events === []) {
            return '<em>No queries</em>';
        }
        $html = '<table style="width:100%;border-collapse:collapse">';
        $html .= '<tr><th style="text-align:left;padding:4px">ms</th><th style="text-align:left;padding:4px">SQL</th></tr>';
        foreach ($events as $e) {
            $sql  = htmlspecialchars($e['sql'], ENT_QUOTES, 'UTF-8');
            $slow = $e['ms'] > 100 ? 'color:#f38ba8' : '';
            $html .= "<tr><td style='padding:2px 4px;{$slow}'>{$e['ms']}</td><td style='padding:2px 4px'><pre>{$sql}</pre></td></tr>";
        }
        return $html . '</table>';
    }
}
