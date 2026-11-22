<?php $this->extend('layouts/main', ['title' => 'Không tìm thấy']) ?>

<section class="container section narrow">
    <div class="card result-card">
        <div class="result-icon fail">404</div>
        <h1><?= e($message ?? 'Không tìm thấy trang') ?></h1>
        <p class="muted">Trang bạn tìm có thể đã bị xoá hoặc đổi địa chỉ.</p>
        <form class="search search-lg" action="<?= url('/products') ?>" method="get">
            <input type="search" name="q" placeholder="Tìm sản phẩm..." aria-label="Tìm sản phẩm">
            <button type="submit">⌕</button>
        </form>
        <div class="hero-actions center"><a class="btn" href="<?= url('/') ?>">Về trang chủ</a></div>
    </div>
</section>
