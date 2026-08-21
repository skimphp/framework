<?php $this->layout('layouts/base') ?>
<?php $this->section('header') ?>
<h1>Home Page</h1>
<?php $this->end_section() ?>

<?php $this->section('content') ?>
<p>Welcome, <?= e($name) ?>!</p>
<?php $this->end_section() ?>
