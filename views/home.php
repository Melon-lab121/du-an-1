<?php $this->extend('layouts/main', ['title' => 'Trang chủ', 'description' => 'Mua sắm trực tuyến, thanh toán SePay an toàn.']) ?>

<section class="hero">
    <div class="container hero-inner">
        <div>
            <span class="badge">Mới · Ưu đãi tháng này</span>
            <h1>Mua sắm thông minh,<br>thanh toán trong <em>3 giây</em>.</h1>
            <p class="lead">Quét VietQR chuyển khoản, QR Napas hoặc thẻ qua SePay. Miễn phí giao hàng cho đơn từ <?= money(config('shop.free_shipping_threshold')) ?>.</p>
            <div class="hero-actions">
                <a class="btn btn-lg" href="<?= url('/products') ?>">Khám phá sản phẩm</a>
                <a class="btn btn-lg btn-ghost" href="#featured">Sản phẩm nổi bật</a>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="orb orb-1"></div><div class="orb orb-2"></div>
            <div class="glass-card">
                <small>Thanh toán VietQR Napas 24/7</small>
                <strong>Miễn phí vận chuyển toàn quốc</strong>
                <span class="pill pill-success">SePay ✓</span>
            </div>
        </div>
    </div>
</section>

<?php if ($categories): ?>
<?php
$getCategorySvg = function (string $slug, string $name): string {
    $slugLower = strtolower($slug . ' ' . $name);
    if (str_contains($slugLower, 'bed') || str_contains($slugLower, 'mattress') || str_contains($slugLower, 'giuong') || str_contains($slugLower, 'nem')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/></svg>';
    }
    if (str_contains($slugLower, 'table') || str_contains($slugLower, 'chair') || str_contains($slugLower, 'ban') || str_contains($slugLower, 'ghe')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h18M4 8v11M20 8v11M8 8v4M16 8v4"/></svg>';
    }
    if (str_contains($slugLower, 'sofa') || str_contains($slugLower, 'armchair') || str_contains($slugLower, 'couch')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11v-5a3 3 0 0 1 6 0v5M4 11h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2zM6 19v2M18 19v2"/></svg>';
    }
    if (str_contains($slugLower, 'light') || str_contains($slugLower, 'lamp') || str_contains($slugLower, 'den')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21h6M12 3a6 6 0 0 0-4 10.45V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3.55A6 6 0 0 0 12 3z"/></svg>';
    }
    if (str_contains($slugLower, 'plant') || str_contains($slugLower, 'flower') || str_contains($slugLower, 'cay') || str_contains($slugLower, 'hoa')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10a6 6 0 0 0-6-6H3v3a6 6 0 0 0 6 6h3M12 10a6 6 0 0 1 6-6h3v3a6 6 0 0 1-6 6h-3M12 10v11"/></svg>';
    }
    if (str_contains($slugLower, 'electronic') || str_contains($slugLower, 'phone') || str_contains($slugLower, 'laptop') || str_contains($slugLower, 'dien-tu')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
    }
    if (str_contains($slugLower, 'storage') || str_contains($slugLower, 'drawer') || str_contains($slugLower, 'dresser') || str_contains($slugLower, 'box') || str_contains($slugLower, 'tu') || str_contains($slugLower, 'ke')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 6v.01M9 12v.01M9 18v.01"/></svg>';
    }
    if (str_contains($slugLower, 'decor') || str_contains($slugLower, 'accessor') || str_contains($slugLower, 'trang-tri')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3z"/></svg>';
    }
    if (str_contains($slugLower, 'kitchen') || str_contains($slugLower, 'appliance') || str_contains($slugLower, 'tableware') || str_contains($slugLower, 'bep')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11h16a1 1 0 0 1 1 1v2a6 6 0 0 1-6 6H9a6 6 0 0 1-6-6v-2a1 1 0 0 1 1-1zM9 4v3M15 4v3M12 4v3"/></svg>';
    }
    if (str_contains($slugLower, 'bath') || str_contains($slugLower, 'tam')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16a1 1 0 0 1 1 1v3a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-3a1 1 0 0 1 1-1zM6 12V5a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v2"/></svg>';
    }
    if (str_contains($slugLower, 'rug') || str_contains($slugLower, 'mat') || str_contains($slugLower, 'floor') || str_contains($slugLower, 'tham')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 5v14M17 5v14M3 9h18M3 15h18"/></svg>';
    }
    if (str_contains($slugLower, 'baby') || str_contains($slugLower, 'kid') || str_contains($slugLower, 'tre-em')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M9 15c1 1.5 5 1.5 6 0"/></svg>';
    }
    if (str_contains($slugLower, 'smart')) {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 13a3 3 0 0 1 6 0M10.5 16a1.5 1.5 0 0 1 3 0"/></svg>';
    }
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
};
?>
<section class="container section" id="categories-section">
    <div class="section-head category-head">
        <h2>Danh mục nổi bật</h2>
        <?php if (count($categories) > 5): ?>
            <button type="button" class="btn btn-sm btn-ghost category-toggle-btn" data-category-toggle aria-expanded="false">
                <span class="toggle-text">Xem tất cả (<?= count($categories) ?>)</span>
                <span class="toggle-icon">▾</span>
            </button>
        <?php endif ?>
    </div>
    <div class="category-grid" data-category-grid data-expanded="false">
        <?php foreach ($categories as $cat): ?>
            <a class="category-tile" href="<?= route('category.show', ['slug' => $cat['slug']]) ?>">
                <div class="category-icon" aria-hidden="true">
                    <?= $getCategorySvg($cat['slug'] ?? '', $cat['name'] ?? '') ?>
                </div>
                <strong class="category-title"><?= e($cat['name']) ?></strong>
                <span class="muted category-count"><?= (int) $cat['product_count'] ?> sản phẩm</span>
            </a>
        <?php endforeach ?>
    </div>
    <?php if (count($categories) > 5): ?>
        <div class="category-collapse-footer" data-category-footer hidden>
            <button type="button" class="btn btn-ghost category-collapse-bottom-btn" data-category-collapse-bottom>
                Thu gọn ▴
            </button>
        </div>
    <?php endif ?>
</section>
<?php endif ?>

<?php if ($featured): ?>
<section class="container section" id="featured">
    <div class="section-head"><h2>Nổi bật</h2><a href="<?= url('/products') ?>">Xem tất cả →</a></div>
    <div class="product-grid">
        <?php foreach ($featured as $product): ?>
            <?= $this->insert('partials/product-card', ['product' => $product]) ?>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<section class="container section">
    <div class="section-head"><h2>Mới nhất</h2></div>
    <?php if ($latest): ?>
        <div class="product-grid">
            <?php foreach ($latest as $product): ?>
                <?= $this->insert('partials/product-card', ['product' => $product]) ?>
            <?php endforeach ?>
        </div>
    <?php else: ?>
        <div class="empty">Chưa có sản phẩm. Chạy <code>php cli.php install</code> để thêm dữ liệu mẫu.</div>
    <?php endif ?>
</section>
