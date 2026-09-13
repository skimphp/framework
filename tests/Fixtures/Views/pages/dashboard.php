<?php $this->layout('layouts/base') ?>
<?php $this->section('content') ?>

<!-- @fragment stats_widget -->
<div id="stats">
    <span><?= e($stats_value) ?></span>
</div>
<!-- @end -->

<!-- @fragment user_list -->
<ul>
    <?php foreach ($users as $user): ?>
        <li><?= e($user) ?></li>
    <?php endforeach; ?>
</ul>
<!-- @end -->

<p>Dashboard for <?= e($name) ?></p>
<?php $this->endSection() ?>
