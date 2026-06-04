<?php
/**
 * Error page template — full-page developer error display with tabs.
 *
 * @var string $class       Exception class name
 * @var string $message     Exception message
 * @var string $file        Error file path
 * @var int    $line        Error line number
 * @var string $php_version PHP version string
 * @var array  $code_lines  Code context lines [{num, code, hl}]
 * @var int    $line_start  First visible line number
 * @var int    $line_end    Last visible line number
 * @var array  $frames      Parsed stack frames
 * @var array  $env_server  Server/PHP environment items
 * @var array  $env_vars    Environment variable items
 * @var array  $request     Request sections
 * @var array  $solutions   Suggested solutions
 * @var string $trace_text  Raw trace string for copy
 */

use skim\dev\dev_theme;
use skim\dev\dev_view;

$this->layout('base');

$frame_count  = count($frames);
$noise_count  = count(array_filter($frames, fn($f) => $f['noise']));
$solution_count = count($solutions);

$tabs = [
    ['id' => 'code',      'label' => 'Code',        'icon' => 'code',    'active' => true],
    ['id' => 'trace',     'label' => 'Stack Trace',  'icon' => 'stack-2', 'pill' => (string)$frame_count],
    ['id' => 'env',       'label' => 'Environment',  'icon' => 'server'],
    ['id' => 'request',   'label' => 'Request',      'icon' => 'world'],
];
if ($solution_count > 0) {
    $tabs[] = ['id' => 'solutions', 'label' => 'Solutions', 'icon' => 'bulb', 'pill' => (string)$solution_count, 'pill_class' => 'ok'];
}

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<?php $this->start('page_css') ?>
.hero { position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 28px 32px 24px; margin-bottom: 14px; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--danger) 0%, var(--warn) 60%, transparent 100%); }
.hero::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 180px; background: radial-gradient(ellipse 60% 100% at 20% 0%, rgba(241,76,76,.06), transparent); pointer-events: none; }
.hero-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
.badge-err { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; background: var(--danger-dim); color: var(--danger); border: 1px solid rgba(241,76,76,.25); }
.badge-err i { font-size: 14px; }
.hero-class-name { font-size: 15px; font-weight: 600; color: var(--text); }
.hero-msg { font-size: 22px; font-weight: 600; color: #f0f4ff; line-height: 1.45; margin-bottom: 18px; word-break: break-word; letter-spacing: -.01em; }
.hero-msg code { font-family: inherit; font-size: .9em; }
.hero-loc { display: inline-flex; align-items: center; gap: 9px; padding: 8px 14px; background: rgba(0,0,0,.3); border: 1px solid var(--border); border-radius: 6px; font-size: 13px; color: var(--muted2); text-decoration: none; cursor: pointer; transition: all .15s; margin-bottom: 20px; }
.hero-loc:hover { background: rgba(79,142,247,.08); border-color: rgba(79,142,247,.3); color: var(--text); }
.hero-loc i { color: var(--muted); font-size: 16px; }
.hero-loc .path { color: #79c0ff; font-size: 13px; }
.hero-loc .colon { color: var(--muted); }
.hero-loc .line { color: var(--warn); font-size: 13px; }
.actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 18px; border-top: 1px solid var(--border); }
.code-file-link { color: #79c0ff; cursor: pointer; display: flex; align-items: center; gap: 7px; font-size: 13px; transition: color .12s; }
.code-file-link i { font-size: 15px; }
.code-file-link:hover { color: var(--text); }
.code-err-at { display: flex; align-items: center; gap: 6px; font-size: 12px; }
.code-err-at .line-num { color: var(--danger); font-weight: 600; }
.trace-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; gap: 12px; flex-wrap: wrap; }
.trace-info { font-size: 13px; color: var(--muted2); }
.trace-info strong { color: var(--text); }
.trace-ctrl { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.toggle-label { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 5px; font-size: 12px; color: var(--muted2); cursor: pointer; background: rgba(255,255,255,.03); border: 1px solid var(--border); transition: all .12s; user-select: none; }
.toggle-label:hover { color: var(--text); border-color: var(--border2); }
.toggle-label input { accent-color: var(--accent); width: 13px; height: 13px; }
.search-wrap { position: relative; margin-bottom: 14px; }
.search-wrap i.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 16px; pointer-events: none; }
.search-wrap input { width: 100%; padding: 9px 60px 9px 38px; background: rgba(0,0,0,.3); border: 1px solid var(--border); border-radius: 6px; color: var(--text); font: inherit; font-size: 13px; outline: none; transition: border-color .13s; }
.search-wrap input:focus { border-color: var(--accent); }
.search-wrap input::placeholder { color: var(--muted); }
.search-meta { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--muted); }
.frames-list { display: flex; flex-direction: column; gap: 5px; }
.frame { background: rgba(0,0,0,.2); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; transition: border-color .13s; }
.frame:hover { border-color: var(--border2); }
.frame.noise { opacity: .5; }
.frame.noise:hover { opacity: .75; }
.frame.open { border-color: rgba(79,142,247,.25); }
.frame.open .frame-head { background: rgba(79,142,247,.07); }
.frame-head { display: grid; grid-template-columns: 44px 1fr auto; gap: 12px; align-items: center; padding: 10px 14px; cursor: pointer; transition: background .1s; }
.frame-head:hover { background: rgba(255,255,255,.03); }
.frame-idx { font-size: 12px; color: var(--muted); text-align: right; font-variant-numeric: tabular-nums; font-weight: 500; }
.frame.open .frame-idx { color: var(--accent); }
.frame-call { font-size: 13px; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.frame-tag { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 1px 6px; border-radius: 3px; background: rgba(100,116,139,.12); color: var(--muted); margin-right: 7px; vertical-align: middle; }
.frame-loc { display: flex; align-items: center; gap: 4px; font-size: 12px; color: var(--muted); white-space: nowrap; flex-shrink: 0; }
.frame-loc .path { color: #79c0ff; }
.frame-loc .colon { color: var(--muted); }
.frame-loc .line { color: var(--warn); }
.frame-body { display: none; border-top: 1px solid var(--border); background: var(--code-bg); }
.frame.open .frame-body { display: block; }
.frame-args { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.04); }
.frame-args-head { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 8px; }
.arg-row { display: grid; grid-template-columns: 28px 1fr; gap: 12px; padding: 3px 0; font-size: 13px; }
.arg-idx { color: var(--muted); text-align: right; }
.arg-val { color: var(--text); word-break: break-all; }
.arg-val.type   { color: var(--s-num); }
.arg-val.string { color: var(--s-str); }
.arg-val.number { color: var(--s-num); }
.arg-val.bool   { color: var(--s-kw); }
.arg-val.null   { color: var(--muted); font-style: italic; }
.arg-val.object { color: var(--s-fn); }
.arg-val.array  { color: var(--s-cls); }
.solution { background: linear-gradient(160deg, rgba(39,192,122,.07), rgba(39,192,122,.02)); border: 1px solid rgba(39,192,122,.2); border-radius: 8px; padding: 18px 20px; margin-bottom: 12px; }
.solution-head { display: flex; align-items: center; gap: 9px; font-size: 14px; font-weight: 600; color: var(--ok); margin-bottom: 10px; }
.solution-head i { font-size: 18px; }
.solution-body { font-size: 13px; color: var(--text); line-height: 1.75; }
.solution-body code { background: rgba(0,0,0,.4); padding: 2px 7px; border-radius: 3px; color: var(--s-cls); font-family: inherit; }
.solution-body ol { margin: 8px 0 8px 20px; line-height: 2; }
.methods-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 6px; margin-top: 10px; }
.method-chip { padding: 4px 10px; border-radius: 4px; font-size: 12px; background: rgba(255,255,255,.04); border: 1px solid var(--border); color: var(--muted2); text-align: center; }
.method-chip.similar { background: var(--ok-dim); border-color: rgba(39,192,122,.25); color: var(--ok); }
<?php $this->end() ?>


<?php $this->start('content') ?>

<!-- HERO -->
<div class="hero">
    <div class="hero-meta">
        <span class="badge-err"><i class="ti ti-alert-triangle"></i> Error</span>
        <span class="hero-class-name"><?= $e($class) ?></span>
        <span class="chip"><i class="ti ti-brand-php"></i> PHP <?= $e($php_version) ?></span>
        <span class="chip ok"><i class="ti ti-bug"></i> debug mode</span>
    </div>

    <div class="hero-msg"><?= $e($message) ?></div>

    <a class="hero-loc" href="phpstorm://open?file=<?= $e($file) ?>&line=<?= (int)$line ?>" title="Open in IDE">
        <i class="ti ti-file-code"></i>
        <span class="path"><?= $e(basename(str_replace('\\', '/', $file))) ?></span>
        <span class="colon">:</span>
        <span class="line"><?= (int)$line ?></span>
    </a>

    <div class="actions">
        <a class="btn primary" href="phpstorm://open?file=<?= $e($file) ?>&line=<?= (int)$line ?>">
            <i class="ti ti-brand-phpstorm"></i> Open in IDE
        </a>
        <button class="btn" onclick="copyTrace()">
            <i class="ti ti-copy"></i> Copy stack trace
        </button>
        <a class="btn warn" target="_blank"
           href="https://www.google.com/search?q=<?= $e(urlencode($message . ' php')) ?>">
            <i class="ti ti-brand-google"></i> Google
        </a>
        <a class="btn ok" target="_blank"
           href="https://chat.openai.com/?q=<?= $e(urlencode('PHP Error: ' . $class . ': ' . $message)) ?>">
            <i class="ti ti-sparkles"></i> Ask AI
        </a>
    </div>
</div>

<!-- TABS -->
<?= $this->include('components/tabs', ['tabs' => $tabs]) ?>

<!-- PANEL: CODE -->
<div class="panel open" id="panel-code">
    <div class="panel-inner">
        <?= $this->include('components/code_box', [
            'file_path'  => $file,
            'lines'      => $code_lines,
            'error_line' => $line,
            'line_start' => $line_start,
            'line_end'   => $line_end,
        ]) ?>
    </div>
</div>

<!-- PANEL: STACK TRACE -->
<div class="panel" id="panel-trace">
    <div class="panel-inner">

        <div class="search-wrap">
            <i class="ti ti-search search-icon"></i>
            <input type="text" id="trace-search" placeholder="Filter by file, class or method…" oninput="filterTrace(this.value)">
            <span class="search-meta" id="trace-search-meta"><?= $frame_count ?> of <?= $frame_count ?></span>
        </div>

        <div class="trace-toolbar">
            <div class="trace-info">
                <strong><?= $frame_count ?> frames</strong>&nbsp;·&nbsp;<span id="trace-visible-count"><?= $frame_count ?> visible</span>&nbsp;·&nbsp;<?= $noise_count ?> framework
            </div>
            <div class="trace-ctrl">
                <label class="toggle-label" title="Hide framework/vendor frames">
                    <input type="checkbox" id="hide-noise" onchange="toggleNoise(this.checked)">
                    hide framework
                </label>
                <button class="btn" onclick="expandAll(true)"><i class="ti ti-chevron-down"></i> expand all</button>
                <button class="btn" onclick="expandAll(false)"><i class="ti ti-chevron-up"></i> collapse all</button>
            </div>
        </div>

        <div class="frames-list" id="trace-list">
            <?php foreach ($frames as $i => $f): ?>
            <div class="frame<?= $f['noise'] ? ' noise' : '' ?><?= $i === 0 ? ' open' : '' ?>" data-search="<?= $e($f['search']) ?>">
                <div class="frame-head" onclick="toggleFrame(this)">
                    <div class="frame-idx"><?= (int)$f['idx'] ?></div>
                    <div class="frame-call">
                        <?php if ($f['noise']): ?>
                        <span class="frame-tag">framework</span>
                        <?php endif ?>
                        <?php if ($f['call'] !== ''): ?>
                            <?php if ($f['class'] !== ''): ?>
                            <span class="cls"><?= $e($f['class']) ?></span>::<span class="fn"><?= $e($f['function']) ?></span>()
                            <?php else: ?>
                            <?= $e($f['call']) ?>
                            <?php endif ?>
                        <?php else: ?>
                        {main}
                        <?php endif ?>
                    </div>
                    <div class="frame-loc">
                        <?php if ($f['file'] !== ''): ?>
                        <span class="path"><?= $e(dev_view::short_path($f['file'])) ?></span><span class="colon">:</span><span class="line"><?= (int)$f['line'] ?></span>
                        <?php else: ?>
                        <span style="color:var(--muted)">—</span>
                        <?php endif ?>
                    </div>
                </div>
                <div class="frame-body">
                    <?php if ($f['args'] !== []): ?>
                    <div class="frame-args">
                        <div class="frame-args-head">Arguments (<?= count($f['args']) ?>)</div>
                        <?php foreach ($f['args'] as $arg): ?>
                        <div class="arg-row">
                            <div class="arg-idx">#<?= (int)$arg['idx'] ?></div>
                            <div class="arg-val"><span class="type"><?= $e($arg['type']) ?></span>&nbsp;<span class="<?= dev_view::value_class($arg['type']) ?>"><?= $e($arg['value']) ?></span></div>
                        </div>
                        <?php endforeach ?>
                    </div>
                    <?php endif ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<!-- PANEL: ENVIRONMENT -->
<div class="panel" id="panel-env">
    <div class="panel-inner">
        <div class="kv-section">
            <?= $this->include('components/kv_grid', [
                'title' => 'Server & PHP',
                'items' => $env_server,
            ]) ?>
        </div>
        <?php if ($env_vars !== []): ?>
        <div class="kv-section">
            <?= $this->include('components/kv_grid', [
                'title'     => 'Environment variables',
                'dot'       => 'warn',
                'items'     => $env_vars,
                'badge'     => 'secrets masked · click to reveal',
                'badge_cls' => 'warn',
            ]) ?>
        </div>
        <?php endif ?>
    </div>
</div>

<!-- PANEL: REQUEST -->
<div class="panel" id="panel-request">
    <div class="panel-inner">
        <?php foreach ($request as $section): ?>
        <div class="kv-section">
            <?php if ($section['items'] !== []): ?>
            <?= $this->include('components/kv_grid', [
                'title' => $section['title'],
                'items' => $section['items'],
            ]) ?>
            <?php else: ?>
            <div class="kv-title"><span class="kv-dot"></span> <?= $e($section['title']) ?>
                <span class="badge" style="margin-left:auto">empty</span>
            </div>
            <div style="padding:12px 14px;font-size:13px;color:var(--muted);font-style:italic">
                <?= $e($section['empty_note'] ?? 'no data') ?>
            </div>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </div>
</div>

<?php if ($solution_count > 0): ?>
<!-- PANEL: SOLUTIONS -->
<div class="panel" id="panel-solutions">
    <div class="panel-inner">
        <?php foreach ($solutions as $sol): ?>
        <div class="solution">
            <div class="solution-head">
                <i class="ti ti-bulb"></i>
                <?= $e($sol['title']) ?>
            </div>
            <div class="solution-body">
                <?= $e($sol['body']) ?>
                <br><br>
                <strong style="color:var(--text)">Available methods in <code><?= $e($sol['class'] ?? '') ?></code>:</strong>
                <div class="methods-grid">
                    <?php
                    $similar_names = array_column($sol['similar'] ?? [], 'name');
                    foreach ($sol['methods'] ?? [] as $method):
                        $is_similar = in_array($method, $similar_names, true);
                    ?>
                    <span class="method-chip<?= $is_similar ? ' similar' : '' ?>">
                        <?= $e($method) ?><?= $is_similar ? ' ← similar' : '' ?>
                    </span>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>
<?php endif ?>

<?php $this->end() ?>


<?php $this->start('page_js') ?>
document.querySelectorAll('.tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
        document.querySelectorAll('.panel').forEach(function(p) { p.classList.remove('open'); });
        tab.classList.add('active');
        var panel = document.getElementById('panel-' + tab.dataset.tab);
        if (panel) panel.classList.add('open');
    });
});

function toggleFrame(head) { head.parentElement.classList.toggle('open'); }

function expandAll(open) {
    document.querySelectorAll('.frame:not(.noise)').forEach(function(f) {
        f.classList.toggle('open', open);
    });
}

function toggleNoise(hide) {
    document.querySelectorAll('.frame.noise').forEach(function(f) {
        f.classList.toggle('hidden', hide);
    });
    updateCounts();
}

function updateCounts() {
    var total   = document.querySelectorAll('.frame').length;
    var visible = document.querySelectorAll('.frame:not(.hidden)').length;
    document.getElementById('trace-visible-count').textContent = visible + ' visible';
    document.getElementById('trace-search-meta').textContent   = visible + ' of ' + total;
}

function filterTrace(q) {
    q = q.toLowerCase().trim();
    var visible = 0;
    document.querySelectorAll('.frame').forEach(function(f) {
        var hay  = (f.dataset.search + ' ' + f.textContent).toLowerCase();
        var show = !q || hay.indexOf(q) !== -1;
        f.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    var total = document.querySelectorAll('.frame').length;
    document.getElementById('trace-search-meta').textContent   = visible + ' of ' + total;
    document.getElementById('trace-visible-count').textContent = visible + ' visible';
}

function revealSecret(el) {
    if (el.classList.contains('revealed')) {
        el.textContent = '\u2022'.repeat(Math.min(el.dataset.secret.length, 30));
        el.classList.remove('revealed');
    } else {
        el.textContent = el.dataset.secret;
        el.classList.add('revealed');
    }
}

function copyTrace() {
    var text = Array.from(document.querySelectorAll('.frame')).map(function(f, i) {
        var call = (f.querySelector('.frame-call') || {}).textContent || '';
        var loc  = (f.querySelector('.frame-loc')  || {}).textContent || '';
        return '#' + i + '  ' + call.trim() + '  at ' + loc.trim();
    }).join('\n');
    navigator.clipboard.writeText(text).then(function() { showToast('Stack trace copied'); });
}

function openInIde(file, line) {
    window.location.href = 'phpstorm://open?file=' + encodeURIComponent(file) + '&line=' + line;
}

function showToast(msg) {
    var t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2200);
}
<?php $this->end() ?>

