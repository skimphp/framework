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

		// ── query stats ──────────────────────────────────────────────────────
		$db_count   = $summary['db']['count'];
		$db_ms      = $summary['db']['ms'];
		$db_warn    = $db_count > 20 ? ' warn-tab' : '';
		$db_rows    = array_filter($events, fn($e) => $e['type'] === 'db');
		$db_html    = self::build_db_rows($db_rows);

		// ── cache stats ───────────────────────────────────────────────────────
		$cache_hits  = $summary['cache']['hits'];
		$cache_miss  = $summary['cache']['misses'];
		$cache_total = $cache_hits + $cache_miss;
		$cache_ratio = $cache_total > 0
			? round($cache_hits / $cache_total * 100, 1) . '%'
			: '—';
		$cache_tab_label = "{$cache_hits}h/{$cache_miss}m";
		$cache_rows  = array_filter($events, fn($e) => $e['type'] === 'cache');
		$cache_kv    = self::build_cache_rows($cache_rows);
		$cache_driver = '';
		foreach ($cache_rows as $e) {
			if (!empty($e['driver'])) { $cache_driver = $e['driver']; break; }
		}
		if ($cache_driver === '') {
			$cache_driver = \skim\core\config::get('cache.driver', '—');
		}

		// ── view stats ────────────────────────────────────────────────────────
		$view_count  = $summary['views'];
		$view_rows   = array_filter($events, fn($e) => $e['type'] === 'view');
		$view_html   = self::build_view_rows($view_rows);

		// ── total request time ────────────────────────────────────────────────
		$total_ms = isset($_SERVER['REQUEST_TIME_FLOAT'])
			? round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1)
			: $db_ms;

		// ── timeline ──────────────────────────────────────────────────────────
		$timeline_html = self::build_timeline($summary, $total_ms);

		// ── log ───────────────────────────────────────────────────────────────
		$log_count = $summary['logs'];
		$log_html  = self::build_log_rows($events);

		// ── memory / php ──────────────────────────────────────────────────────
		$peak_mem = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
		$ms_class = $total_ms > 200 ? 'warn' : ($total_ms > 100 ? '' : 'ok');
		$php_ver  = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

		// ── request basics ────────────────────────────────────────────────────
		$method = htmlspecialchars($req->method(),    ENT_QUOTES, 'UTF-8');
		$path   = htmlspecialchars($req->path(),      ENT_QUOTES, 'UTF-8');

		// ── request tab data ──────────────────────────────────────────────────
		$req_html = self::build_request_panel($req);

		return <<<HTML
        <style>
        :root{--tb-bg:#0f1117;--tb-surface:#161b22;--tb-border:rgba(255,255,255,0.08);--tb-text:#e2e8f0;--tb-muted:#64748b;--tb-accent:#3b82f6;--tb-warn:#f59e0b;--tb-danger:#ef4444;--tb-success:#10b981;--tb-active-tab:#1e2535;--tb-height:36px;--tb-panel-h:280px}
        #skim-tb{position:fixed;bottom:0;left:0;right:0;background:var(--tb-bg);border-top:1px solid var(--tb-border);border-radius:8px 8px 0 0;overflow:hidden;user-select:none;z-index:999999;font:12px/1.4 'JetBrains Mono','Fira Code',ui-monospace,monospace}
        #skim-tb .bar{display:flex;align-items:stretch;height:var(--tb-height);border-bottom:1px solid var(--tb-border);overflow:hidden}
        #skim-tb .brand{display:flex;align-items:center;padding:0 14px;border-right:1px solid var(--tb-border);gap:7px;flex-shrink:0}
        #skim-tb .brand-dot{width:6px;height:6px;border-radius:50%;background:var(--tb-accent)}
        #skim-tb .brand-name{font-size:11px;font-weight:700;letter-spacing:.12em;color:var(--tb-text);text-transform:uppercase}
        #skim-tb .route{display:flex;align-items:center;padding:0 12px;gap:6px;border-right:1px solid var(--tb-border);flex-shrink:0}
        #skim-tb .http-method{font-size:10px;font-weight:700;letter-spacing:.08em;background:rgba(59,130,246,.15);color:var(--tb-accent);padding:2px 6px;border-radius:3px;border:1px solid rgba(59,130,246,.3)}
        #skim-tb .route-path{font-size:11px;color:var(--tb-text);opacity:.7;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        #skim-tb .tabs{display:flex;align-items:stretch;flex:1;overflow:hidden}
        #skim-tb .tab{display:flex;align-items:center;gap:6px;padding:0 13px;cursor:pointer;border-right:1px solid var(--tb-border);font-size:11px;color:var(--tb-muted);white-space:nowrap;transition:background .12s,color .12s;position:relative}
        #skim-tb .tab:hover{background:rgba(255,255,255,.03);color:var(--tb-text)}
        #skim-tb .tab.active{background:var(--tb-active-tab);color:var(--tb-text)}
        #skim-tb .tab.active::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:var(--tb-accent)}
        #skim-tb .tab.active.warn-tab::after{background:var(--tb-warn)}
        #skim-tb .tab i{font-size:13px;opacity:.7}
        #skim-tb .tab-count{font-size:10px;font-weight:600;padding:1px 5px;border-radius:10px;background:rgba(255,255,255,.06);color:var(--tb-muted)}
        #skim-tb .tab.active .tab-count{background:rgba(59,130,246,.2);color:var(--tb-accent)}
        #skim-tb .tab.warn-tab .tab-count{background:rgba(245,158,11,.2);color:var(--tb-warn)}
        #skim-tb .stats{display:flex;align-items:center;margin-left:auto}
        #skim-tb .stat{display:flex;align-items:center;gap:5px;padding:0 12px;border-left:1px solid var(--tb-border);font-size:11px;color:var(--tb-muted);white-space:nowrap}
        #skim-tb .stat-val{color:var(--tb-text);font-variant-numeric:tabular-nums}
        #skim-tb .stat-val.warn{color:var(--tb-warn)}
        #skim-tb .stat-val.ok{color:var(--tb-success)}
        #skim-tb .tb-close{display:flex;align-items:center;padding:0 12px;border-left:1px solid var(--tb-border);color:var(--tb-muted);cursor:pointer;font-size:15px;transition:color .1s}
        #skim-tb .tb-close:hover{color:var(--tb-text)}
        #skim-tb .panel{display:none;height:var(--tb-panel-h);background:var(--tb-surface);border-top:1px solid var(--tb-border);overflow:hidden}
        #skim-tb .panel.open{display:flex;flex-direction:column}
        #skim-tb .panel-inner{flex:1;overflow-y:auto;overflow-x:hidden}
        #skim-tb .panel-inner::-webkit-scrollbar{width:4px}
        #skim-tb .panel-inner::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}
        #skim-tb .req-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--tb-border)}
        #skim-tb .req-section{background:var(--tb-surface);padding:10px 14px}
        #skim-tb .req-head{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--tb-muted);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.06)}
        #skim-tb .kv{display:flex;justify-content:space-between;align-items:baseline;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.03);gap:10px}
        #skim-tb .kv:last-child{border-bottom:none}
        #skim-tb .kv-k{font-size:11px;color:var(--tb-muted);flex-shrink:0}
        #skim-tb .kv-v{font-size:11px;color:var(--tb-text);text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px}
        #skim-tb .kv-v.ok{color:var(--tb-success)}
        #skim-tb .kv-v.warn{color:var(--tb-warn)}
        #skim-tb .kv-v.blue{color:#79c0ff}
        #skim-tb .kv-v.purple{color:#d2a8ff}
        #skim-tb .kv-v.muted{color:var(--tb-muted)}
        #skim-tb .param-table{width:100%;border-collapse:collapse;table-layout:fixed}
        #skim-tb .param-table td{padding:4px 0;font-size:11px;border-bottom:1px solid rgba(255,255,255,.03);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        #skim-tb .param-table td:first-child{color:var(--tb-muted);width:40%;padding-right:8px}
        #skim-tb .param-table td:last-child{color:var(--tb-text)}
        #skim-tb .empty-note{padding:8px 0;font-size:11px;color:var(--tb-muted);font-style:italic}
        #skim-tb .col-head{display:grid;grid-template-columns:48px 58px 1fr 76px;border-bottom:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.2)}
        #skim-tb .col-head span{padding:5px 10px;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--tb-muted)}
        #skim-tb .col-head span:first-child{text-align:right}
        #skim-tb .q-row{display:grid;grid-template-columns:48px 58px 1fr 76px;align-items:start;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .1s}
        #skim-tb .q-row:hover{background:rgba(255,255,255,.03)}
        #skim-tb .q-row.expanded{background:rgba(255,255,255,.04)}
        #skim-tb .qc{padding:8px 10px;font-size:11px;overflow:hidden}
        #skim-tb .qn{color:var(--tb-muted);text-align:right;padding-top:9px;font-variant-numeric:tabular-nums}
        #skim-tb .qm{padding-top:9px;font-variant-numeric:tabular-nums}
        #skim-tb .qm.fast{color:var(--tb-success)}
        #skim-tb .qm.med{color:var(--tb-warn)}
        #skim-tb .qm.slow{color:var(--tb-danger)}
        #skim-tb .qs{color:var(--tb-text);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-top:9px;opacity:.85}
        #skim-tb .qs .kw{color:#79c0ff}
        #skim-tb .qt{font-size:10px;text-align:right;padding-top:10px;color:var(--tb-muted);letter-spacing:.05em}
        #skim-tb .q-expand{display:none;grid-column:1/-1;padding:8px 12px 12px 116px;background:rgba(0,0,0,.3);border-bottom:1px solid rgba(255,255,255,.06)}
        #skim-tb .q-expand.open{display:block}
        #skim-tb .q-expand pre{margin:0;font-size:11px;white-space:pre-wrap;word-break:break-all;line-height:1.6;color:var(--tb-text);opacity:.9}
        #skim-tb .q-expand pre .kw{color:#79c0ff}
        #skim-tb .cache-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--tb-border);margin:12px 16px;border-radius:4px;overflow:hidden}
        #skim-tb .cache-cell{background:rgba(0,0,0,.3);padding:12px 14px}
        #skim-tb .cache-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--tb-muted);margin-bottom:4px}
        #skim-tb .cache-val{font-size:22px;font-weight:600;font-variant-numeric:tabular-nums}
        #skim-tb .cache-val.hit{color:var(--tb-success)}
        #skim-tb .cache-val.miss{color:var(--tb-warn)}
        #skim-tb .cache-val.ratio{color:var(--tb-accent)}
        #skim-tb .tl-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:11px}
        #skim-tb .tl-lbl{color:var(--tb-muted);width:100px;flex-shrink:0}
        #skim-tb .tl-wrap{flex:1;background:rgba(255,255,255,.04);border-radius:2px;height:6px}
        #skim-tb .tl-bar{height:6px;border-radius:2px}
        #skim-tb .tl-ms{color:var(--tb-muted);font-size:10px;width:48px;text-align:right;font-variant-numeric:tabular-nums}
        #skim-tb .log-row{display:flex;gap:10px;padding:6px 12px;font-size:11px;border-bottom:1px solid rgba(255,255,255,.03)}
        #skim-tb .log-time{color:var(--tb-muted);flex-shrink:0;font-variant-numeric:tabular-nums}
        #skim-tb .log-msg{color:var(--tb-text);opacity:.8}
        #skim-tb .log-tag{font-size:10px;padding:1px 6px;border-radius:3px;flex-shrink:0}
        #skim-tb .tag-db{background:rgba(59,130,246,.15);color:#79c0ff}
        #skim-tb .tag-cache{background:rgba(16,185,129,.15);color:var(--tb-success)}
        #skim-tb .tag-view{background:rgba(168,85,247,.15);color:#d2a8ff}
        #skim-tb .tag-warn{background:rgba(245,158,11,.15);color:var(--tb-warn)}
        #skim-tb .kv-section-head{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--tb-muted);padding:10px 0 6px;border-bottom:1px solid rgba(255,255,255,.06);margin-bottom:6px}
        #skim-tb .tb-resize{height:4px;cursor:ns-resize;background:transparent;transition:background .15s;flex-shrink:0}
        #skim-tb .tb-resize:hover,#skim-tb .tb-resize.dragging{background:var(--tb-accent)}
        </style>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
        <div id="skim-tb">
            <div class="tb-resize" id="skim-resize"></div>
            <div class="bar">
                <div class="brand">
                    <div class="brand-dot"></div>
                    <span class="brand-name">skim</span>
                </div>
                <div class="route">
                    <span class="http-method">{$method}</span>
                    <span class="route-path">{$path}</span>
                </div>
                <div class="tabs">
                    <div class="tab active" onclick="skimTab(this,'request')">
                        <i class="ti ti-server" aria-hidden="true"></i>
                        <span>request</span>
                    </div>
                    <div class="tab{$db_warn}" onclick="skimTab(this,'db')">
                        <i class="ti ti-database" aria-hidden="true"></i>
                        <span>queries</span>
                        <span class="tab-count">{$db_count}</span>
                    </div>
                    <div class="tab" onclick="skimTab(this,'cache')">
                        <i class="ti ti-bolt" aria-hidden="true"></i>
                        <span>cache</span>
                        <span class="tab-count">{$cache_tab_label}</span>
                    </div>
                    <div class="tab" onclick="skimTab(this,'timeline')">
                        <i class="ti ti-timeline" aria-hidden="true"></i>
                        <span>timeline</span>
                    </div>
                    <div class="tab" onclick="skimTab(this,'views')">
                        <i class="ti ti-layout" aria-hidden="true"></i>
                        <span>views</span>
                        <span class="tab-count">{$view_count}</span>
                    </div>
                    <div class="tab" onclick="skimTab(this,'log')">
                        <i class="ti ti-list" aria-hidden="true"></i>
                        <span>log</span>
                        <span class="tab-count">{$log_count}</span>
                    </div>
                </div>
                <div class="stats">
                    <div class="stat">
                        <span>time</span>
                        <span class="stat-val {$ms_class}">{$total_ms}ms</span>
                    </div>
                    <div class="stat">
                        <span>mem</span>
                        <span class="stat-val">{$peak_mem} MB</span>
                    </div>
                </div>
                <div class="tb-close" onclick="skimClose()" title="Toggle panel">
                    <i class="ti ti-chevron-down" aria-hidden="true"></i>
                </div>
            </div>

            <div class="panel open" id="skim-panel-request">
                <div class="panel-inner">
                    {$req_html}
                </div>
            </div>

            <div class="panel" id="skim-panel-db">
                <div class="col-head">
                    <span>#</span><span>ms</span><span>SQL</span><span style="text-align:right">type</span>
                </div>
                <div class="panel-inner">
                    {$db_html}
                </div>
            </div>

            <div class="panel" id="skim-panel-cache">
                <div class="panel-inner">
                    <div class="cache-grid">
                        <div class="cache-cell">
                            <div class="cache-lbl">hits</div>
                            <div class="cache-val hit">{$cache_hits}</div>
                        </div>
                        <div class="cache-cell">
                            <div class="cache-lbl">misses</div>
                            <div class="cache-val miss">{$cache_miss}</div>
                        </div>
                        <div class="cache-cell">
                            <div class="cache-lbl">hit ratio</div>
                            <div class="cache-val ratio">{$cache_ratio}</div>
                        </div>
                        <div class="cache-cell">
                            <div class="cache-lbl">driver</div>
                            <div class="cache-val" style="font-size:16px;color:var(--tb-text)">{$cache_driver}</div>
                        </div>
                    </div>
                    <div style="padding:0 16px">
                        {$cache_kv}
                    </div>
                </div>
            </div>

            <div class="panel" id="skim-panel-timeline">
                <div class="panel-inner">
                    <div style="padding:12px 16px">
                        {$timeline_html}
                    </div>
                </div>
            </div>

            <div class="panel" id="skim-panel-views">
                <div class="panel-inner">
                    <div style="padding:8px 0">
                        {$view_html}
                    </div>
                </div>
            </div>

            <div class="panel" id="skim-panel-log">
                <div class="panel-inner">
                    {$log_html}
                </div>
            </div>
        </div>
        <script>
        (function(){
            function skimTab(el,id){
                document.querySelectorAll('#skim-tb .tab').forEach(t=>t.classList.remove('active'));
                document.querySelectorAll('#skim-tb .panel').forEach(p=>p.classList.remove('open'));
                el.classList.add('active');
                var p=document.getElementById('skim-panel-'+id);
                if(p) p.classList.add('open');
            }
            var _open=true;
            function skimClose(){
                var ch=document.querySelector('#skim-tb .tb-close .ti');
                var panels=document.querySelectorAll('#skim-tb .panel');
                if(_open){
                    panels.forEach(p=>{if(p.classList.contains('open'))p.setAttribute('data-was-open','1');p.classList.remove('open');});
                    if(ch){ch.className='ti ti-chevron-up';}
                    _open=false;
                } else {
                    document.querySelectorAll('#skim-tb [data-was-open]').forEach(p=>{p.classList.add('open');p.removeAttribute('data-was-open');});
                    if(ch){ch.className='ti ti-chevron-down';}
                    _open=true;
                }
            }
            function skimToggleQ(i){
                var ex=document.getElementById('skim-ex-'+i);
                var row=ex.previousElementSibling;
                var wasOpen=ex.classList.contains('open');
                document.querySelectorAll('#skim-tb .q-expand.open').forEach(function(e){
                    e.classList.remove('open');
                    e.previousElementSibling.classList.remove('expanded');
                });
                if(!wasOpen){ex.classList.add('open');row.classList.add('expanded');}
            }
            var _tb=document.getElementById('skim-tb');
            var _rz=document.getElementById('skim-resize');
            var _rzY=0,_rzH=280;
            _rz.addEventListener('mousedown',function(e){
                if(!_open)return;
                _rzY=e.clientY;
                _rzH=parseInt(getComputedStyle(_tb).getPropertyValue('--tb-panel-h'))||280;
                _rz.classList.add('dragging');
                document.addEventListener('mousemove',_rzMove);
                document.addEventListener('mouseup',_rzUp);
                e.preventDefault();
            });
            function _rzMove(e){
                var d=_rzY-e.clientY;
                var h=Math.max(80,Math.min(window.innerHeight-60,_rzH+d));
                _tb.style.setProperty('--tb-panel-h',h+'px');
            }
            function _rzUp(){
                _rz.classList.remove('dragging');
                document.removeEventListener('mousemove',_rzMove);
                document.removeEventListener('mouseup',_rzUp);
            }
            window.skimTab=skimTab;
            window.skimClose=skimClose;
            window.skimToggleQ=skimToggleQ;
        })();
        </script>
        HTML;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Request panel — $_SERVER, headers, GET/POST/COOKIE
	// ─────────────────────────────────────────────────────────────────────────
	private static function build_request_panel(request $req): string {
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

		$scheme_class = $scheme === 'https' ? 'ok' : 'warn';
		$qs_out   = $qs !== '' ? $qs : '—';
		$qs_class = $qs !== '' ? '' : 'muted';

		// server / php
		$php_full   = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
		$sapi       = htmlspecialchars(PHP_SAPI, ENT_QUOTES, 'UTF-8');
		$software   = htmlspecialchars($s['SERVER_SOFTWARE'] ?? '—', ENT_QUOTES, 'UTF-8');
		$srv_name   = htmlspecialchars($s['SERVER_NAME']     ?? '—', ENT_QUOTES, 'UTF-8');
		$doc_root   = htmlspecialchars($s['DOCUMENT_ROOT']   ?? '—', ENT_QUOTES, 'UTF-8');
		$script     = htmlspecialchars(basename($s['SCRIPT_FILENAME'] ?? '—'), ENT_QUOTES, 'UTF-8');
		$mem_limit  = htmlspecialchars(ini_get('memory_limit')     ?: '—', ENT_QUOTES, 'UTF-8');
		$max_exec   = htmlspecialchars(ini_get('max_execution_time') ?: '—', ENT_QUOTES, 'UTF-8');
		$opcache    = function_exists('opcache_get_status') ? 'enabled' : 'disabled';
		$opcache_cl = $opcache === 'enabled' ? 'ok' : 'warn';

		// headers (HTTP_* keys)
		$raw_headers = [
			'accept'           => $s['HTTP_ACCEPT']           ?? null,
			'accept-encoding'  => $s['HTTP_ACCEPT_ENCODING']  ?? null,
			'accept-language'  => $s['HTTP_ACCEPT_LANGUAGE']  ?? null,
			'user-agent'       => $s['HTTP_USER_AGENT']        ?? null,
			'referer'          => $s['HTTP_REFERER']           ?? null,
			'content-type'     => $s['CONTENT_TYPE']           ?? $s['HTTP_CONTENT_TYPE'] ?? null,
			'authorization'    => isset($s['HTTP_AUTHORIZATION']) ? '••••••••' : null,
			'x-requested-with' => $s['HTTP_X_REQUESTED_WITH'] ?? null,
		];
		$header_rows = '';
		foreach ($raw_headers as $key => $val) {
			$v     = $val !== null ? htmlspecialchars($val, ENT_QUOTES, 'UTF-8') : null;
			$cls   = $v !== null ? '' : 'muted';
			$show  = $v !== null ? $v : '—';
			// truncate long values for display
			if (mb_strlen($show) > 40) {
				$show = mb_substr($show, 0, 37) . '…';
			}
			$header_rows .= "<div class=\"kv\"><span class=\"kv-k\">{$key}</span><span class=\"kv-v {$cls}\">{$show}</span></div>";
		}

		// GET params
		$get_html = '';
		if (!empty($_GET)) {
			$get_html .= '<table class="param-table">';
			foreach ($_GET as $k => $v) {
				$k = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
				$v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
				$get_html .= "<tr><td>{$k}</td><td>{$v}</td></tr>";
			}
			$get_html .= '</table>';
		} else {
			$get_html = '<div class="empty-note">no $_GET params</div>';
		}

		// POST params
		$post_html = '';
		if (!empty($_POST)) {
			$post_html .= '<table class="param-table">';
			foreach ($_POST as $k => $v) {
				$k = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
				$v = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
				$post_html .= "<tr><td>{$k}</td><td>{$v}</td></tr>";
			}
			$post_html .= '</table>';
		} else {
			$post_html = '<div class="empty-note">no $_POST params</div>';
		}

		// Cookies (mask values)
		$cookie_html = '';
		if (!empty($_COOKIE)) {
			$cookie_html .= '<table class="param-table">';
			foreach ($_COOKIE as $k => $v) {
				$k    = htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8');
				$tail = htmlspecialchars(mb_substr((string)$v, -4), ENT_QUOTES, 'UTF-8');
				$cookie_html .= "<tr><td>{$k}</td><td style=\"color:#d2a8ff\">••••{$tail}</td></tr>";
			}
			$cookie_html .= '</table>';
		} else {
			$cookie_html = '<div class="empty-note">no cookies</div>';
		}

		$max_exec_label = is_numeric($max_exec) ? "{$max_exec}s" : $max_exec;

		return <<<HTML
        <div class="req-grid">
            <div class="req-section">
                <div class="req-head">request</div>
                <div class="kv"><span class="kv-k">method</span><span class="kv-v blue">{$method}</span></div>
                <div class="kv"><span class="kv-k">uri</span><span class="kv-v">{$uri}</span></div>
                <div class="kv"><span class="kv-k">query string</span><span class="kv-v {$qs_class}">{$qs_out}</span></div>
                <div class="kv"><span class="kv-k">protocol</span><span class="kv-v">{$protocol}</span></div>
                <div class="kv"><span class="kv-k">scheme</span><span class="kv-v {$scheme_class}">{$scheme}</span></div>
                <div class="kv"><span class="kv-k">host</span><span class="kv-v">{$host}</span></div>
                <div class="kv"><span class="kv-k">port</span><span class="kv-v">{$port}</span></div>
                <div class="kv"><span class="kv-k">remote addr</span><span class="kv-v">{$remote}</span></div>
                <div class="kv"><span class="kv-k">forwarded for</span><span class="kv-v muted">{$fwd}</span></div>
            </div>
            <div class="req-section">
                <div class="req-head">server &amp; php</div>
                <div class="kv"><span class="kv-k">php version</span><span class="kv-v ok">{$php_full}</span></div>
                <div class="kv"><span class="kv-k">sapi</span><span class="kv-v">{$sapi}</span></div>
                <div class="kv"><span class="kv-k">server software</span><span class="kv-v">{$software}</span></div>
                <div class="kv"><span class="kv-k">server name</span><span class="kv-v">{$srv_name}</span></div>
                <div class="kv"><span class="kv-k">document root</span><span class="kv-v">{$doc_root}</span></div>
                <div class="kv"><span class="kv-k">script filename</span><span class="kv-v">{$script}</span></div>
                <div class="kv"><span class="kv-k">memory limit</span><span class="kv-v">{$mem_limit}</span></div>
                <div class="kv"><span class="kv-k">max exec time</span><span class="kv-v">{$max_exec_label}</span></div>
                <div class="kv"><span class="kv-k">opcache</span><span class="kv-v {$opcache_cl}">{$opcache}</span></div>
            </div>
            <div class="req-section">
                <div class="req-head">headers</div>
                {$header_rows}
            </div>
            <div class="req-section">
                <div class="req-head">get params</div>
                {$get_html}
                <div class="kv-section-head" style="margin-top:10px">post params</div>
                {$post_html}
                <div class="kv-section-head" style="margin-top:10px">cookies</div>
                {$cookie_html}
            </div>
        </div>
        HTML;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// DB query rows
	// ─────────────────────────────────────────────────────────────────────────
	private static function build_db_rows(array $events): string {
		if ($events === []) {
			return '<div style="padding:20px 14px;font-size:11px;color:#64748b;font-style:italic">No queries</div>';
		}

		$html = '';
		$i    = 0;

		foreach ($events as $e) {
			$i++;
			$sql      = htmlspecialchars($e['sql'], ENT_QUOTES, 'UTF-8');
			$ms       = (int)$e['ms'];
			$ms_class = $ms >= 100 ? 'slow' : ($ms >= 30 ? 'med' : 'fast');
			$first    = preg_split('/\s+/', ltrim($e['sql']), 2)[0] ?? '';
			$type     = htmlspecialchars(strtoupper($first) ?: 'SELECT', ENT_QUOTES, 'UTF-8');
			$type_style = match ($type) {
				'INSERT' => 'color:#d2a8ff',
				'UPDATE' => 'color:#f59e0b',
				'DELETE' => 'color:#ef4444',
				default  => '',
			};

			$html .= <<<ROW
            <div class="q-row" onclick="skimToggleQ({$i})">
                <div class="qc qn">{$i}</div>
                <div class="qc qm {$ms_class}">{$ms}ms</div>
                <div class="qc qs">{$sql}</div>
                <div class="qc qt" style="{$type_style}">{$type}</div>
            </div>
            <div class="q-expand" id="skim-ex-{$i}"><pre>{$sql}</pre></div>
            ROW;
		}

		return $html;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Cache key/value rows
	// ─────────────────────────────────────────────────────────────────────────
	private static function build_cache_rows(array $events): string {
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

	// ─────────────────────────────────────────────────────────────────────────
	// View rows
	// ─────────────────────────────────────────────────────────────────────────
	private static function build_view_rows(array $events): string {
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

	// ─────────────────────────────────────────────────────────────────────────
	// Timeline bars
	// ─────────────────────────────────────────────────────────────────────────
	private static function build_timeline(array $summary, float $total_ms): string {
		$db_ms   = (float)($summary['db']['ms']  ?? 0);
		$view_ms = (float)($summary['view_ms']   ?? 0);
		$other   = max(0.0, $total_ms - $db_ms - $view_ms);
		$total   = max(1.0, $total_ms);

		$phases = [
			'db queries'  => ['ms' => $db_ms,   'color' => '#f59e0b'],
			'view render' => ['ms' => $view_ms, 'color' => '#d2a8ff'],
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

		$total_display = round($total_ms, 1);
		$html .= <<<TOTAL
        <div class="tl-row" style="margin-top:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px">
            <span class="tl-lbl" style="color:var(--tb-text);font-weight:600">total</span>
            <div class="tl-wrap"><div class="tl-bar" style="width:100%;background:#3b82f6"></div></div>
            <span class="tl-ms" style="color:var(--tb-warn);font-weight:600">{$total_display}ms</span>
        </div>
        TOTAL;

		return $html;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Log rows (all profiler events in order)
	// ─────────────────────────────────────────────────────────────────────────
	private static function build_log_rows(array $events): string {
		if ($events === []) {
			return '<div style="padding:20px 14px;font-size:11px;color:#64748b;font-style:italic">No events logged</div>';
		}

		$html = '';
		foreach ($events as $e) {
			$type = $e['type'] ?? 'info';
			$tag_class = match ($type) {
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
                <span class="log-tag {$tag_class}">{$type}</span>
                <span class="log-msg">{$msg}</span>
            </div>
            ROW;
		}

		return $html;
	}
}