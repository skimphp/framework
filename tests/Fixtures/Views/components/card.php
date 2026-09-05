<div class="card">
    <?php if (hasPart('header')): ?>
        <div class="card-header"><?= part('header') ?></div>
    <?php else: ?>
        <div class="card-header"><h3><?= e($title ?? 'Card') ?></h3></div>
    <?php endif; ?>

    <div class="card-body"><?= part() ?></div>

    <?php if (hasPart('footer')): ?>
        <div class="card-footer"><?= part('footer') ?></div>
    <?php endif; ?>
</div>
