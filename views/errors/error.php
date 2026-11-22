<?php
/** @var int $status @var string $message @var ?Throwable $exception */
$this->extend('layouts/main', ['title' => 'Lỗi ' . $status]);
?>
<section class="container section narrow">
    <div class="card result-card">
        <div class="result-icon fail"><?= (int) $status ?></div>
        <h1><?= e($message) ?></h1>
        <div class="hero-actions center">
            <a class="btn" href="<?= url('/') ?>">Về trang chủ</a>
        </div>
    </div>
    <?php if ($exception): ?>
        <div class="card debug">
            <strong><?= e($exception::class) ?></strong>: <?= e($exception->getMessage()) ?>
            <p class="muted"><?= e($exception->getFile()) ?>:<?= $exception->getLine() ?></p>
            <pre><?= e($exception->getTraceAsString()) ?></pre>
        </div>
    <?php endif ?>
</section>
