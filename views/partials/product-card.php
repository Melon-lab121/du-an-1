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
?>
<article class="product-card" id="<?= e($cardId) ?>">
    <a href="<?= $link ?>" class="product-thumb">
        <?= $this->insert('partials/product-image', ['product' => $product]) ?>
        <?php if ($hasSale): ?>
            <span class="tag tag-sale">-<?= (int) round(100 - $final * 100 / max(1, (int) $product['price'])) ?>%</span>
        <?php endif ?>
        <?php if ((int) $product['stock'] <= 0): ?><span class="tag tag-out">Hết hàng</span><?php endif ?>
    </a>

    <?php if ($variantThumbs !== []): ?>
        <div class="variant-thumb-strip" role="group" aria-label="Ảnh biến thể <?= e((string) $product['name']) ?>" style="display:flex;gap:6px;flex-wrap:wrap;padding:8px 12px 0;">
            <?php foreach ($variantThumbs as $idx => $v): ?>
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
                    class="js-variant-thumb"
                    data-target-card="<?= e($cardId) ?>"
                    data-variant-img="<?= e($vImg) ?>"
                    data-variant-title="<?= e($vTitle) ?>"
                    data-variant-final="<?= $vFinal ?>"
                    data-variant-price="<?= $vPrice ?>"
                    data-variant-stock="<?= $vStock ?>"
                    title="<?= e($vTitle) ?>"
                    aria-label="Xem biến thể <?= e($vTitle) ?>"
                    style="padding:2px;border:1.5px solid <?= $idx === 0 ? 'var(--primary, #2563eb)' : 'var(--border, #e5e7eb)' ?>;border-radius:6px;background:#fff;cursor:pointer;line-height:0;"
                >
                    <img
                        src="<?= e($vImg) ?>"
                        alt="<?= e($vTitle) ?>"
                        style="width:32px;height:32px;object-fit:cover;border-radius:4px;display:block;"
                    >
                </button>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <div class="product-body">
        <h3 class="product-name"><a href="<?= $link ?>"><?= e($product['name']) ?></a></h3>

        <?php if ($cardVariants !== []): ?>
            <div class="variant-labels muted small" style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;">
                <?php foreach ($cardVariants as $v): ?>
                    <span
                        class="variant-pill"
                        style="font-size:11px;padding:2px 6px;border-radius:4px;background:var(--surface-muted, #f3f4f6);border:1px solid var(--border, #e5e7eb);"
                    ><?= e((string) ($v['title'] ?? '')) ?></span>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <div class="price">
            <strong class="js-card-price"><?= $product['has_variants'] ? 'Từ ' : '' ?><?= money($final) ?></strong>
            <?php if ($hasSale): ?><del><?= money($product['price']) ?></del><?php endif ?>
        </div>

        <?php if ($product['has_variants']): ?>
            <div class="product-card-actions">
                <a class="btn-cart-icon js-variant-detail-link" href="<?= $link ?>" title="Chọn phân loại">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </a>
                <a class="btn-buy-now js-variant-detail-link" href="<?= $link ?>" title="Chọn phân loại">
                    Thanh toán ngay
                </a>
            </div>
        <?php else: ?>
            <form action="<?= url('/cart/add') ?>" method="post" class="product-card-actions" data-ajax-cart>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <button class="btn-cart-icon" type="submit" title="Thêm vào giỏ hàng" <?= (int) $product['stock'] <= 0 ? 'disabled' : '' ?>>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </button>
                <button class="btn-buy-now" type="submit" name="buy_now" value="1" <?= (int) $product['stock'] <= 0 ? 'disabled' : '' ?>>
                    Thanh toán ngay
                </button>
            </form>
        <?php endif ?>
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
            el.style.borderColor = el === btn ? 'var(--primary, #2563eb)' : 'var(--border, #e5e7eb)';
        });
    });
});
</script>
<?php $this->stop() ?>