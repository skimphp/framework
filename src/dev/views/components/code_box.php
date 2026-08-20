<?php
/**
 * Code box component — renders source code with line numbers and error highlighting.
 *
 * Expected data:
 *   $file_path  — absolute file path for IDE link (optional)
 *   $file_label — display label for the file (optional, defaults to basename)
 *   $lines      — array of ['num' => int, 'code' => string (raw), 'hl' => bool]
 *   $error_line — the error line number for the header display (optional)
 *   $line_start — first visible line number (optional, for header display)
 *   $line_end   — last visible line number (optional, for header display)
 *
 * @var string $file_path
 * @var string $file_label
 * @var array  $lines
 * @var int    $error_line
 * @var int    $line_start
 * @var int    $line_end
 */
$file_path  = $file_path  ?? '';
$file_label = $file_label ?? ($file_path !== '' ? basename($file_path) : '');
$lines      = $lines      ?? [];
$error_line = $error_line ?? 0;
$line_start = $line_start ?? 0;
$line_end   = $line_end   ?? 0;
?>
<div class="code-box">
    <?php if ($file_path !== ''): ?>
    <div class="code-head">
        <span class="code-file-link" onclick="openInIde('<?= htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8') ?>', <?= (int)$error_line ?>)">
            <i class="ti ti-file-code"></i>
            <?= htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8') ?>
        </span>
        <?php if ($line_start > 0 && $error_line > 0): ?>
        <span class="code-err-at">
            <span style="color:var(--muted)">lines <?= (int)$line_start ?>–<?= (int)$line_end ?> · error at</span>
            <span class="line-num">:<?= (int)$error_line ?></span>
        </span>
        <?php endif ?>
    </div>
    <?php endif ?>
    <div class="code-body">
        <table>
            <?php foreach ($lines as $l): ?>
            <tr<?= !empty($l['hl']) ? ' class="hl"' : '' ?>>
                <td class="ln"><?= (int)$l['num'] ?></td>
                <td class="src"><?= $l['code'] ?></td>
            </tr>
            <?php endforeach ?>
        </table>
    </div>
</div>
