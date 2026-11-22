<?php
/** @var array<string, mixed> $product */
$final = App\Services\PriceCalculator::unitPrice($product);
$hasSale = $final < (int) $product['price'];
$link = route('product.show', ['slug' => $product['slug']]);
?>
<article class="product-card">
    <a href="<?= $link ?>" class="product-thumb">
        <?= $this->insert('partials/product-image', ['product' => $product]) ?>
        <?php if ($hasSale): ?>
            <span class="tag tag-sale">-<?= (int) round(100 - $final * 100 / max(1, (int) $product['price'])) ?>%</span>
        <?php endif ?>
        <?php if ((int) $product['stock'] <= 0): ?><span class="tag tag-out">Hết hàng</span><?php endif ?>
    </a>
    <div class="product-body">
        <h3 class="product-name"><a href="<?= $link ?>"><?= e($product['name']) ?></a></h3>
        <div class="price">
            <strong><?= $product['has_variants'] ? 'Từ ' : '' ?><?= money($final) ?></strong>
            <?php if ($hasSale): ?><del><?= money($product['price']) ?></del><?php endif ?>
        </div>
        <?php if ($product['has_variants']): ?>
            <div class="product-card-actions">
                <a class="btn-cart-icon" href="<?= $link ?>" title="Chọn phân loại">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </a>
                <a class="btn-buy-now" href="<?= $link ?>" title="Chọn phân loại">
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
