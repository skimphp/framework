<?php
/**
 * Error page template — full-page developer error display with tabs.
 *
 * Renders the hero, tab bar, and five panels (Code / Stack Trace / Container /
 * Environment / Request) plus an inline Solutions block when solutions exist.
 * Replaces the older single-tab trace with three labeled sections (Application
 * / Request Pipeline / Framework) and adds the Container / DI tree panel.
 *
 * @var string $class           Exception class name
 * @var string $message         Exception message
 * @var string $file            Error file path
 * @var int    $line            Error line number
 * @var string $php_version     PHP version string
 * @var array  $code_lines      Code context lines [{num, code, hl}]
 * @var int    $line_start      First visible line number
 * @var int    $line_end        Last visible line number
 * @var array  $frames_app      Application frames [{idx, file, line, call, args, ...}]
 * @var array  $frames_pipeline Middleware pipeline frames
 * @var array  $frames_fw       Framework frames
 * @var int    $frame_count     Total frame count across all three sections
 * @var array  $env_server      Server/PHP environment items
 * @var array  $env_vars        Environment variable items
 * @var array  $request         Request sections
 * @var ?array $route           Matched route info from request_trace
 * @var ?array $middleware      Middleware stack rows
 * @var array  $container       DI tree nodes
 * @var array  $solutions       Suggested solutions
 * @var string $trace_text      Raw trace string for copy
 * @var string $ide             Resolved IDE identifier
 * @var string $ide_name        Human IDE name
 * @var string $ide_url         IDE deep-link URL for the error location
 */

use Skim\Dev\DevView;

$this->layout('base');

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>

<?php $this->start('page_css') ?>
/* Error-page-only CSS lives in dev_theme::css() so it ships once. */
<?php $this->end() ?>


<?php $this->start('content') ?>

<!-- HERO -->
<div class="hero">
  <div class="hero-msg">
    <span class="badge-err"><i class="ti ti-alert-triangle"></i> Error</span>
    <?= $e($message) ?>
  </div>

  <a class="hero-loc" href="<?= $e($ide_url) ?>" title="Open in <?= $e($ide_name) ?>">
    <i class="ti ti-file-code"></i>
    <span class="path"><?= $e($file) ?></span>
    <span class="colon">:</span>
    <span class="line"><?= (int)$line ?></span>
  </a>

  <div class="actions">
    <a class="btn primary" href="<?= $e($ide_url) ?>">
      <i class="ti ti-brand-phpstorm"></i> Open in <?= $e($ide_name) ?>
    </a>
    <button class="btn" onclick="copyTrace()">
      <i class="ti ti-copy"></i> Copy stack trace
    </button>
    <a class="btn warn" target="_blank"
       href="https://www.google.com/search?q=<?= $e(urlencode($message . ' php')) ?>">
      <i class="ti ti-brand-google"></i> Google
    </a>
    <a class="btn" target="_blank"
       href="https://chat.openai.com/?q=<?= $e(urlencode('PHP Error: ' . $class . ': ' . $message)) ?>">
      <i class="ti ti-sparkles"></i> Ask AI
    </a>
  </div>
</div>

<!-- TABS -->
<div class="tabs">
  <div class="tab active" data-tab="code"><i class="ti ti-code"></i> Code</div>
  <div class="tab" data-tab="trace"><i class="ti ti-stack-2"></i> Stack Trace <span class="tab-pill"><?= (int)$frame_count ?></span></div>
  <div class="tab" data-tab="container"><i class="ti ti-hierarchy"></i> Container</div>
  <div class="tab" data-tab="env"><i class="ti ti-server"></i> Environment</div>
  <div class="tab" data-tab="request"><i class="ti ti-world"></i> Request</div>
</div>

<!-- PANEL: CODE -->
<div class="panel open" id="panel-code">
  <div class="panel-inner">
    <div class="code-box">
      <div class="code-head">
        <a class="code-file-link" href="<?= $e($ide_url) ?>">
          <i class="ti ti-file-code"></i>
          <?= $e($file) ?>
        </a>
        <span class="code-err-label">lines <?= (int)$line_start ?>–<?= (int)$line_end ?> · error at <span class="ln-danger">:<?= (int)$line ?></span></span>
      </div>
      <div class="code-body">
        <table>
          <?php foreach ($code_lines as $l): ?>
          <tr<?= !empty($l['hl']) ? ' class="hl"' : '' ?>>
            <td class="ln"><?= (int)$l['num'] ?></td>
            <td class="src"><?= $l['code'] ?></td>
          </tr>
          <?php endforeach ?>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- PANEL: STACK TRACE -->
<div class="panel" id="panel-trace">
  <div class="panel-inner">

    <?php if ($frames_app !== []): ?>
    <div class="trace-section app">
      <div class="trace-section-head">
        <span class="trace-section-label"><i class="ti ti-app-window" style="vertical-align:middle;margin-right:5px"></i>Application</span>
        <span class="trace-section-line"></span>
        <span class="trace-section-count"><?= count($frames_app) ?> frame<?= count($frames_app) === 1 ? '' : 's' ?></span>
      </div>

      <?php foreach ($frames_app as $i => $f): ?>
      <div class="frame<?= !empty($f['is_error']) ? ' is-error' : '' ?><?= $i === 0 ? ' open' : '' ?>" data-search="<?= $e($f['search']) ?>">
        <div class="frame-head" onclick="toggleFrame(this)">
          <div class="frame-idx"><?= (int)$f['idx'] ?></div>
          <div class="frame-main">
            <div class="frame-call">
              <?php if ($f['class'] !== ''): ?>
              <span class="cls"><?= $e($f['class']) ?></span><span class="op"><?= $e($f['function'] !== '' ? '::' : '') ?></span><span class="fn"><?= $e($f['function']) ?></span>()
              <?php else: ?>
              <?= $e($f['call']) ?>
              <?php endif ?>
            </div>
            <div class="frame-subtitle">
              <?php if ($f['file'] !== ''): ?>
              <span class="path"><?= $e(\Skim\Dev\DevView::shortPath($f['file'])) ?></span><span class="colon">:</span><span class="line"><?= (int)$f['line'] ?></span>
              <?php else: ?>
              <span style="color:var(--muted)">—</span>
              <?php endif ?>
            </div>
          </div>
          <?php if (!empty($f['is_error'])): ?>
          <span class="frame-context error"><i class="ti ti-alert-triangle"></i> Error origin</span>
          <?php else: ?>
          <span class="frame-context route">Route Handler</span>
          <?php endif ?>
        </div>
        <div class="frame-body">
          <?php if (!empty($f['args'])): ?>
          <div class="args-panel">
            <div class="args-title">Arguments (<?= count($f['args']) ?>)</div>
            <?php foreach ($f['args'] as $arg): ?>
            <div class="arg-item">
              <div class="arg-row">
                <div class="arg-idx">#<?= (int)$arg['idx'] ?></div>
                <div class="arg-value-wrap">
                  <div>
                    <span class="arg-type-badge <?= $e(\Skim\Dev\DevView::valueClass($arg['type'])) ?>"><?= $e($arg['type']) ?><?= isset($arg['object_id']) ? ' ' . $e($arg['object_id']) : '' ?></span>
                  </div>
                  <?php if (!empty($arg['props'])): ?>
                  <div class="obj-props">
                    <?php foreach ($arg['props'] as $prop): ?>
                    <div class="obj-prop-row">
                      <span class="obj-prop-key"><?= $e($prop['key']) ?></span>
                      <span class="obj-prop-val <?= $e($prop['class']) ?>"><?= $e((string)$prop['value']) ?></span>
                    </div>
                    <?php endforeach ?>
                  </div>
                  <?php elseif ($arg['type'] === 'int' || $arg['type'] === 'float'): ?>
                  <span class="val-int"><?= $e((string)$arg['value']) ?></span>
                  <?php elseif (isset($arg['closure_at'])): ?>
                  <span class="obj-prop-val muted"><?= $e($arg['closure_at']) ?></span>
                  <?php elseif (isset($arg['array_len'])): ?>
                  <span class="obj-prop-val muted">array[<?= (int)$arg['array_len'] ?>]</span>
                  <?php else: ?>
                  <span class="obj-prop-val"><?= $e((string)$arg['value']) ?></span>
                  <?php endif ?>
                </div>
              </div>
            </div>
            <?php endforeach ?>
          </div>
          <?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if ($frames_pipeline !== []): ?>
    <div class="trace-section mw">
      <div class="trace-section-head">
        <span class="trace-section-label"><i class="ti ti-filter" style="vertical-align:middle;margin-right:5px"></i>Request Pipeline</span>
        <span class="trace-section-line"></span>
        <span class="trace-section-count"><?= count($frames_pipeline) ?> middleware</span>
      </div>
      <?php foreach ($frames_pipeline as $f): ?>
      <div class="frame" data-search="<?= $e($f['search']) ?>">
        <div class="frame-head" onclick="toggleFrame(this)">
          <div class="frame-idx"><?= (int)$f['idx'] ?></div>
          <div class="frame-main">
            <div class="frame-call">
              <span class="cls"><?= $e($f['class']) ?></span><span class="op">::</span><span class="fn"><?= $e($f['function']) ?></span>()
            </div>
            <div class="frame-subtitle">
              <span class="path"><?= $e(\Skim\Dev\DevView::shortPath($f['file'])) ?></span><span class="colon">:</span><span class="line"><?= (int)$f['line'] ?></span>
            </div>
          </div>
          <span class="frame-context mw">Request → Response</span>
        </div>
        <div class="frame-body"></div>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if ($frames_fw !== []): ?>
    <div class="trace-section">
      <div class="trace-section-head">
        <span class="trace-section-label"><i class="ti ti-package" style="vertical-align:middle;margin-right:5px"></i>Framework</span>
        <span class="trace-section-line"></span>
        <span class="trace-section-count"><?= count($frames_fw) ?> frame<?= count($frames_fw) === 1 ? '' : 's' ?></span>
      </div>
      <div class="fw-collapsed" id="fw-toggle" onclick="toggleFwFrames()">
        <i class="ti ti-chevron-right" id="fw-chevron"></i>
        <span id="fw-toggle-label">Show <?= count($frames_fw) ?> framework frame<?= count($frames_fw) === 1 ? '' : 's' ?></span>
        <span style="margin-left:auto;font-size:11px;color:var(--muted)">Skim\Core\* &middot; vendor\*</span>
      </div>
      <div id="fw-frames" class="hidden">
        <?php foreach ($frames_fw as $f): ?>
        <div class="frame" data-search="<?= $e($f['search']) ?>">
          <div class="frame-head" onclick="toggleFrame(this)">
            <div class="frame-idx"><?= (int)$f['idx'] ?></div>
            <div class="frame-main">
              <div class="frame-call">
                <?php if ($f['class'] !== ''): ?>
                <span class="cls"><?= $e($f['class']) ?></span><span class="op">::</span><span class="fn"><?= $e($f['function']) ?></span>()
                <?php else: ?>
                <?= $e($f['call']) ?>
                <?php endif ?>
              </div>
              <div class="frame-subtitle">
                <span class="path"><?= $e(\Skim\Dev\DevView::shortPath($f['file'])) ?></span><span class="colon">:</span><span class="line"><?= (int)$f['line'] ?></span>
              </div>
            </div>
          </div>
          <div class="frame-body"></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

  </div>
</div>

<!-- PANEL: CONTAINER (DI tree) -->
<div class="panel" id="panel-container">
  <div class="panel-inner">
    <div style="font-size:13px;color:var(--muted2);margin-bottom:16px">
      Dependency resolution tree for this request. Failed nodes are highlighted.
    </div>
    <div class="di-tree">
      <?php foreach ($container as $node): ?>
        <?= $this->include('components/di_node', ['node' => $node, 'depth' => 0]) ?>
      <?php endforeach ?>
    </div>
<!--
    <div style="margin-top:32px;padding-top:20px;border-top:1px solid var(--border)">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:12px">Example — failed resolution</div>
      <?= $this->include('components/di_node', [
        'node' => [
          'cls'      => 'PaymentController',
          'ref'      => 'resolving…',
          'failed'   => false,
          'children' => [
            [
              'cls'    => 'StripeGateway',
              'ref'    => 'failed',
              'failed' => true,
              'detail' => '<strong>Missing binding:</strong> <code style="background:rgba(0,0,0,.3);padding:1px 5px;border-radius:3px;color:var(--s-cls)">PaymentConfigInterface</code> is not bound in the container.<br>'
                        . 'Add <code style="background:rgba(0,0,0,.3);padding:1px 5px;border-radius:3px;color:var(--s-fn)">$container-&gt;bind(PaymentConfigInterface::class, StripeConfig::class)</code> in your service provider.',
              'children' => [],
            ],
          ],
        ],
        'depth' => 0,
      ]) ?>
    </div>
-->
    <?php if ($bindings_list !== []): ?>
    <div style="margin-top:32px;padding-top:20px;border-top:1px solid var(--border)">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:12px">All bindings (<?= (int)count($bindings_list) ?>)</div>
      <div class="kv-grid">
        <?php foreach ($bindings_list as $b): ?>
        <div class="kv-cell">
          <span class="kv-k"><?= $e($b['abstract']) ?></span>
          <span class="kv-v" style="font-size:11px;color:var(--muted)"><?= $e($b['factory']) ?> · p<?= (int)$b['priority'] ?></span>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

    <?php if ($resolved_list !== []): ?>
    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--ok);margin-bottom:12px">Resolved in this request (<?= (int)count($resolved_list) ?>)</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($resolved_list as $r): ?>
        <span class="badge ok" style="font-family:var(--mono);font-size:11px"><?= $e($r['abstract']) ?></span>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>

<!-- PANEL: ENVIRONMENT -->
<div class="panel" id="panel-env">
  <div class="panel-inner">
    <div class="kv-section">
      <div class="kv-title"><span class="kv-dot"></span> Server &amp; PHP</div>
      <div class="kv-grid">
        <?php foreach ($env_server as $row): ?>
        <div class="kv-cell"><span class="kv-k"><?= $e($row['key']) ?></span><span class="kv-v <?= $e($row['class']) ?>"><?= $e($row['value']) ?></span></div>
        <?php endforeach ?>
      </div>
    </div>

    <?php if ($env_vars !== []): ?>
    <div class="kv-section">
      <div class="kv-title">
        <span class="kv-dot warn"></span> Environment variables
        <span class="badge warn" style="margin-left:auto">secrets masked · click to reveal</span>
      </div>
      <div class="kv-grid">
        <?php foreach ($env_vars as $row): ?>
        <div class="kv-cell">
          <span class="kv-k"><?= $e($row['key']) ?></span>
          <?php if (!empty($row['secret'])): ?>
          <span class="kv-v secret" onclick="revealSecret(this)" data-secret="<?= $e($row['secret_value']) ?>"><?= $e($row['value']) ?></span>
          <?php else: ?>
          <span class="kv-v <?= $e($row['class']) ?>"><?= $e($row['value']) ?></span>
          <?php endif ?>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>

<!-- PANEL: REQUEST -->
<div class="panel" id="panel-request">
  <div class="panel-inner">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px">

      <div>
        <div class="kv-title"><span class="kv-dot"></span> Matched Route</div>
        <div class="route-block">
          <?php if ($route !== null): ?>
          <div class="route-pill">
            <span class="method-badge"><?= $e($_SERVER['REQUEST_METHOD'] ?? 'GET') ?></span>
            <span class="route-pattern"><?= $e(preg_replace_callback('/(@[a-zA-Z_][a-zA-Z0-9_]*(?::[a-z]+)?)/', static fn($m) => '<span class="param">' . $m[0] . '</span>', $route['pattern'])) ?></span>
          </div>
          <?php if (!empty($route['params'])): ?>
          <table class="params-table">
            <?php foreach ($route['params'] as $k => $v): ?>
            <tr><td class="pk"><?= $e((string)$k) ?></td><td class="pv"><?= $e(is_scalar($v) ? (string)$v : json_encode($v)) ?></td></tr>
            <?php endforeach ?>
          </table>
          <?php else: ?>
          <div style="font-size:12px;color:var(--muted);font-style:italic">no route params</div>
          <?php endif ?>
          <?php else: ?>
          <div style="font-size:13px;color:var(--muted);font-style:italic">no route captured (trace unavailable)</div>
          <?php endif ?>
        </div>
      </div>

      <div>
        <div class="kv-title"><span class="kv-dot" style="background:var(--purple)"></span> Middleware Stack</div>
        <div class="mw-list">
          <?php if ($middleware !== null): ?>
            <?php foreach ($middleware as $mw): ?>
            <div class="mw-item<?= !empty($mw['active']) ? ' active' : '' ?>">
              <i class="ti ti-shield-check"></i> <?= $e($mw['class']) ?>
              <?php if (!empty($mw['active'])): ?>
              <span class="mw-tag">active</span>
              <?php endif ?>
            </div>
            <?php endforeach ?>
          <?php else: ?>
            <div style="font-size:13px;color:var(--muted);font-style:italic">no middleware captured</div>
          <?php endif ?>
        </div>
      </div>

    </div>

    <?php foreach ($request as $section): ?>
    <div class="kv-section">
      <?php if (!empty($section['items'])): ?>
        <div class="kv-title"><span class="kv-dot"></span> <?= $e($section['title']) ?></div>
        <div class="kv-grid">
          <?php foreach ($section['items'] as $row): ?>
          <div class="kv-cell">
            <span class="kv-k"><?= $e($row['key']) ?></span>
            <?php if (!empty($row['secret'])): ?>
            <span class="kv-v secret" onclick="revealSecret(this)" data-secret="<?= $e($row['secret_value']) ?>"><?= $e($row['value']) ?></span>
            <?php else: ?>
            <span class="kv-v <?= $e($row['class']) ?>"><?= $e($row['value']) ?></span>
            <?php endif ?>
          </div>
          <?php endforeach ?>
        </div>
      <?php else: ?>
        <div class="kv-title"><span class="kv-dot"></span> <?= $e($section['title']) ?><span class="badge" style="margin-left:auto">empty</span></div>
        <div style="padding:12px 14px;font-size:13px;color:var(--muted);font-style:italic"><?= $e($section['empty_note'] ?? 'no data') ?></div>
      <?php endif ?>
    </div>
    <?php endforeach ?>

  </div>
</div>

<?php if (!empty($solutions)): ?>
<!-- SOLUTIONS (inline) -->
<div class="panel open" id="panel-solutions">
  <div class="panel-inner">
    <div class="kv-title" style="margin-bottom:14px">
      <span class="kv-dot" style="background:var(--ok)"></span> Suggested Solutions
      <span class="badge ok" style="margin-left:auto"><?= count($solutions) ?> suggestion<?= count($solutions) === 1 ? '' : 's' ?></span>
    </div>
    <?php foreach ($solutions as $sol): ?>
    <div class="solution">
      <div class="solution-head">
        <i class="ti ti-bulb"></i>
        <?= $e($sol['title']) ?>
      </div>
      <div class="solution-body">
        <?= $e($sol['body']) ?>
        <?php if (!empty($sol['methods'])): ?>
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
        <?php endif ?>
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

function toggleFwFrames() {
  var frames  = document.getElementById('fw-frames');
  var chevron = document.getElementById('fw-chevron');
  var label   = document.getElementById('fw-toggle-label');
  if (!frames) return;
  var hidden  = frames.classList.toggle('hidden');
  if (chevron) chevron.style.transform = hidden ? '' : 'rotate(90deg)';
  var n = frames.querySelectorAll('.frame').length;
  if (label) label.textContent = hidden ? ('Show ' + n + ' framework frame' + (n === 1 ? '' : 's')) : ('Hide framework frames');
}

function revealSecret(el) {
  if (el.classList.contains('revealed')) {
    el.textContent = '••••••••••••••••••••••••••••••';
    el.classList.remove('revealed');
  } else {
    el.textContent = el.dataset.secret;
    el.classList.add('revealed');
  }
}

function copyTrace() {
  var lines = Array.from(document.querySelectorAll('.frame')).map(function(f) {
    var call = (f.querySelector('.frame-call') || {}).textContent || '';
    var loc  = (f.querySelector('.frame-subtitle') || {}).textContent || '';
    return call.trim() + '  at ' + loc.trim();
  });
  navigator.clipboard.writeText(lines.join('\n')).then(function() { showToast('Stack trace copied'); });
}

function showToast(msg) {
  var t = document.getElementById('toast');
  var m = document.getElementById('toast-msg');
  if (m) m.textContent = msg;
  if (t) {
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2200);
  }
}
<?php $this->end() ?>
