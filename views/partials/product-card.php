<?php
/** @var array<string, mixed> $product */
$final = App\Services\PriceCalculator::unitPrice($product);
$hasSale = $final < (int) $product['price'];
$link = route('product.show', ['slug' => $product['slug']]);
$cardId = 'pcard-' . (int) $product['id'] . '-' . substr(md5((string) ($product['slug'] ?? '') . microtime()), 0, 6);

// Supports $product['variants'] when passed by controller/query, or falls back to active rows in product_variants
$cardVariants = [];
if (!empty($product['variants']) && is_array($product['variants'])) {
    $cardVariants = $product['variants'];
} elseif (!empty($product['has_variants'])) {
    $cardVariants = App\Models\ProductVariant::forProduct((int) $product['id']);
}

$variantThumbs = array_values(array_filter(
    $cardVariants,
    static fn (array $v): bool => !empty($v['image'])
));
$visibleThumbs = array_slice($variantThumbs, 0, 4);
$extraThumbs = max(0, count($variantThumbs) - count($visibleThumbs));
?>
<article class="product-card" id="<?= e($cardId) ?>">
    <a href="<?= $link ?>" class="product-thumb">
        <?= $this->insert('partials/product-image', ['product' => $product]) ?>
        <?php if ($hasSale): ?>
            <span class="tag tag-sale">-<?= (int) round(100 - $final * 100 / max(1, (int) $product['price'])) ?>%</span>
        <?php endif ?>
        <?php if ((int) $product['stock'] <= 0): ?><span class="tag tag-out">Hết hàng</span><?php endif ?>
    </a>

    <?php if ($visibleThumbs !== []): ?>
        <div class="variant-thumb-strip" role="group" aria-label="Ảnh biến thể <?= e((string) $product['name']) ?>">
            <?php foreach ($visibleThumbs as $idx => $v): ?>
                <?php
                $vTitle = (string) ($v['title'] ?? 'Biến thể');
                $vImg = (string) $v['image'];
                $vPrice = (int) ($v['price'] ?? $product['price']);
                $vSale = isset($v['sale_price']) && $v['sale_price'] !== null && (int) $v['sale_price'] > 0 && (int) $v['sale_price'] < $vPrice
                    ? (int) $v['sale_price']
                    : null;
                $vFinal = $vSale ?? $vPrice;
                $vStock = (int) ($v['stock'] ?? 0);
                ?>
                <button
                    type="button"
                    class="js-variant-thumb <?= $idx === 0 ? 'is-active' : '' ?>"
                    data-target-card="<?= e($cardId) ?>"
                    data-variant-img="<?= e($vImg) ?>"
                    data-variant-title="<?= e($vTitle) ?>"
                    data-variant-final="<?= $vFinal ?>"
                    data-variant-price="<?= $vPrice ?>"
                    data-variant-stock="<?= $vStock ?>"
                    title="<?= e($vTitle) ?>"
                    aria-label="Xem biến thể <?= e($vTitle) ?>"
                >
                    <img src="<?= e($vImg) ?>" alt="<?= e($vTitle) ?>">
                </button>
            <?php endforeach ?>
            <?php if ($extraThumbs > 0): ?>
                <a href="<?= $link ?>" class="variant-more-count">+<?= $extraThumbs ?></a>
            <?php endif ?>
        </div>
    <?php endif ?>

    <div class="product-body">
        <?php if (!empty($product['has_variants'])): ?>
            <span class="product-kicker"><?= count($cardVariants) ?> phân loại</span>
        <?php elseif ($hasSale): ?>
            <span class="product-kicker">Ưu đãi giá tốt</span>
        <?php endif ?>

        <h3 class="product-name"><a href="<?= $link ?>"><?= e($product['name']) ?></a></h3>

        <?php if (!empty($product['short_description'])): ?>
            <p class="product-sub"><?= e((string) $product['short_description']) ?></p>
        <?php endif ?>

        <div class="product-footer-row">
            <div class="price <?= $hasSale ? 'has-sale' : '' ?>">
                <strong class="js-card-price"><?= $product['has_variants'] ? 'Từ ' : '' ?><?= money($final) ?></strong>
                <?php if ($hasSale): ?><del><?= money($product['price']) ?></del><?php endif ?>
            </div>

            <?php if ($product['has_variants']): ?>
                <a class="btn-ikea-bag js-variant-detail-link" href="<?= $link ?>" title="Chọn phân loại" aria-label="Chọn phân loại <?= e($product['name']) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M12 11v6"/><path d="M9 14h6"/></svg>
                </a>
            <?php else: ?>
                <form action="<?= url('/cart/add') ?>" method="post" data-ajax-cart>
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <button class="btn-ikea-bag" type="submit" title="Thêm vào giỏ hàng" aria-label="Thêm <?= e($product['name']) ?> vào giỏ" <?= (int) $product['stock'] <= 0 ? 'disabled' : '' ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M12 11v6"/><path d="M9 14h6"/></svg>
                    </button>
                </form>
            <?php endif ?>
        </div>
    </div>
</article>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.__productCardVariantThumbsBound) return;
    window.__productCardVariantThumbsBound = true;

    const formatVnd = (amount) =>
        new Intl.NumberFormat('vi-VN').format(Math.max(0, Math.round(amount))) + ' ₫';

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-variant-thumb');
        if (!btn) return;

        const card = document.getElementById(btn.dataset.targetCard || '') || btn.closest('.product-card');
        if (!card) return;

        const mainImg = card.querySelector('.product-thumb img');
        if (mainImg && btn.dataset.variantImg) {
            mainImg.src = btn.dataset.variantImg;
            if (btn.dataset.variantTitle) {
                mainImg.alt = btn.dataset.variantTitle;
            }
        }

        const priceEl = card.querySelector('.js-card-price');
        if (priceEl && btn.dataset.variantFinal) {
            priceEl.textContent = formatVnd(Number(btn.dataset.variantFinal));
        }

        card.querySelectorAll('.js-variant-thumb').forEach((el) => {
            el.classList.toggle('is-active', el === btn);
        });
    });
});
</script>
<?php $this->stop() ?>
