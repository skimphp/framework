<?php
/**
 * Toolbar template — self-contained debug widget injected before </body>.
 *
 * @var string $method        HTTP method
 * @var string $path          Request path
 * @var int    $db_count      Query count
 * @var string $db_warn       Tab warning class
 * @var string $db_html       Pre-built query rows HTML
 * @var int    $cache_hits    Cache hit count
 * @var int    $cache_miss    Cache miss count
 * @var string $cache_ratio   Hit ratio percentage
 * @var string $cache_tab_label  Cache tab label
 * @var string $cache_driver  Cache driver name
 * @var string $cache_kv      Pre-built cache rows HTML
 * @var int    $view_count    Views rendered count
 * @var string $view_html     Pre-built view rows HTML
 * @var float  $total_ms      Total request time
 * @var string $timeline_html Pre-built timeline HTML
 * @var int    $log_count     Log entry count
 * @var string $log_html      Pre-built log rows HTML
 * @var float  $peak_mem      Peak memory in MB
 * @var string $ms_class      Time color class
 * @var string $php_ver       PHP version
 * @var string $req_html      Pre-built request panel HTML
 * @var array  $custom_panels Custom panels from Profiler::panels()
 */

use Skim\Dev\DevTheme;

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<style>
<?= \Skim\Dev\DevTheme::toolbarCss() ?>
</style>
<?= \Skim\Dev\DevTheme::iconFont() ?>
<div id="skim-tb">
    <div class="tb-resize" id="skim-resize"></div>
    <div class="bar">
        <div class="brand">
            <div class="brand-dot"></div>
            <span class="brand-name">skim</span>
        </div>
        <div class="route">
            <span class="http-method"><?= $e($method) ?></span>
            <span class="route-path"><?= $e($path) ?></span>
        </div>
        <div class="tabs">
            <div class="tab active" onclick="skimTab(this,'request')">
                <i class="ti ti-server" aria-hidden="true"></i>
                <span>request</span>
            </div>
            <div class="tab<?= $e($db_warn) ?>" onclick="skimTab(this,'db')">
                <i class="ti ti-database" aria-hidden="true"></i>
                <span>queries</span>
                <span class="tab-count"><?= (int)$db_count ?></span>
            </div>
            <div class="tab" onclick="skimTab(this,'cache')">
                <i class="ti ti-bolt" aria-hidden="true"></i>
                <span>cache</span>
                <span class="tab-count"><?= $e($cache_tab_label) ?></span>
            </div>
            <div class="tab" onclick="skimTab(this,'timeline')">
                <i class="ti ti-timeline" aria-hidden="true"></i>
                <span>timeline</span>
            </div>
            <div class="tab" onclick="skimTab(this,'views')">
                <i class="ti ti-layout" aria-hidden="true"></i>
                <span>views</span>
                <span class="tab-count"><?= (int)$view_count ?></span>
            </div>
            <div class="tab" onclick="skimTab(this,'log')">
                <i class="ti ti-list" aria-hidden="true"></i>
                <span>log</span>
                <span class="tab-count"><?= (int)$log_count ?></span>
            </div>
            <?php foreach ($custom_panels as $cp): ?>
            <div class="tab" onclick="skimTab(this,'<?= $e($cp['id']) ?>')">
                <i class="ti ti-<?= $e($cp['icon']) ?>" aria-hidden="true"></i>
                <span><?= $e($cp['label']) ?></span>
            </div>
            <?php endforeach ?>
        </div>
        <div class="stats">
            <div class="stat">
                <span>time</span>
                <span class="stat-val <?= $e($ms_class) ?>"><?= $e((string)$total_ms) ?>ms</span>
            </div>
            <div class="stat">
                <span>mem</span>
                <span class="stat-val"><?= $e((string)$peak_mem) ?> MB</span>
            </div>
        </div>
        <div class="tb-close" onclick="skimClose()" title="Toggle panel">
            <i class="ti ti-chevron-down" aria-hidden="true"></i>
        </div>
    </div>

    <div class="panel open" id="skim-panel-request">
        <div class="panel-inner">
            <?= $req_html ?>
        </div>
    </div>

    <div class="panel" id="skim-panel-db">
        <div class="col-head">
            <span>#</span><span>ms</span><span>SQL</span><span style="text-align:right">type</span>
        </div>
        <div class="panel-inner">
            <?= $db_html ?>
        </div>
    </div>

    <div class="panel" id="skim-panel-cache">
        <div class="panel-inner">
            <div class="cache-grid">
                <div class="cache-cell">
                    <div class="cache-lbl">hits</div>
                    <div class="cache-val hit"><?= (int)$cache_hits ?></div>
                </div>
                <div class="cache-cell">
                    <div class="cache-lbl">misses</div>
                    <div class="cache-val miss"><?= (int)$cache_miss ?></div>
                </div>
                <div class="cache-cell">
                    <div class="cache-lbl">hit ratio</div>
                    <div class="cache-val ratio"><?= $e($cache_ratio) ?></div>
                </div>
                <div class="cache-cell">
                    <div class="cache-lbl">driver</div>
                    <div class="cache-val" style="font-size:16px;color:var(--tb-text)"><?= $e($cache_driver) ?></div>
                </div>
            </div>
            <div style="padding:0 16px">
                <?= $cache_kv ?>
            </div>
        </div>
    </div>

    <div class="panel" id="skim-panel-timeline">
        <div class="panel-inner">
            <div style="padding:12px 16px">
                <?= $timeline_html ?>
            </div>
        </div>
    </div>

    <div class="panel" id="skim-panel-views">
        <div class="panel-inner">
            <div style="padding:8px 0">
                <?= $view_html ?>
            </div>
        </div>
    </div>

    <div class="panel" id="skim-panel-log">
        <div class="panel-inner">
            <?= $log_html ?>
        </div>
    </div>

    <?php foreach ($custom_panels as $cp): ?>
    <div class="panel" id="skim-panel-<?= $e($cp['id']) ?>">
        <div class="panel-inner">
            <?php if ($cp['html'] !== ''): ?>
            <?= $cp['html'] ?>
            <?php elseif ($cp['template'] !== ''): ?>
            <?= \Skim\Dev\DevView::render($cp['template'], $cp['data']) ?>
            <?php else: ?>
            <?= $this->include('components/kv_grid', ['items' => $cp['data']]) ?>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
</div>
<script>
(function(){
    var STORAGE_KEY = 'skim-toolbar-open';
    function skimTab(el,id){
        document.querySelectorAll('#skim-tb .tab').forEach(function(t){t.classList.remove('active')});
        document.querySelectorAll('#skim-tb .panel').forEach(function(p){p.classList.remove('open')});
        el.classList.add('active');
        var p=document.getElementById('skim-panel-'+id);
        if(p) p.classList.add('open');
    }
    var _saved = localStorage.getItem(STORAGE_KEY);
    var _open = _saved === null ? true : _saved === '1';
    function skimClose(){
        var ch=document.querySelector('#skim-tb .tb-close .ti');
        var panels=document.querySelectorAll('#skim-tb .panel');
        if(_open){
            panels.forEach(function(p){if(p.classList.contains('open'))p.setAttribute('data-was-open','1');p.classList.remove('open');});
            if(ch){ch.className='ti ti-chevron-up';}
            _open=false;
            localStorage.setItem(STORAGE_KEY,'0');
        } else {
            document.querySelectorAll('#skim-tb [data-was-open]').forEach(function(p){p.classList.add('open');p.removeAttribute('data-was-open');});
            if(ch){ch.className='ti ti-chevron-down';}
            _open=true;
            localStorage.setItem(STORAGE_KEY,'1');
        }
    }
    // Apply persisted state on load
    if(!_open){
        document.querySelectorAll('#skim-tb .panel').forEach(function(p){p.classList.remove('open');});
        var ch=document.querySelector('#skim-tb .tb-close .ti');
        if(ch){ch.className='ti ti-chevron-up';}
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
