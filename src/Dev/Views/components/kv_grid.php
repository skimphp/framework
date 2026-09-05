<?php
/**
 * KV grid component — renders key-value pairs in a 2-column grid.
 *
 * Expected data:
 *   $title     — section title (optional)
 *   $dot       — dot color: 'accent' (default) or 'warn'
 *   $items     — array of ['key' => string, 'value' => string, 'class' => string (optional)]
 *   $badge     — badge text shown in title (optional)
 *   $badge_cls — badge class: '', 'ok', 'warn', 'danger' (optional)
 *
 * @var string $title
 * @var string $dot
 * @var array  $items
 * @var string $badge
 * @var string $badge_cls
 */
$title     = $title     ?? '';
$dot       = $dot       ?? 'accent';
$items     = $items     ?? [];
$badge     = $badge     ?? '';
$badge_cls = $badge_cls ?? '';

if ($items === []) {
    return;
}
?>
<?php if ($title !== ''): ?>
<div class="kv-title">
    <span class="kv-dot<?= $dot === 'warn' ? ' warn' : '' ?>"></span>
    <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
    <?php if ($badge !== ''): ?>
    <span class="badge <?= htmlspecialchars($badge_cls, ENT_QUOTES, 'UTF-8') ?>" style="margin-left:auto"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif ?>
</div>
<?php endif ?>
<div class="kv-grid">
    <?php foreach ($items as $item): ?>
    <div class="kv-cell">
        <span class="kv-k"><?= htmlspecialchars((string)($item['key'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <?php
        $val   = (string)($item['value'] ?? '');
        $cls   = $item['class']  ?? '';
        $is_secret = !empty($item['secret']);
        ?>
        <?php if ($is_secret): ?>
        <span class="kv-v secret" onclick="revealSecret(this)" data-secret="<?= htmlspecialchars($item['secret_value'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></span>
        <?php else: ?>
        <span class="kv-v <?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif ?>
    </div>
    <?php endforeach ?>
</div>
