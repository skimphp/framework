<?php declare(strict_types=1);

namespace Skim\Dev;

/**
 * Shared CSS design system for all dev tool HTML output. #AI:class
 *
 * Use when any dev tool (error page, toolbar, future debug panels) needs
 * consistent dark-theme styling. Returns CSS strings for embedding in
 * <style> blocks — no external .css files, no asset pipeline dependency.
 *
 * The design system defines CSS custom properties for colors, typography,
 * and spacing, plus shared component classes (kv-grid, badges, buttons,
 * code-box, tabs, toast) used across error_page and toolbar templates.
 *
 * Example:
 *   // Inside a dev tool template:
 *   <style><?= DevTheme::css() ?></style>
 *
 * Testing: Pure string output — assert css() contains expected selectors.
 *
 * #AI:class
 */
final class DevTheme {
    /**
     * Returns the complete shared CSS for full-page dev tools (error page). #AI:css
     *
     * Includes CSS custom properties, base reset, scrollbar styling, and
     * shared component classes. Toolbar uses toolbarCss() instead because
     * it needs scoped selectors to avoid conflicts with the host page.
     */
    public static function css(): string {
        return <<<'CSS'
:root {
  --bg:         #0c0e14;
  --surface:    #13161f;
  --surface2:   #181c27;
  --border:     rgba(255,255,255,.07);
  --border2:    rgba(255,255,255,.12);
  --text:       #dce4f0;
  --muted:      #4e5a6e;
  --muted2:     #6b7a90;
  --accent:     #4f8ef7;
  --accent-dim: rgba(79,142,247,.12);
  --accent-glow:rgba(79,142,247,.25);
  --danger:     #f14c4c;
  --danger-dim: rgba(241,76,76,.1);
  --danger-glow:rgba(241,76,76,.3);
  --warn:       #f0a245;
  --warn-dim:   rgba(240,162,69,.1);
  --ok:         #27c07a;
  --ok-dim:     rgba(39,192,122,.1);
  --purple:     #c678dd;
  --purple-dim: rgba(198,120,221,.12);
  --code-bg:    #090b10;
  --ln-color:   #2a3040;
  --s-kw:  #e06c75;
  --s-fn:  #c678dd;
  --s-str: #98c379;
  --s-num: #61afef;
  --s-com: #5c6370;
  --s-cls: #e5c07b;
  --s-var: #abb2bf;
  --s-op:  #56b6c2;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
html { scroll-behavior: smooth; }
body {
  font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', ui-monospace, monospace;
  font-size: 14px; line-height: 1.6;
  background: var(--bg); color: var(--text);
  padding: 28px 24px 60px;
  -webkit-font-smoothing: antialiased;
}
::selection { background: rgba(79,142,247,.25); }
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.15); }
.wrap { max-width: 1320px; margin: 0 auto; }

.kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--border); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
.kv-cell { background: var(--surface2); padding: 9px 14px; display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: 13px; transition: background .1s; }
.kv-cell:hover { background: rgba(255,255,255,.025); }
.kv-k { color: var(--muted2); flex-shrink: 0; }
.kv-v { color: var(--text); text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; }
.kv-v.ok     { color: var(--ok); }
.kv-v.warn   { color: var(--warn); }
.kv-v.blue   { color: #79c0ff; }
.kv-v.purple { color: var(--s-fn); }
.kv-v.muted  { color: var(--muted); font-style: italic; }
.kv-v.secret { cursor: pointer; background: var(--warn-dim); padding: 1px 7px; border-radius: 3px; color: var(--warn); font-family: inherit; transition: all .13s; }
.kv-v.secret:hover { background: rgba(240,162,69,.18); }
.kv-v.secret.revealed { background: var(--ok-dim); color: var(--ok); }

.kv-section { margin-bottom: 22px; }
.kv-section:last-child { margin-bottom: 0; }
.kv-title { display: flex; align-items: center; gap: 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: var(--muted2); padding: 0 0 8px; border-bottom: 1px solid var(--border); margin-bottom: 10px; }
.kv-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }
.kv-dot.warn { background: var(--warn); }

.badge { display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; padding: 2px 7px; border-radius: 3px; background: rgba(255,255,255,.05); color: var(--muted2); border: 1px solid var(--border); }
.badge.ok    { background: var(--ok-dim);     border-color: rgba(39,192,122,.2);  color: var(--ok); }
.badge.warn  { background: var(--warn-dim);   border-color: rgba(240,162,69,.2);  color: var(--warn); }
.badge.danger{ background: var(--danger-dim); border-color: rgba(241,76,76,.2);   color: var(--danger); }

.chip { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 4px; font-size: 11px; background: rgba(255,255,255,.04); border: 1px solid var(--border); color: var(--muted2); }
.chip.ok   { background: var(--ok-dim);   border-color: rgba(39,192,122,.2); color: var(--ok); }
.chip.warn { background: var(--warn-dim); border-color: rgba(240,162,69,.2); color: var(--warn); }

.btn { display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 5px; background: rgba(255,255,255,.04); border: 1px solid var(--border); color: var(--text); cursor: pointer; font: inherit; font-size: 13px; text-decoration: none; transition: all .13s; white-space: nowrap; }
.btn i { font-size: 15px; }
.btn:hover { background: rgba(255,255,255,.08); border-color: var(--border2); }
.btn.primary { background: var(--accent-dim); border-color: var(--accent-glow); color: var(--accent); }
.btn.primary:hover { background: rgba(79,142,247,.2); }
.btn.warn:hover { color: var(--warn); border-color: rgba(240,162,69,.3); }
.btn.ok:hover   { color: var(--ok);   border-color: rgba(39,192,122,.3); }

.code-box { background: var(--code-bg); border: 1px solid var(--border); border-radius: 7px; overflow: hidden; }
.code-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: rgba(0,0,0,.4); border-bottom: 1px solid var(--border); font-size: 12px; color: var(--muted2); }
.code-body { overflow-x: auto; }
.code-body table { border-collapse: collapse; width: 100%; }
.code-body tr { line-height: 1.75; }
.code-body td { padding: 0; white-space: pre; vertical-align: top; font-size: 13.5px; }
.code-body .ln { color: var(--ln-color); text-align: right; user-select: none; min-width: 56px; padding: 0 18px 0 16px; border-right: 1px solid rgba(255,255,255,.04); }
.code-body .src { padding: 0 20px; }
.code-body tr:hover { background: rgba(255,255,255,.02); }
.code-body tr.hl { background: rgba(241,76,76,.09); }
.code-body tr.hl .ln { color: var(--danger); border-right-color: rgba(241,76,76,.25); }
.code-body tr.hl .src { position: relative; }
.code-body tr.hl .src::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--danger); border-radius: 0 2px 2px 0; }
.code-file-link { color: #79c0ff; cursor: pointer; display: flex; align-items: center; gap: 7px; font-size: 13px; transition: color .12s; text-decoration: none; }
.code-file-link i { font-size: 15px; }
.code-file-link:hover { color: var(--text); }
.code-err-label { font-size: 12px; color: var(--muted2); display: flex; align-items: center; gap: 6px; }
.code-err-label .ln-danger { color: var(--danger); font-weight: 600; }

.kw  { color: var(--s-kw); }
.fn  { color: var(--s-fn); }
.str { color: var(--s-str); }
.num { color: var(--s-num); }
.com { color: var(--s-com); font-style: italic; }
.cls { color: var(--s-cls); }
.var { color: var(--s-var); }
.op  { color: var(--s-op); }

.tabs { display: flex; background: var(--bg); border: 1px solid var(--border); border-bottom: none; border-radius: 10px 10px 0 0; overflow: auto hidden; position: sticky; top: 0; z-index: 50; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
.tabs::-webkit-scrollbar { height: 0; }
.tab { display: flex; align-items: center; gap: 7px; padding: 0 20px; height: 44px; cursor: pointer; font-size: 13px; color: var(--muted2); white-space: nowrap; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); transition: background .12s, color .12s; position: relative; user-select: none; flex-shrink: 0; background: var(--bg); }
.tab:last-child { border-right: none; }
.tab i { font-size: 16px; }
.tab:hover { background: rgba(255,255,255,.025); color: var(--text); }
/* Active: surface bg, accent top border, no bottom border so it merges with panel */
.tab.active { color: var(--text); background: var(--surface); border-bottom-color: var(--surface); box-shadow: inset 0 1px 0 #5b8ef0; }
.tab-pill { font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 10px; background: rgba(255,255,255,.06); color: var(--muted2); }
.tab.active .tab-pill { background: var(--accent-dim); color: var(--accent); }
.tab-pill.ok { background: var(--ok-dim); color: var(--ok); }

/* ── Hero (error page top banner) ── */
.hero { position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 28px 32px 24px; margin-bottom: 14px; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--danger) 0%, var(--warn) 55%, transparent 100%); }
.hero::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 200px; background: radial-gradient(ellipse 50% 100% at 15% 0%, rgba(241,76,76,.055), transparent); pointer-events: none; }
.hero-msg { font-size: 22px; font-weight: 600; color: #f0f4ff; line-height: 1.45; margin-bottom: 18px; word-break: break-word; letter-spacing: -.01em; }
.hero-msg code { font-family: inherit; font-size: .9em; }
.badge-err { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; background: var(--danger-dim); color: var(--danger); border: 1px solid rgba(241,76,76,.25); white-space: nowrap; vertical-align: middle; margin-right: 10px; position: relative; top: -2px; }
.badge-err i { font-size: 13px; }
.hero-loc { display: inline-flex; align-items: center; gap: 9px; padding: 8px 14px; background: rgba(0,0,0,.3); border: 1px solid var(--border); border-radius: 6px; font-size: 13px; color: var(--muted2); text-decoration: none; cursor: pointer; transition: all .15s; margin-bottom: 20px; }
.hero-loc:hover { background: rgba(79,142,247,.08); border-color: rgba(79,142,247,.3); color: var(--text); }
.hero-loc i { color: var(--muted); font-size: 16px; }
.hero-loc .path { color: #79c0ff; }
.hero-loc .colon { color: var(--muted); }
.hero-loc .line { color: var(--warn); }
.actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 18px; border-top: 1px solid var(--border); }

.panel { display: none; background: var(--surface); border: 1px solid var(--border); border-top: none; border-radius: 0 0 10px 10px; margin-bottom: 14px; }
.panel.open { display: block; }
.panel-inner { padding: 24px 28px; }

.toast { position: fixed; bottom: 28px; right: 28px; background: var(--surface); border: 1px solid var(--ok); border-radius: 6px; padding: 11px 16px; font-size: 13px; color: var(--text); display: flex; align-items: center; gap: 9px; opacity: 0; transform: translateY(16px); transition: all .2s cubic-bezier(.22,1,.36,1); pointer-events: none; z-index: 1000; }
.toast.show { opacity: 1; transform: translateY(0); }
.toast i { color: var(--ok); font-size: 16px; }

.foot { text-align: center; font-size: 12px; color: var(--muted); padding: 40px 0 16px; margin-top: 24px; letter-spacing: .04em; border-top: 1px solid var(--border); }
.foot a { color: var(--accent); text-decoration: none; }
.foot a:hover { text-decoration: underline; }
.foot strong { color: var(--muted2); }

.hidden { display: none !important; }

/* ── Trace sections (Application / Request Pipeline / Framework) ── */
.trace-section { margin-bottom: 20px; }
.trace-section:last-child { margin-bottom: 0; }
.trace-section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.trace-section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--muted2); }
.trace-section-line { flex: 1; height: 1px; background: var(--border); }
.trace-section-count { font-size: 11px; color: var(--muted); }
.trace-section.app .trace-section-label { color: var(--accent); }
.trace-section.app .trace-section-line  { background: var(--accent-dim); }
.trace-section.mw .trace-section-label  { color: var(--purple); }
.trace-section.mw .trace-section-line   { background: var(--purple-dim); }

/* ── Stack frames ── */
.frame { background: rgba(0,0,0,.2); border: 1px solid var(--border); border-radius: 6px; margin-bottom: 5px; overflow: hidden; transition: border-color .13s; }
.frame:hover { border-color: var(--border2); }
.frame.is-error { border-color: rgba(241,76,76,.3); }
.frame.is-error .frame-head { background: rgba(241,76,76,.05); }
.frame.open { border-color: rgba(79,142,247,.3); }
.frame.open .frame-head { background: rgba(79,142,247,.07); }
.frame.is-error.open { border-color: rgba(241,76,76,.4); }
.frame.is-error.open .frame-head { background: rgba(241,76,76,.08); }
.frame-head { display: grid; grid-template-columns: 38px 1fr auto; gap: 12px; align-items: start; padding: 11px 14px; cursor: pointer; transition: background .1s; user-select: none; }
.frame-main { min-width: 0; }
.arg-value-wrap { min-width: 0; }
.frame-head:hover { background: rgba(255,255,255,.025); }
.frame-idx { font-size: 12px; color: var(--muted); text-align: right; padding-top: 2px; }
.frame.is-error .frame-idx { color: var(--danger); }
.frame.open .frame-idx { color: var(--accent); }
.frame-call { font-size: 13.5px; color: var(--text); margin-bottom: 3px; }
.frame-call .cls { color: var(--s-cls); }
.frame-call .fn  { color: var(--s-fn); }
.frame-subtitle { font-size: 12px; color: var(--muted2); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.frame-subtitle .path { color: #79c0ff; }
.frame-subtitle .colon { color: var(--muted); }
.frame-subtitle .line { color: var(--warn); }
.frame-context { font-size: 11px; color: var(--muted); background: rgba(255,255,255,.04); border: 1px solid var(--border); padding: 1px 7px; border-radius: 3px; white-space: nowrap; align-self: center; }
.frame-context.route { color: var(--accent); background: var(--accent-dim); border-color: rgba(79,142,247,.2); }
.frame-context.mw    { color: var(--purple); background: var(--purple-dim); border-color: rgba(198,120,221,.2); }
.frame-context.error { color: var(--danger); background: var(--danger-dim); border-color: rgba(241,76,76,.2); }
.fw-collapsed { display: flex; align-items: center; gap: 10px; padding: 9px 14px; background: rgba(0,0,0,.15); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; font-size: 12px; color: var(--muted); margin-bottom: 5px; transition: all .13s; }
.fw-collapsed:hover { background: rgba(255,255,255,.03); color: var(--muted2); border-color: var(--border2); }
.fw-collapsed i { font-size: 14px; }
.frame-body { display: none; border-top: 1px solid var(--border); }
.frame.open .frame-body { display: block; }

/* ── Args (with object property expansion) ── */
.args-panel { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,.04); background: rgba(0,0,0,.15); }
.args-title { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 10px; }
.arg-item { margin-bottom: 8px; }
.arg-item:last-child { margin-bottom: 0; }
.arg-row { display: grid; grid-template-columns: 22px 1fr; gap: 10px; align-items: start; font-size: 13px; }
.arg-idx { color: var(--muted); text-align: right; padding-top: 1px; }
.arg-type-badge { display: inline-block; font-size: 11px; background: rgba(255,255,255,.05); border: 1px solid var(--border); padding: 1px 7px; border-radius: 3px; color: var(--muted2); margin-bottom: 4px; }
.arg-type-badge.object { color: var(--s-fn); background: var(--purple-dim); border-color: rgba(198,120,221,.15); }
.arg-type-badge.int    { color: var(--s-num); background: rgba(97,175,239,.07); border-color: rgba(97,175,239,.15); }
.obj-props { background: rgba(0,0,0,.3); border: 1px solid var(--border); border-radius: 5px; padding: 8px 12px; font-size: 12.5px; }
.obj-prop-row { display: flex; gap: 10px; padding: 2px 0; }
.obj-prop-key { color: var(--muted2); flex-shrink: 0; min-width: 100px; }
.obj-prop-val { color: var(--text); }
.obj-prop-val.str  { color: var(--s-str); }
.obj-prop-val.num  { color: var(--s-num); }
.obj-prop-val.ref  { color: var(--s-fn); }
.obj-prop-val.muted{ color: var(--muted); }
.val-int { color: var(--s-num); }

/* ── Route + Middleware block in Request panel ── */
.route-block { background: rgba(0,0,0,.25); border: 1px solid var(--border); border-radius: 6px; padding: 14px 16px; margin-bottom: 0; }
.route-pill { display: inline-flex; align-items: center; gap: 10px; padding: 8px 13px; border-radius: 5px; background: rgba(0,0,0,.35); border: 1px solid var(--border); font-size: 13.5px; margin-bottom: 10px; }
.method-badge { font-size: 11px; font-weight: 700; letter-spacing: .06em; padding: 3px 8px; border-radius: 3px; background: var(--ok-dim); border: 1px solid rgba(39,192,122,.2); color: var(--ok); }
.route-pattern { color: var(--text); }
.route-pattern .param { color: var(--warn); }
.params-table { width: 100%; font-size: 13px; border-collapse: collapse; }
.params-table tr { border-bottom: 1px solid var(--border); }
.params-table tr:last-child { border-bottom: none; }
.params-table td { padding: 7px 0; }
.params-table .pk { color: var(--muted2); padding-right: 16px; }
.params-table .pv { color: var(--s-num); }
.mw-list { display: flex; flex-direction: column; gap: 4px; }
.mw-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 4px; background: rgba(0,0,0,.2); border: 1px solid var(--border); font-size: 13px; color: var(--text); }
.mw-item i { font-size: 14px; color: var(--muted); }
.mw-item.active { border-color: rgba(198,120,221,.2); background: var(--purple-dim); }
.mw-item.active i { color: var(--purple); }
.mw-tag { margin-left: auto; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 1px 6px; border-radius: 3px; background: var(--warn-dim); color: var(--warn); border: 1px solid rgba(240,162,69,.2); }

/* ── DI / container tree ── */
.di-tree { font-size: 13px; line-height: 1.9; }
.di-node { position: relative; }
.di-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.di-indent { padding-left: 20px; border-left: 1px solid var(--border); margin-left: 9px; }
.di-connector { color: var(--muted); font-size: 11px; margin-right: 2px; }
.di-cls { color: var(--s-cls); }
.di-ref { color: var(--muted); font-size: 12px; }
.di-row.failed .di-cls { color: var(--danger); }
.di-row.resolved .di-cls { color: #f9e2af; }
.di-resolved { display: inline-flex; align-items: center; gap: 4px; color: var(--ok); font-size: 12px; }
.di-check { color: var(--ok); font-weight: 700; }
.di-fail-badge { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 1px 6px; border-radius: 3px; background: var(--danger-dim); border: 1px solid rgba(241,76,76,.2); color: var(--danger); }
.di-fail-detail { margin: 6px 0 8px 28px; padding: 10px 14px; background: var(--danger-dim); border: 1px solid rgba(241,76,76,.2); border-radius: 5px; font-size: 12.5px; color: var(--text); line-height: 1.7; }
.di-fail-detail strong { color: var(--danger); }

/* ── Solution block (inline at bottom) ── */
.solution { background: linear-gradient(160deg, rgba(39,192,122,.07), rgba(39,192,122,.02)); border: 1px solid rgba(39,192,122,.2); border-radius: 8px; padding: 18px 20px; margin-bottom: 12px; }
.solution-head { display: flex; align-items: center; gap: 9px; font-size: 14px; font-weight: 600; color: var(--ok); margin-bottom: 10px; }
.solution-head i { font-size: 18px; }
.solution-body { font-size: 13px; color: var(--text); line-height: 1.8; }
.solution-body code { background: rgba(0,0,0,.4); padding: 2px 7px; border-radius: 3px; color: var(--s-cls); font-family: inherit; }
.methods-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 6px; margin-top: 10px; }
.method-chip { padding: 4px 10px; border-radius: 4px; font-size: 12px; text-align: center; background: rgba(255,255,255,.04); border: 1px solid var(--border); color: var(--muted2); }
.method-chip.similar { background: var(--ok-dim); border-color: rgba(39,192,122,.25); color: var(--ok); }
CSS;
    }

    /**
     * Returns toolbar-scoped CSS variables and component styles. #AI:toolbarCss
     *
     * All selectors are scoped to #skim-tb to prevent style leakage into
     * the host page. Uses the same design tokens as css() but with a
     * --tb- prefix to avoid conflicts with user CSS custom properties.
     */
    public static function toolbarCss(): string {
        return <<<'CSS'
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
CSS;
    }

    /**
     * Returns the Tabler Icons CDN link tag. #AI:iconFont
     */
    public static function iconFont(): string {
        return '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">';
    }
}

#AI:class
#AI symbol: Skim\Dev\DevTheme
#AI source_path: src/Dev/DevTheme.php
#AI title: DevTheme
#AI description: Shared CSS design system for dev tool HTML output — dark theme tokens, component classes, and toolbar styles.
#AI role: CSS design system provider
#AI layer: dev
#AI badges: [dev; debug; css; theme; design-system]
#AI intro: `DevTheme` provides the shared CSS design system used by all dev tool renderers (error page, toolbar, future debug panels). It returns CSS strings for embedding in `<style>` blocks — no external .css files needed.
#AI lifecycle: stateless — returns static CSS strings on each call
#AI fallback: none — pure string output
#AI test_seam: assert css() and toolbarCss() contain expected selectors and custom properties
#AI invariants: [css() returns unprefixed selectors for standalone pages; toolbarCss() returns #skim-tb-scoped selectors; both use the same color palette]
#AI core_behaviors: [Provides CSS custom properties for colors, typography, and spacing; Provides shared component classes (kv-grid, badges, buttons, code-box, tabs, toast); Provides toolbar-scoped CSS that avoids host page conflicts]
#AI owns: none — stateless
#AI entry_points: [css; toolbarCss; iconFont]
#AI config_reads: []
#AI non_goals: [Does not load external CSS files; Does not integrate with the Vite asset pipeline; Does not compile or minify CSS]
#AI side_effects: []
#AI flow: DevTheme::css() → CSS string; DevTheme::toolbarCss() → scoped CSS string
#AI lifecycle_steps: [called by templates; returns static CSS string; embedded in <style> block]
#AI section_order: [CSS Providers; Utilities]
#AI architectural_notes: Keeps CSS co-located with PHP dev tools for zero-config deployment. The toolbar uses --tb- prefixed variables to avoid conflicts with user page CSS.

#AI:css
#AI group: CSS Providers
#AI frequency: medium
#AI signature: public static function css(): string
#AI contract: Returns the complete shared CSS for full-page dev tools — custom properties, base reset, scrollbar, and shared component classes (kv-grid, badges, buttons, code-box, tabs, toast).
#AI return_detail: {type: string | desc: Complete CSS string for embedding in a <style> block.}

#AI:toolbarCss
#AI group: CSS Providers
#AI frequency: medium
#AI signature: public static function toolbarCss(): string
#AI contract: Returns toolbar-scoped CSS with --tb- prefixed variables and #skim-tb selectors to prevent style leakage into the host page.
#AI return_detail: {type: string | desc: Scoped CSS string for the debug toolbar.}

#AI:iconFont
#AI group: Utilities
#AI frequency: low
#AI signature: public static function iconFont(): string
#AI contract: Returns the Tabler Icons webfont CDN <link> tag used by both error page and toolbar.
#AI return_detail: {type: string | desc: HTML <link> tag for Tabler Icons CDN.}
