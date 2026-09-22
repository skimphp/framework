<?php declare(strict_types=1);

namespace Skim\Dev;

use Skim\Core\Request;

/**
 * Debug toolbar appended before </body> for text/html responses when APP_DEBUG=true. #AI:class
 *
 * Use only via toolbar_middleware — not called directly. Renders a fixed-bottom
 * panel with tabs for request info, DB queries, cache stats, timeline, views,
 * and log entries. Only rendered for non-JSON, non-AJAX HTML responses.
 *
 * Example:
 *   // Called by toolbar_middleware, not directly:
 *   $html = Toolbar::render($req);
 *   $response_body .= $html;
 *
 * Testing: Call render() with a mock request; requires profiler to be populated.
 *
 * #AI:class
 */
final class Toolbar {
	/**
	 * Generates the debug toolbar HTML string from profiler data. #AI:render
	 *
	 * Returns empty string when APP_DEBUG=false (safe guard). Delegates HTML
	 * generation to dev_view templates with shared CSS from dev_theme. Falls
	 * back to empty string if template rendering fails.
	 *
     * @param \Skim\Core\Request $req Current HTTP request for method/path display.
	 * @return string Complete toolbar HTML, or empty string when debug is off.
	 */
    public static function render(\Skim\Core\Request $req): string {
		if (!\Skim\Core\Config::get('app.debug', false)) {
			return '';
		}

		$summary = \Skim\Dev\Profiler::summary();
		$events  = \Skim\Dev\Profiler::events();

		$dbCount   = $summary['db']['count'];
		$dbMs      = $summary['db']['ms'];
		$dbWarn    = $dbCount > 20 ? ' warn-tab' : '';
		$dbRows    = array_filter($events, fn($e) => $e['type'] === 'db');
		$dbHtml    = self::buildDbRows($dbRows);

		$cacheHits  = $summary['cache']['hits'];
		$cacheMiss  = $summary['cache']['misses'];
		$cacheTotal = $cacheHits + $cacheMiss;
		$cacheRatio = $cacheTotal > 0
			? round($cacheHits / $cacheTotal * 100, 1) . '%'
			: '—';
		$cacheTabLabel = "{$cacheHits}h/{$cacheMiss}m";
		$cacheRows  = array_filter($events, fn($e) => $e['type'] === 'cache');
		$cacheKv    = self::buildCacheRows($cacheRows);
		$cacheDriver = '';
		foreach ($cacheRows as $e) {
			if (!empty($e['driver'])) { $cacheDriver = $e['driver']; break; }
		}
		if ($cacheDriver === '') {
			$cacheDriver = \Skim\Core\Config::get('cache.driver', '—');
		}

		$viewCount  = $summary['views'];
		$viewRows   = array_filter($events, fn($e) => $e['type'] === 'view');
		$viewHtml   = self::buildViewRows($viewRows);

		$totalMs = isset($_SERVER['REQUEST_TIME_FLOAT'])
			? round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1)
			: $dbMs;

		$timelineHtml = self::buildTimeline($summary, $totalMs);

		$logCount = $summary['logs'];
		$logHtml  = self::buildLogRows($events);

		$peakMem = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
		$msClass = $totalMs > 200 ? 'warn' : ($totalMs > 100 ? '' : 'ok');

		$method  = $req->method();
		$path    = $req->path();
		$reqHtml = self::buildRequestPanel($req);

		try {
			return \Skim\Dev\DevView::render('toolbar', [
				'method'          => $method,
				'path'            => $path,
				'db_count'        => $dbCount,
				'db_warn'         => $dbWarn,
				'db_html'         => $dbHtml,
				'cache_hits'      => $cacheHits,
				'cache_miss'      => $cacheMiss,
				'cache_ratio'     => $cacheRatio,
				'cache_tab_label' => $cacheTabLabel,
				'cache_driver'    => $cacheDriver,
				'cache_kv'        => $cacheKv,
				'view_count'      => $viewCount,
				'view_html'       => $viewHtml,
				'total_ms'        => $totalMs,
				'timeline_html'   => $timelineHtml,
				'log_count'       => $logCount,
				'log_html'        => $logHtml,
				'peak_mem'        => $peakMem,
				'ms_class'        => $msClass,
				'req_html'        => $reqHtml,
				'custom_panels'   => \Skim\Dev\Profiler::panels(),
			]);
		}
		catch (\Throwable) {
			return '';
		}
	}

	private static function buildRequestPanel(\Skim\Core\Request $req): string {
		$s = $_SERVER;

		$method   = htmlspecialchars($req->method(), ENT_QUOTES, 'UTF-8');
		$uri      = htmlspecialchars($s['REQUEST_URI']  ?? $req->path(), ENT_QUOTES, 'UTF-8');
		$qs       = htmlspecialchars($s['QUERY_STRING'] ?? '', ENT_QUOTES, 'UTF-8');
		$protocol = htmlspecialchars($s['SERVER_PROTOCOL'] ?? 'HTTP/1.1', ENT_QUOTES, 'UTF-8');
		$scheme   = (!empty($s['HTTPS']) && $s['HTTPS'] !== 'off') ? 'https' : 'http';
		$host     = htmlspecialchars($s['HTTP_HOST']   ?? $s['SERVER_NAME'] ?? '—', ENT_QUOTES, 'UTF-8');
		$port     = htmlspecialchars((string)($s['SERVER_PORT'] ?? '—'), ENT_QUOTES, 'UTF-8');
		$remote   = htmlspecialchars($s['REMOTE_ADDR'] ?? '—', ENT_QUOTES, 'UTF-8');
		$fwd      = htmlspecialchars($s['HTTP_X_FORWARDED_FOR'] ?? '—', ENT_QUOTES, 'UTF-8');

		$schemeClass = $scheme === 'https' ? 'ok' : 'warn';
		$qsOut   = $qs !== '' ? $qs : '—';
		$qsClass = $qs !== '' ? '' : 'muted';

		$phpFull   = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
		$sapi       = htmlspecialchars(PHP_SAPI, ENT_QUOTES, 'UTF-8');
		$software   = htmlspecialchars($s['SERVER_SOFTWARE'] ?? '—', ENT_QUOTES, 'UTF-8');
		$srvName   = htmlspecialchars($s['SERVER_NAME']     ?? '—', ENT_QUOTES, 'UTF-8');
		$docRoot   = htmlspecialchars($s['DOCUMENT_ROOT']   ?? '—', ENT_QUOTES, 'UTF-8');
		$script     = htmlspecialchars(basename($s['SCRIPT_FILENAME'] ?? '—'), ENT_QUOTES, 'UTF-8');
		$memLimit  = htmlspecialchars(ini_get('memory_limit')     ?: '—', ENT_QUOTES, 'UTF-8');
		$maxExec   = htmlspecialchars(ini_get('max_execution_time') ?: '—', ENT_QUOTES, 'UTF-8');
		$opcache    = function_exists('opcache_get_status') ? 'enabled' : 'disabled';
		$opcacheCl = $opcache === 'enabled' ? 'ok' : 'warn';

		$rawHeaders = [
			'accept'           => $s['HTTP_ACCEPT']           ?? null,
			'accept-encoding'  => $s['HTTP_ACCEPT_ENCODING']  ?? null,
			'accept-language'  => $s['HTTP_ACCEPT_LANGUAGE']  ?? null,
			'user-agent'       => $s['HTTP_USER_AGENT']        ?? null,
			'referer'          => $s['HTTP_REFERER']           ?? null,
			'content-type'     => $s['CONTENT_TYPE']           ?? $s['HTTP_CONTENT_TYPE'] ?? null,
			'authorization'    => isset($s['HTTP_AUTHORIZATION']) ? '••••••••' : null,
			'x-requested-with' => $s['HTTP_X_REQUESTED_WITH'] ?? null,
		];
		$headerRows = '';
		foreach ($rawHeaders as $key => $val) {
			$v     = $val !== null ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : null;
			$cls   = $v !== null ? '' : 'muted';
			$show  = $v !== null ? $v : '—';
			if (mb_strlen($show) > 40) {
				$show = mb_substr($show, 0, 37) . '…';
			}
			$headerRows .= "<div class=\"kv\"><span class=\"kv-k\">{$key}</span><span class=\"kv-v {$cls}\">{$show}</span></div>";
		}

		$getHtml = '';
		if (!empty($_GET)) {
			$getHtml .= '<table class="param-table">';
			foreach ($_GET as $k => $v) {
				$k = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
				$v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
				$getHtml .= "<tr><td>{$k}</td><td>{$v}</td></tr>";
			}
			$getHtml .= '</table>';
		} else {
			$getHtml = '<div class="empty-note">no $_GET params</div>';
		}

		$postHtml = '';
		if (!empty($_POST)) {
			$postHtml .= '<table class="param-table">';
			foreach ($_POST as $k => $v) {
				$k = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
				$v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
				$postHtml .= "<tr><td>{$k}</td><td>{$v}</td></tr>";
			}
			$postHtml .= '</table>';
		} else {
			$postHtml = '<div class="empty-note">no $_POST params</div>';
		}

		$cookieHtml = '';
		if (!empty($_COOKIE)) {
			$cookieHtml .= '<table class="param-table">';
			foreach ($_COOKIE as $k => $v) {
				$k    = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
				$tail = htmlspecialchars(mb_substr((string)$v, -4), ENT_QUOTES, 'UTF-8');
				$cookieHtml .= "<tr><td>{$k}</td><td style=\"color:#d2a8ff\">••••{$tail}</td></tr>";
			}
			$cookieHtml .= '</table>';
		} else {
			$cookieHtml = '<div class="empty-note">no cookies</div>';
		}

		$maxExecLabel = is_numeric($maxExec) ? "{$maxExec}s" : $maxExec;

		return <<<HTML
        <div class="req-grid">
            <div class="req-section">
                <div class="req-head">request</div>
                <div class="kv"><span class="kv-k">method</span><span class="kv-v blue">{$method}</span></div>
                <div class="kv"><span class="kv-k">uri</span><span class="kv-v">{$uri}</span></div>
                <div class="kv"><span class="kv-k">query string</span><span class="kv-v {$qsClass}">{$qsOut}</span></div>
                <div class="kv"><span class="kv-k">protocol</span><span class="kv-v">{$protocol}</span></div>
                <div class="kv"><span class="kv-k">scheme</span><span class="kv-v {$schemeClass}">{$scheme}</span></div>
                <div class="kv"><span class="kv-k">host</span><span class="kv-v">{$host}</span></div>
                <div class="kv"><span class="kv-k">port</span><span class="kv-v">{$port}</span></div>
                <div class="kv"><span class="kv-k">remote addr</span><span class="kv-v">{$remote}</span></div>
                <div class="kv"><span class="kv-k">forwarded for</span><span class="kv-v muted">{$fwd}</span></div>
            </div>
            <div class="req-section">
                <div class="req-head">server &amp; php</div>
                <div class="kv"><span class="kv-k">php version</span><span class="kv-v ok">{$phpFull}</span></div>
                <div class="kv"><span class="kv-k">sapi</span><span class="kv-v">{$sapi}</span></div>
                <div class="kv"><span class="kv-k">server software</span><span class="kv-v">{$software}</span></div>
                <div class="kv"><span class="kv-k">server name</span><span class="kv-v">{$srvName}</span></div>
                <div class="kv"><span class="kv-k">document root</span><span class="kv-v">{$docRoot}</span></div>
                <div class="kv"><span class="kv-k">script filename</span><span class="kv-v">{$script}</span></div>
                <div class="kv"><span class="kv-k">memory limit</span><span class="kv-v">{$memLimit}</span></div>
                <div class="kv"><span class="kv-k">max exec time</span><span class="kv-v">{$maxExecLabel}</span></div>
                <div class="kv"><span class="kv-k">opcache</span><span class="kv-v {$opcacheCl}">{$opcache}</span></div>
            </div>
            <div class="req-section">
                <div class="req-head">headers</div>
                {$headerRows}
            </div>
            <div class="req-section">
                <div class="req-head">get params</div>
                {$getHtml}
                <div class="kv-section-head" style="margin-top:10px">post params</div>
                {$postHtml}
                <div class="kv-section-head" style="margin-top:10px">cookies</div>
                {$cookieHtml}
            </div>
        </div>
        HTML;
	}

	private static function buildDbRows(array $events): string {
		if ($events === []) {
			return '<div style="padding:20px 14px;font-size:11px;color:#64748b;font-style:italic">No queries</div>';
		}

		$html = '';
		$i    = 0;

		foreach ($events as $e) {
			$i++;
			$sql      = htmlspecialchars($e['sql'], ENT_QUOTES, 'UTF-8');
			$ms       = (int)$e['ms'];
			$msClass = $ms >= 100 ? 'slow' : ($ms >= 30 ? 'med' : 'fast');
			$first    = preg_split('/\s+/', ltrim($e['sql']), 2)[0] ?? '';
			$type     = htmlspecialchars(strtoupper($first) ?: 'SELECT', ENT_QUOTES, 'UTF-8');
			$typeStyle = match ($type) {
				'INSERT' => 'color:#d2a8ff',
				'UPDATE' => 'color:#f59e0b',
				'DELETE' => 'color:#ef4444',
				default  => '',
			};

			$html .= <<<ROW
            <div class="q-row" onclick="skimToggleQ({$i})">
                <div class="qc qn">{$i}</div>
                <div class="qc qm {$msClass}">{$ms}ms</div>
                <div class="qc qs">{$sql}</div>
                <div class="qc qt" style="{$typeStyle}">{$type}</div>
            </div>
            <div class="q-expand" id="skim-ex-{$i}"><pre>{$sql}</pre></div>
            ROW;
		}

		return $html;
	}

	private static function buildCacheRows(array $events): string {
		if ($events === []) {
			return '<div style="padding:8px 0;font-size:11px;color:#64748b;font-style:italic">No cache events</div>';
		}

		$html = '';

		foreach ($events as $e) {
			$key     = htmlspecialchars($e['key'] ?? '—', ENT_QUOTES, 'UTF-8');
			$hit     = !empty($e['hit']);
			$label   = $hit ? 'HIT' : 'MISS';
			$cls     = $hit ? 'ok'  : 'warn';
			$html   .= "<div class=\"kv\"><span class=\"kv-k\">{$key}</span><span class=\"kv-v {$cls}\">{$label}</span></div>";
		}

		return $html;
	}

	private static function buildViewRows(array $events): string {
		if ($events === []) {
			return '<div style="padding:20px 14px;font-size:11px;color:#64748b;font-style:italic">No views rendered</div>';
		}

		$html = '';

		foreach ($events as $e) {
			$frag = !empty($e['fragment']) ? '#' . htmlspecialchars($e['fragment'], ENT_QUOTES, 'UTF-8') : '';
			$name = htmlspecialchars($e['template'] ?? '—', ENT_QUOTES, 'UTF-8') . $frag;
			$ms   = round((float)($e['ms'] ?? 0), 1);
			$cls  = $ms > 50 ? 'warn' : 'ok';
			$html .= "<div class=\"kv\" style=\"padding:5px 14px\"><span class=\"kv-k\">{$name}</span><span class=\"kv-v {$cls}\">{$ms}ms</span></div>";
		}

		return $html;
	}

	private static function buildTimeline(array $summary, float $totalMs): string {
		$dbMs   = (float)($summary['db']['ms']  ?? 0);
		$viewMs = (float)($summary['view_ms']   ?? 0);
		$other   = max(0.0, $totalMs - $dbMs - $viewMs);
		$total   = max(1.0, $totalMs);

		$phases = [
			'db queries'  => ['ms' => $dbMs,   'color' => '#f59e0b'],
			'view render' => ['ms' => $viewMs, 'color' => '#d2a8ff'],
			'other'       => ['ms' => $other,   'color' => '#64748b'],
		];

		$html = '';
		foreach ($phases as $label => $data) {
			$ms  = round($data['ms'], 1);
			$pct = min(100, round($ms / $total * 100));
			$col = $data['color'];
			$lbl = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
			$html .= <<<ROW
            <div class="tl-row">
                <span class="tl-lbl">{$lbl}</span>
                <div class="tl-wrap"><div class="tl-bar" style="width:{$pct}%;background:{$col}"></div></div>
                <span class="tl-ms">{$ms}ms</span>
            </div>
            ROW;
		}

		$totalDisplay = round($totalMs, 1);
		$html .= <<<TOTAL
        <div class="tl-row" style="margin-top:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px">
            <span class="tl-lbl" style="color:var(--tb-text);font-weight:600">total</span>
            <div class="tl-wrap"><div class="tl-bar" style="width:100%;background:#3b82f6"></div></div>
            <span class="tl-ms" style="color:var(--tb-warn);font-weight:600">{$totalDisplay}ms</span>
        </div>
        TOTAL;

		return $html;
	}

	private static function buildLogRows(array $events): string {
		if ($events === []) {
			return '<div style="padding:20px 14px;font-size:11px;color:#64748b;font-style:italic">No events logged</div>';
		}

		$html = '';
		foreach ($events as $e) {
			$type = $e['type'] ?? 'info';
			$tagClass = match ($type) {
				'db'    => 'tag-db',
				'cache' => 'tag-cache',
				'view'  => 'tag-view',
				default => 'tag-warn',
			};
			$t   = round((float)($e['ms'] ?? 0));
			$msg = htmlspecialchars($e['sql'] ?? $e['key'] ?? $e['template'] ?? $e['message'] ?? '—', ENT_QUOTES, 'UTF-8');
			$html .= <<<ROW
            <div class="log-row">
                <span class="log-time">{$t}ms</span>
                <span class="log-tag {$tagClass}">{$type}</span>
                <span class="log-msg">{$msg}</span>
            </div>
            ROW;
		}

		return $html;
	}
}

#AI:class
#AI symbol: Skim\Dev\Toolbar
#AI source_path: src/Dev/Toolbar.php
#AI title: toolbar
#AI description: Debug toolbar rendered before </body> for HTML responses when APP_DEBUG=true, showing queries, cache, timeline, views, and log.
#AI role: debug toolbar renderer
#AI layer: dev
#AI badges: [dev; debug; toolbar; html; profiler]
#AI intro: `toolbar` generates a fixed-bottom debug panel with tabbed views for request info, DB queries, cache statistics, timeline breakdown, rendered views, and log entries. It reads all data from Profiler::summary() and Profiler::events().
#AI lifecycle: called by ToolbarMiddleware after controller returns, before response is sent
#AI fallback: returns empty string when APP_DEBUG=false
#AI test_seam: call render() with a mock request after populating profiler
#AI invariants: [returns empty string when debug is off; all output is HTML-escaped; reads from profiler static state]
#AI core_behaviors: [Renders tabbed debug panel with request, queries, cache, timeline, views, and log tabs; Shows query count warning when > 20 queries; Color-codes query timing (fast/medium/slow); Displays cache hit/miss ratio; Shows timeline breakdown of db/views/other time]
#AI owns: none — reads from profiler
#AI entry_points: [render]
#AI config_reads: [app.debug; cache.driver]
#AI non_goals: [Does not collect events (profiler does); Does not inject itself (ToolbarMiddleware does); Does not render for JSON/AJAX responses]
#AI side_effects: [generates large HTML string with inline CSS and JavaScript]
#AI flow: render(req) -> config debug check -> Profiler::summary() + events() -> build_*_rows() -> HTML template
#AI lifecycle_steps: [render(); -> check app.debug; -> Profiler::summary(); -> Profiler::events(); -> build panels; -> return HTML]
#AI section_order: [Rendering; Architecture]
#AI architectural_notes: Called by ToolbarMiddleware, not directly. The toolbar includes inline CSS and JavaScript for self-contained rendering.

#AI:render
#AI group: Rendering
#AI frequency: medium
#AI signature: public static function render(Request $req): string
#AI contract: Generates the complete debug toolbar HTML string from profiler data. Returns empty string when APP_DEBUG=false. Builds tabbed panels for request info, DB queries, cache stats, timeline, views, and log entries.
#AI param_details: [{name: $req | type: request | required: true | desc: Current HTTP request for method/path display in the toolbar header.}]
#AI return_detail: {type: string | desc: Complete toolbar HTML with inline CSS and JS, or empty string when debug is off.}
