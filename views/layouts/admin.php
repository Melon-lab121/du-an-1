<?php
/** @var App\Core\View $this */
$title = isset($title) ? $title . ' — Quản trị' : 'Quản trị';
$menu = [
    ['/admin', 'Tổng quan', '▦', '/admin'],
    ['/admin/orders', 'Đơn hàng', '🧾', '/admin/orders*'],
    ['/admin/products', 'Sản phẩm', '📦', '/admin/products*'],
    ['/admin/categories', 'Danh mục', '🗂', '/admin/categories*'],
    ['/admin/vouchers', 'Voucher', '🏷', '/admin/vouchers*'],
    ['/admin/comments', 'Bình luận', '🏷', '/admin/comments*'],
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="logo" href="<?= url('/admin') ?>"><span class="logo-mark">◆</span> Admin</a>
        <nav>
            <?php foreach ($menu as [$href, $label, $icon, $pattern]): ?>
                <a class="<?= is_active($pattern) ?>" href="<?= url($href) ?>"><span><?= $icon ?></span> <?= $label ?></a>
            <?php endforeach ?>
        </nav>
        <div class="admin-sidebar-foot">
            <a href="<?= url('/') ?>" target="_blank" rel="noopener">↗ Xem cửa hàng</a>
            <form action="<?= url('/logout') ?>" method="post"><?= csrf_field() ?><button class="link" type="submit">Đăng xuất</button></form>
        </div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <h1 class="h2"><?= e($heading ?? ($title)) ?></h1>
            <div class="muted">👤 <?= e(auth()->user()['name'] ?? '') ?></div>
        </header>
        <?= $this->insert('partials/flash') ?>
        <div class="admin-content">
            <?= $this->section('content') ?>
        </div>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<?= $this->section('scripts') ?>
</body>
</html>
