<?php
/**
 * Tabs component — renders a tab bar for panel switching.
 *
 * Expected data:
 *   $tabs — array of ['id' => string, 'label' => string, 'icon' => string, 'pill' => string|null, 'pill_class' => string, 'active' => bool]
 *
 * @var array $tabs
 */
$tabs = $tabs ?? [];
?>
<div class="tabs">
    <?php foreach ($tabs as $i => $t): ?>
    <div class="tab<?= !empty($t['active']) ? ' active' : '' ?>" data-tab="<?= htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($t['icon'])): ?>
        <i class="ti ti-<?= htmlspecialchars($t['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
        <?php endif ?>
        <?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?>
        <?php if (isset($t['pill']) && $t['pill'] !== null && $t['pill'] !== ''): ?>
        <span class="tab-pill <?= htmlspecialchars($t['pill_class'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$t['pill'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif ?>
    </div>
    <?php endforeach ?>
</div>
