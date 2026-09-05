<?php
/**
 * Badge component — renders a small status badge.
 *
 * Expected data:
 *   $text  — badge label text
 *   $class — variant: '', 'ok', 'warn', 'danger'
 *   $icon  — Tabler icon class (optional, e.g. 'ti-alert-triangle')
 *
 * @var string $text
 * @var string $class
 * @var string $icon
 */
$text  = $text  ?? '';
$class = $class ?? '';
$icon  = $icon  ?? '';
?>
<span class="badge <?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($icon !== ''): ?><i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i> <?php endif ?>
    <?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
</span>
