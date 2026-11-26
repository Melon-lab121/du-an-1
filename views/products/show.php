<?php
$this->extend('layouts/main', ['title' => $product['name'], 'description' => $product['short_description']]);
$final = App\Services\PriceCalculator::unitPrice($product);
$hasSale = $final < (int) $product['price'];
$inStock = (int) $product['stock'] > 0;
$hasVariants = $variants !== null;
?>
<section class="container section">
    <div class="page-nav-bar">
        <button type="button" class="btn-back-action" onclick="if (history.length > 1) { history.back(); } else { location.href = '<?= $category ? route('category.show', ['slug' => $category['slug']]) : url('/products') ?>'; }" title="Quay lại trang trước">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            <span>Quay lại</span>
        </button>
        <nav class="breadcrumb">
            <a href="<?= url('/') ?>" class="breadcrumb-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Trang chủ</span>
            </a>
            <span class="breadcrumb-sep">/</span>
            <?php if ($category): ?>
                <a href="<?= route('category.show', ['slug' => $category['slug']]) ?>" class="breadcrumb-link"><?= e($category['name']) ?></a>
                <span class="breadcrumb-sep">/</span>
            <?php endif ?>
            <span class="breadcrumb-current"><?= e($product['name']) ?></span>
        </nav>
    </div>

    <div class="product-detail">
        <div class="product-gallery card">
            <?= $this->insert('partials/product-image', ['product' => $product]) ?>
        </div>
        <div class="product-info">
            <h1><?= e($product['name']) ?></h1>
            <p class="muted">SKU: <span data-variant-sku><?= e($product['sku'] ?? '—') ?></span></p>
            <div class="price price-lg" data-variant-price>
                <strong><?= $hasVariants ? 'Từ ' : '' ?><?= money($final) ?></strong>
                <?php if ($hasSale): ?><del><?= money($product['price']) ?></del><span class="tag tag-sale">Tiết kiệm <?= money((int) $product['price'] - $final) ?></span><?php endif ?>
            </div>
            <p><?= e($product['short_description']) ?></p>
            <p class="<?= $inStock ? 'text-success' : 'text-danger' ?>" data-variant-stock><?= $inStock ? ($hasVariants ? 'Chọn phân loại để xem tồn kho' : 'Còn ' . (int) $product['stock'] . ' sản phẩm') : 'Tạm hết hàng' ?></p>

            <form action="<?= url('/cart/add') ?>" method="post" class="buy-box" data-ajax-cart <?= $hasVariants ? 'data-variant-picker' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <?php if ($hasVariants): ?>
                    <?php // Gửi options[option_id] = value_id; server tự tìm biến thể (không cần JS). JS chỉ để hiện giá/tồn kho ngay ?>
                    <?php foreach ($variants['options'] as $option): ?>
                        <fieldset class="variant-option">
                            <legend><?= e($option['name']) ?></legend>
                            <div class="bank-codes">
                                <?php foreach ($option['values'] as $value): ?>
                                    <label class="chip">
                                        <input type="radio" name="options[<?= (int) $option['id'] ?>]" value="<?= (int) $value['id'] ?>" required>
                                        <span><?= e($value['value']) ?></span>
                                    </label>
                                <?php endforeach ?>
                            </div>
                        </fieldset>
                    <?php endforeach ?>
                    <script type="application/json" data-variant-json><?= json_encode($variants['variants'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
                <?php endif ?>
                <div class="qty" data-qty>
                    <button type="button" data-step="-1" aria-label="Giảm">−</button>
                    <input type="number" name="quantity" value="1" min="1" max="<?= max(1, (int) $product['stock']) ?>" aria-label="Số lượng">
                    <button type="button" data-step="1" aria-label="Tăng">+</button>
                </div>
                <button class="btn btn-lg btn-outline btn-detail-cart" type="submit" <?= $inStock ? '' : 'disabled' ?>>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 6px;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span>Thêm vào giỏ</span>
                </button>
                <button class="btn btn-lg btn-primary btn-detail-buy" type="submit" name="buy_now" value="1" <?= $inStock ? '' : 'disabled' ?>>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 6px;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    <span>Thanh toán ngay</span>
                </button>
            </form>

            <ul class="perks">
                <li>🚚 Miễn phí ship đơn từ <?= money(config('shop.free_shipping_threshold')) ?></li>
                <li>💳 Thanh toán SePay: VietQR / QR Napas / Thẻ</li>
                <li>↩️ Đổi trả trong 7 ngày</li>
            </ul>
        </div>
    </div>

    <?php if (!empty($product['description'])): ?>
        <div class="card prose section">
            <h2>Mô tả sản phẩm</h2>
            <?= nl2br(e($product['description'])) ?>
        </div>
    <?php endif ?>

    <?php if ($related): ?>
        <div class="section">
            <div class="section-head"><h2>Sản phẩm liên quan</h2></div>
            <div class="product-grid">
                <?php foreach ($related as $item): ?>
                    <?= $this->insert('partials/product-card', ['product' => $item]) ?>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
</section>
