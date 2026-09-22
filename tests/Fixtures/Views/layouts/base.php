<!DOCTYPE html>
<html>
<head><title><?= e($title ?? 'Default') ?></title></head>
<body>
<header><?= $this->block('header') ?></header>
<main><?= $this->block('content') ?></main>
<footer><?= $this->block('footer', 'Default Footer') ?></footer>
</body>
</html>
