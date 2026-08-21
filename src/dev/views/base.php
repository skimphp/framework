<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title ?? 'skim framework', ENT_QUOTES, 'UTF-8') ?></title>
<?= \skim\dev\dev_theme::icon_font() ?>
<style>
<?= \skim\dev\dev_theme::css() ?>
<?= $this->slot('page_css') ?>
</style>
</head>
<body>
<div class="wrap">
    <?= $this->slot('content') ?>

    <div class="foot">
        <strong>skim</strong> framework &middot; PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?> &middot; Debug mode enabled &middot;
        <a href="https://github.com/skim/framework" target="_blank">github</a> &middot;
        <a href="https://skim.dev/docs" target="_blank">docs</a>
    </div>
</div>

<div class="toast" id="toast">
    <i class="ti ti-check"></i>
    <span id="toast-msg">Copied to clipboard</span>
</div>

<script>
<?= $this->slot('page_js') ?>
</script>
</body>
</html>
