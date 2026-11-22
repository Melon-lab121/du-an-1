<?php
/** @var App\Core\View $this */
$title = isset($title) ? $title . ' — ' . $appName : $appName;
$user = auth()->user();
$navCategories ??= [];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?></title>
    <?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif ?>
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <?= $this->section('head') ?>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= url('/') ?>"><span class="logo-mark">◆</span> <?= e($appName) ?></a>

        <form class="search" action="<?= url('/products') ?>" method="get" role="search">
            <input type="search" name="q" value="<?= e(request()->query('q', '')) ?>" placeholder="Tìm sản phẩm..." aria-label="Tìm sản phẩm">
            <button type="submit" aria-label="Tìm">⌕</button>
        </form>

        <nav class="header-actions">
            <a class="cart-link" href="<?= url('/cart') ?>">
                🛒 <span class="hide-sm">Giỏ hàng</span>
                <span class="cart-count" data-cart-count><?= cart()->count() ?></span>
            </a>
            <?php if ($user): ?>
                <details class="dropdown">
                    <summary>👤 <span class="hide-sm"><?= e($user['name']) ?></span></summary>
                    <div class="dropdown-menu">
                        <?php if (auth()->isAdmin()): ?><a href="<?= url('/admin') ?>">Trang quản trị</a><?php endif ?>
                        <a href="<?= url('/orders') ?>">Đơn hàng của tôi</a>
                        <form action="<?= url('/logout') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="link">Đăng xuất</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a href="<?= url('/login') ?>">Đăng nhập</a>
            <?php endif ?>
        </nav>
    </div>
    <?php if ($navCategories): ?>
        <nav class="category-nav">
            <div class="container">
                <a class="<?= is_active('/') ?> nav-home-link" href="<?= url('/') ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 4px;"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Trang chủ
                </a>
                <a class="<?= is_active('/products') ?>" href="<?= url('/products') ?>">Tất cả</a>
                <?php foreach ($navCategories as $cat): ?>
                    <a class="<?= is_active('/category/' . $cat['slug']) ?>" href="<?= route('category.show', ['slug' => $cat['slug']]) ?>"><?= e($cat['name']) ?></a>
                <?php endforeach ?>
            </div>
        </nav>
    <?php endif ?>
</header>

<main>
    <?= $this->insert('partials/flash') ?>
    <?= $this->section('content') ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <strong><?= e($appName) ?></strong>
            <p class="muted">Base code thương mại điện tử PHP thuần — dễ mở rộng, thanh toán SePay.</p>
        </div>
        <div>
            <strong>Hỗ trợ</strong>
            <p class="muted">Giao hàng toàn quốc · Đổi trả 7 ngày<br>Miễn phí ship đơn từ <?= money(config('shop.free_shipping_threshold')) ?></p>
        </div>
        <div>
            <strong>Thanh toán</strong>
            <p class="muted">COD · SePay VietQR · QR Napas · Thẻ</p>
        </div>
    </div>
    <div class="container footer-bottom muted">© <?= date('Y') ?> <?= e($appName) ?></div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<?= $this->section('scripts') ?>
</body>
</html>
