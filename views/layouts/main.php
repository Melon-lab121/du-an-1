<?php
/** @var App\Core\View $this */
$title = isset($title) ? $title . ' — ' . $appName : $appName;
$user = auth()->user();
$navCategories ??= [];

$catSvg = static function (string $slug, string $name): string {
    $s = strtolower($slug . ' ' . $name);
    if (str_contains($s, 'bed') || str_contains($s, 'mattress') || str_contains($s, 'giuong') || str_contains($s, 'nem')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/></svg>';
    }
    if (str_contains($s, 'table') || str_contains($s, 'chair') || str_contains($s, 'ban') || str_contains($s, 'ghe')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h18M4 8v11M20 8v11M8 8v4M16 8v4"/></svg>';
    }
    if (str_contains($s, 'sofa') || str_contains($s, 'armchair') || str_contains($s, 'couch')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11v-5a3 3 0 0 1 6 0v5M4 11h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2zM6 19v2M18 19v2"/></svg>';
    }
    if (str_contains($s, 'light') || str_contains($s, 'lamp') || str_contains($s, 'den')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21h6M12 3a6 6 0 0 0-4 10.45V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3.55A6 6 0 0 0 12 3z"/></svg>';
    }
    if (str_contains($s, 'plant') || str_contains($s, 'flower') || str_contains($s, 'cay') || str_contains($s, 'hoa')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10a6 6 0 0 0-6-6H3v3a6 6 0 0 0 6 6h3M12 10a6 6 0 0 1 6-6h3v3a6 6 0 0 1-6 6h-3M12 10v11"/></svg>';
    }
    if (str_contains($s, 'electronic') || str_contains($s, 'phone') || str_contains($s, 'laptop') || str_contains($s, 'dien-tu')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
    }
    if (str_contains($s, 'storage') || str_contains($s, 'drawer') || str_contains($s, 'dresser') || str_contains($s, 'box') || str_contains($s, 'tu') || str_contains($s, 'ke')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 6v.01M9 12v.01M9 18v.01"/></svg>';
    }
    if (str_contains($s, 'decor') || str_contains($s, 'accessor') || str_contains($s, 'trang-tri')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3z"/></svg>';
    }
    if (str_contains($s, 'kitchen') || str_contains($s, 'appliance') || str_contains($s, 'tableware') || str_contains($s, 'bep')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11h16a1 1 0 0 1 1 1v2a6 6 0 0 1-6 6H9a6 6 0 0 1-6-6v-2a1 1 0 0 1 1-1zM9 4v3M15 4v3M12 4v3"/></svg>';
    }
    if (str_contains($s, 'bath') || str_contains($s, 'tam')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16a1 1 0 0 1 1 1v3a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-3a1 1 0 0 1 1-1zM6 12V5a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v2"/></svg>';
    }
    if (str_contains($s, 'rug') || str_contains($s, 'mat') || str_contains($s, 'floor') || str_contains($s, 'tham')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 5v14M17 5v14M3 9h18M3 15h18"/></svg>';
    }
    if (str_contains($s, 'baby') || str_contains($s, 'kid') || str_contains($s, 'tre-em')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M9 15c1 1.5 5 1.5 6 0"/></svg>';
    }
    if (str_contains($s, 'smart')) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 13a3 3 0 0 1 6 0M10.5 16a1.5 1.5 0 0 1 3 0"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
};
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
<div class="top-utility-bar">
    <div class="container top-utility-inner">
        <span class="top-utility-meta">VN | Tiếng Việt</span>
        <span class="top-utility-promo">Miễn phí giao hàng toàn quốc đơn từ <?= money(config('shop.free_shipping_threshold')) ?></span>
        <span class="top-utility-meta">SePay VietQR · COD · Đổi trả 7 ngày</span>
    </div>
</div>

<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= url('/') ?>">
            <img src="<?= asset('assets/images/logo.jpg') ?>" alt="<?= e($appName) ?>" style="height:36px;width:auto;display:block;border-radius:4px;object-fit:cover;">
            <span class="logo-brand-name"><?= e($appName) ?></span>
        </a>

        <form class="search" action="<?= url('/products') ?>" method="get" role="search">
            <input type="search" name="q" value="<?= e(request()->query('q', '')) ?>" placeholder="Bạn đang tìm món nội thất nào?" aria-label="Tìm sản phẩm">
            <button type="submit" aria-label="Tìm">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
        </form>

        <nav class="header-actions">
            <?php if ($user): ?>
                <details class="dropdown">
                    <summary class="header-pill-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="hide-sm"><?= e($user['name']) ?></span>
                    </summary>
                    <div class="dropdown-menu">
                        <?php if (auth()->isAdmin()): ?><a href="<?= url('/admin') ?>">Trang quản trị</a><?php endif ?>
                        <a href="<?= url('/orders') ?>">Đơn hàng của tôi</a>
                        <a href="<?= url('/account') ?>">Thông tin tài khoản</a>
                        <form action="<?= url('/logout') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="link">Đăng xuất</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a class="header-pill-link" href="<?= url('/login') ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="hide-sm">Đăng nhập</span>
                </a>
            <?php endif ?>

            <a class="header-pill-link cart-link" href="<?= url('/cart') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="hide-sm">Giỏ hàng</span>
                <span class="cart-count" data-cart-count><?= cart()->count() ?></span>
            </a>
        </nav>
    </div>

    <nav class="category-nav" aria-label="Danh mục sản phẩm">
        <div class="container category-nav-inner">
            <button type="button" class="cat-scroll-btn" data-cat-prev aria-label="Cuộn danh mục sang trái" hidden>‹</button>
            <div class="category-strip" data-category-strip>
                <a class="category-chip <?= is_active('/') ?>" href="<?= url('/') ?>">
                    <span class="category-chip-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span>Trang chủ</span>
                </a>
                <a class="category-chip <?= is_active('/products') ?>" href="<?= url('/products') ?>">
                    <span class="category-chip-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </span>
                    <span>Tất cả</span>
                </a>
                <?php foreach ($navCategories as $cat): ?>
                    <a class="category-chip <?= is_active('/category/' . $cat['slug']) ?>" href="<?= route('category.show', ['slug' => $cat['slug']]) ?>">
                        <span class="category-chip-icon" aria-hidden="true">
                            <?= $catSvg((string) ($cat['slug'] ?? ''), (string) ($cat['name'] ?? '')) ?>
                        </span>
                        <span><?= e($cat['name']) ?></span>
                    </a>
                <?php endforeach ?>
            </div>
            <button type="button" class="cat-scroll-btn" data-cat-next aria-label="Cuộn danh mục sang phải" hidden>›</button>
        </div>
    </nav>
</header>

<main>
    <?= $this->insert('partials/flash') ?>
    <?= $this->section('content') ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <span class="footer-col-title"><?= e($appName) ?></span>
                <p class="muted small" style="margin:0 0 14px;">Nội thất phong cách Bắc Âu tối giản, chất lượng bền vững cho mọi không gian sống hiện đại.</p>
                <a class="btn btn-sm" href="<?= $user ? url('/account') : url('/login') ?>">
                    <?= $user ? 'Tài khoản của tôi' : 'Đăng nhập / Thành viên' ?>
                </a>
            </div>
            <div>
                <span class="footer-col-title">Mua sắm</span>
                <ul class="footer-links">
                    <li><a href="<?= url('/') ?>">Trang chủ</a></li>
                    <li><a href="<?= url('/products') ?>">Tất cả sản phẩm</a></li>
                    <li><a href="<?= url('/cart') ?>">Giỏ hàng</a></li>
                    <li><a href="<?= url('/checkout') ?>">Thanh toán</a></li>
                </ul>
            </div>
            <div>
                <span class="footer-col-title">Hỗ trợ khách hàng</span>
                <ul class="footer-links">
                    <li><a href="<?= url('/orders') ?>">Tra cứu đơn hàng</a></li>
                    <li><span class="muted">Đổi trả linh hoạt trong 7 ngày</span></li>
                    <li><span class="muted">Miễn phí ship từ <?= money(config('shop.free_shipping_threshold')) ?></span></li>
                </ul>
            </div>
            <div>
                <span class="footer-col-title">Thanh toán an toàn</span>
                <ul class="footer-links">
                    <li><span>COD (Nhận hàng thanh toán)</span></li>
                    <li><span>SePay VietQR / QR Napas</span></li>
                    <li><span>Thẻ ngân hàng nội địa &amp; quốc tế</span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom muted">
            <span>© <?= date('Y') ?> <?= e($appName) ?> — Nội thất tối giản phong cách Bắc Âu</span>
            <span>VN | Tiếng Việt</span>
        </div>
    </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<?= $this->section('scripts') ?>
</body>
</html>
