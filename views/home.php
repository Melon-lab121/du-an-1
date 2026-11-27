<?php
$this->extend('layouts/main', ['title' => 'Trang chủ', 'description' => 'Nội thất phong cách Bắc Âu tối giản, thanh toán SePay an toàn.']);
$heroSlides = array_values($featured ?: $latest);
$heroMain = $heroSlides[0] ?? null;
$heroSub1 = $heroSlides[1] ?? null;
$heroSub2 = $heroSlides[2] ?? null;
?>

<section class="ikea-hero">
    <div class="container">
        <div class="ikea-bento">
            <div class="ikea-hero-primary">
                <div>
                    <span class="ikea-hero-kicker">Ưu đãi tháng này</span>
                    <h1>Thiết kế Bắc Âu,<br>cho mọi không gian.</h1>
                    <p class="lead">
                        Quét VietQR chuyển khoản, QR Napas hoặc thẻ qua SePay chỉ trong 3 giây.
                        Miễn phí giao hàng cho đơn từ <?= money(config('shop.free_shipping_threshold')) ?>.
                    </p>
                </div>
                <div class="ikea-hero-actions">
                    <a class="btn btn-lg btn-hero-light" href="<?= url('/products') ?>">Khám phá sản phẩm</a>
                    <a class="btn btn-lg btn-hero-outline" href="#featured">Sản phẩm nổi bật</a>
                </div>
            </div>

            <div class="ikea-hero-mosaic">
                <?php if ($heroMain !== null): ?>
                    <?php $mainPrice = App\Services\PriceCalculator::unitPrice($heroMain); ?>
                    <a class="ikea-hero-feature" href="<?= route('product.show', ['slug' => $heroMain['slug']]) ?>">
                        <span class="ikea-tag-blue">Nổi bật</span>
                        <?= $this->insert('partials/product-image', ['product' => $heroMain]) ?>
                        <div class="ikea-scrim">
                            <span class="ikea-scrim-title"><?= e($heroMain['name']) ?></span>
                            <div class="ikea-scrim-meta">
                                <span><?= !empty($heroMain['has_variants']) ? 'Từ ' : '' ?><?= money($mainPrice) ?></span>
                                <span class="ikea-circle-arrow" aria-hidden="true">→</span>
                            </div>
                        </div>
                    </a>
                <?php else: ?>
                    <a class="ikea-hero-feature" href="<?= url('/products') ?>" style="background:#111111;">
                        <div class="ikea-scrim">
                            <span class="ikea-scrim-title">Bộ sưu tập nội thất mới</span>
                            <div class="ikea-scrim-meta">
                                <span>Xem toàn bộ sản phẩm</span>
                                <span class="ikea-circle-arrow" aria-hidden="true">→</span>
                            </div>
                        </div>
                    </a>
                <?php endif ?>

                <div class="ikea-hero-subgrid">
                    <?php foreach ([$heroSub1, $heroSub2] as $subItem): ?>
                        <?php if ($subItem !== null): ?>
                            <?php $subPrice = App\Services\PriceCalculator::unitPrice($subItem); ?>
                            <a class="ikea-hero-tile" href="<?= route('product.show', ['slug' => $subItem['slug']]) ?>">
                                <span class="ikea-tag-blue">Gợi ý hôm nay</span>
                                <?= $this->insert('partials/product-image', ['product' => $subItem]) ?>
                                <div class="ikea-scrim">
                                    <span class="ikea-scrim-title"><?= e($subItem['name']) ?></span>
                                    <div class="ikea-scrim-meta">
                                        <span><?= !empty($subItem['has_variants']) ? 'Từ ' : '' ?><?= money($subPrice) ?></span>
                                        <span class="ikea-circle-arrow" aria-hidden="true">→</span>
                                    </div>
                                </div>
                            </a>
                        <?php else: ?>
                            <a class="ikea-hero-tile" href="<?= url('/products') ?>" style="background:#f5f5f5;">
                                <div class="ikea-scrim">
                                    <span class="ikea-scrim-title">Xem thêm thiết kế mới</span>
                                    <div class="ikea-scrim-meta">
                                        <span>Tất cả sản phẩm</span>
                                        <span class="ikea-circle-arrow" aria-hidden="true">→</span>
                                    </div>
                                </div>
                            </a>
                        <?php endif ?>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container section">
    <div class="section-head">
        <h2>Trải nghiệm mua sắm tại <?= e($appName) ?></h2>
    </div>
    <div class="ikea-color-grid">
        <a class="ikea-color-card is-blue" href="<?= url('/products') ?>">
            <div>
                <h3>Miễn phí giao hàng</h3>
                <p>Áp dụng toàn quốc cho đơn hàng từ <?= money(config('shop.free_shipping_threshold')) ?>.</p>
            </div>
            <span class="ikea-circle-arrow" aria-hidden="true">→</span>
        </a>
        <a class="ikea-color-card is-red" href="<?= url('/cart') ?>">
            <div>
                <h3>Thanh toán SePay</h3>
                <p>Quét VietQR, QR Napas hoặc thẻ ngân hàng xác nhận tự động.</p>
            </div>
            <span class="ikea-circle-arrow" aria-hidden="true">→</span>
        </a>
        <a class="ikea-color-card is-orange" href="<?= url('/cart') ?>">
            <div>
                <h3>Ưu đãi Voucher</h3>
                <p>Chọn nhanh mã giảm giá và freeship ngay trong giỏ hàng.</p>
            </div>
            <span class="ikea-circle-arrow" aria-hidden="true">→</span>
        </a>
        <a class="ikea-color-card is-yellow" href="<?= url('/products?sort=price_asc') ?>">
            <div>
                <h3>Đổi trả trong 7 ngày</h3>
                <p>An tâm kiểm tra, hỗ trợ minh chứng và hoàn tiền minh bạch.</p>
            </div>
            <span class="ikea-circle-arrow" aria-hidden="true">→</span>
        </a>
    </div>
</section>

<?php if ($featured): ?>
<section class="container section" id="featured">
    <div class="section-head">
        <h2>Sản phẩm nổi bật</h2>
        <a class="section-link-pill" href="<?= url('/products') ?>">Xem tất cả →</a>
    </div>
    <div class="product-grid">
        <?php foreach ($featured as $product): ?>
            <?= $this->insert('partials/product-card', ['product' => $product]) ?>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<section class="container section" id="latest">
    <div class="section-head">
        <h2>Sản phẩm mới nhất</h2>
        <a class="section-link-pill" href="<?= url('/products?sort=newest') ?>">Khám phá thêm →</a>
    </div>
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

<section class="ikea-services-band">
    <div class="container">
        <div class="section-head">
            <h2>Dịch vụ &amp; Hỗ trợ</h2>
            <a class="section-link-pill" href="<?= url('/orders') ?>">Theo dõi đơn hàng</a>
        </div>
        <div class="ikea-services-grid">
            <div class="ikea-service-card">
                <span class="ikea-service-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </span>
                <h3>Giao hàng toàn quốc</h3>
                <p>Theo dõi trạng thái đơn hàng theo từng chặng từ xác nhận đến giao thành công.</p>
            </div>
            <div class="ikea-service-card">
                <span class="ikea-service-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </span>
                <h3>Thanh toán linh hoạt</h3>
                <p>Hỗ trợ COD khi nhận hàng hoặc chuyển khoản nhanh VietQR qua cổng SePay.</p>
            </div>
            <div class="ikea-service-card">
                <span class="ikea-service-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                </span>
                <h3>Đổi trả 7 ngày</h3>
                <p>Gửi yêu cầu trả hàng kèm ảnh minh chứng trực tiếp trong chi tiết đơn hàng.</p>
            </div>
        </div>
    </div>
</section>
