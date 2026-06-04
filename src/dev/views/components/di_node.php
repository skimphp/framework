<?php
/**
 * DI tree node component — recursive renderer for a single container resolution node.
 *
 * Expected data:
 *   $node — ['cls' => string, 'ref' => string, 'failed' => bool, 'detail' => string, 'children' => array]
 *   $depth — current nesting depth (0 = root)
 *
 * @var array $node
 * @var int   $depth
 */

$node  = $node  ?? [];
$depth = $depth ?? 0;

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$cls   = (string) ($node['cls']   ?? '');
$ref   = (string) ($node['ref']   ?? '');
$failed = (bool)   ($node['failed'] ?? false);
$detail = (string) ($node['detail'] ?? '');
$children = (array) ($node['children'] ?? []);

$connector = $depth === 0 ? '' : '└─';
?>
<div class="di-node">
  <div class="di-row<?= $failed ? ' failed' : '' ?>">
    <?php if ($connector !== ''): ?>
    <span class="di-connector"><?= $connector ?></span>
    <?php endif ?>
    <span class="di-cls"><?= $e($cls) ?></span>
    <?php if ($failed): ?>
    <span class="di-fail-badge"><?= $e($ref !== '' ? $ref : 'failed') ?></span>
    <?php elseif ($ref !== ''): ?>
    <span class="di-ref" style="color:var(--ok)"><?= $e($ref) ?></span>
    <?php endif ?>
  </div>
  <?php if ($failed && $detail !== ''): ?>
  <div class="di-fail-detail"><?= $detail /* detail is pre-built HTML, controlled by collector */ ?></div>
  <?php endif ?>
  <?php if ($children !== []): ?>
  <div class="di-indent">
    <?php foreach ($children as $child): ?>
      <?= $this->include('components/di_node', ['node' => $child, 'depth' => $depth + 1]) ?>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</div>
