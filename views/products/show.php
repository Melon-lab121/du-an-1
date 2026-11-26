<?php
$this->extend('layouts/main', ['title' => $product['name'], 'description' => $product['short_description']]);
$final = App\Services\PriceCalculator::unitPrice($product);
$hasSale = $final < (int) $product['price'];
$inStock = (int) $product['stock'] > 0;
$hasVariants = $variants !== null;

$mainDefaultSrc = !empty($product['image'])
    ? (preg_match('#^https?://#i', (string) $product['image']) === 1 ? (string) $product['image'] : url((string) $product['image']))
    : '';

$variantRows = ($hasVariants && !empty($variants['variants']) && is_array($variants['variants']))
    ? $variants['variants']
    : [];

$variantThumbs = [];
foreach ($variantRows as $vRow) {
    $vImg = trim((string) ($vRow['image'] ?? ''));
    if ($vImg === '') {
        continue;
    }
    $vSrc = preg_match('#^https?://#i', $vImg) === 1 ? $vImg : url($vImg);
    if (!isset($variantThumbs[$vSrc])) {
        $variantThumbs[$vSrc] = [
            'src' => $vSrc,
            'title' => (string) ($vRow['title'] ?? $product['name']),
            'values' => $vRow['values'] ?? ($vRow['option_values'] ?? []),
        ];
    }
}

$fullDescription = trim((string) ($product['description'] ?? ''));
$isLongDescription = mb_strlen($fullDescription) > 360;
$shortDescription = $isLongDescription ? mb_substr($fullDescription, 0, 360) . '…' : $fullDescription;

$comments = $comments ?? [];
$avgRating = $avgRating ?? 0.0;
$isLoggedIn = isset($isLoggedIn)
    ? (bool) $isLoggedIn
    : (!empty($_SESSION['user_id']) || (!empty($_SESSION['user']['id'])));
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
            <div class="gallery-main" data-main-image-box>
                <?= $this->insert('partials/product-image', ['product' => $product]) ?>
            </div>

            <?php if ($variantThumbs !== []): ?>
                <div class="variant-thumbs" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                    <?php if ($mainDefaultSrc !== ''): ?>
                        <button
                            type="button"
                            class="variant-thumb-btn is-active"
                            data-thumb-src="<?= e($mainDefaultSrc) ?>"
                            data-variant-values=""
                            title="<?= e((string) $product['name']) ?>"
                            style="width: 56px; height: 56px; padding: 2px; border: 1.5px solid var(--brand, #5c4d42); border-radius: 8px; background: transparent; cursor: pointer; overflow: hidden;"
                        >
                            <img src="<?= e($mainDefaultSrc) ?>" alt="<?= e((string) $product['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                        </button>
                    <?php endif ?>

                    <?php foreach ($variantThumbs as $idx => $thumb): ?>
                        <?php $valCsv = implode(',', array_map('intval', is_array($thumb['values']) ? $thumb['values'] : [])); ?>
                        <button
                            type="button"
                            class="variant-thumb-btn <?= ($mainDefaultSrc === '' && $idx === 0) ? 'is-active' : '' ?>"
                            data-thumb-src="<?= e($thumb['src']) ?>"
                            data-variant-values="<?= e($valCsv) ?>"
                            title="<?= e($thumb['title']) ?>"
                            style="width: 56px; height: 56px; padding: 2px; border: <?= ($mainDefaultSrc === '' && $idx === 0) ? '1.5px solid var(--brand, #5c4d42)' : '1px solid var(--border, #ddd)' ?>; border-radius: 8px; background: transparent; cursor: pointer; overflow: hidden;"
                        >
                            <img src="<?= e($thumb['src']) ?>" alt="<?= e($thumb['title']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                        </button>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <div class="product-info">
            <h1><?= e($product['name']) ?></h1>
            <p class="muted">SKU: <span data-variant-sku><?= e($product['sku'] ?? '—') ?></span></p>
            <div class="price price-lg" data-variant-price>
                <strong><?= $hasVariants ? 'Từ ' : '' ?><?= money($final) ?></strong>
                <?php if ($hasSale): ?><del><?= money($product['price']) ?></del><span class="tag tag-sale">Tiết kiệm <?= money((int) $product['price'] - $final) ?></span><?php endif ?>
            </div>
            <p class="<?= $inStock ? 'text-success' : 'text-danger' ?>" data-variant-stock><?= $inStock ? ($hasVariants ? 'Chọn phân loại để xem tồn kho' : 'Còn ' . (int) $product['stock'] . ' sản phẩm') : 'Tạm hết hàng' ?></p>

            <form action="<?= url('/cart/add') ?>" method="post" class="buy-box" data-ajax-cart <?= $hasVariants ? 'data-variant-picker' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="variant_id" value="" data-selected-variant-id>

                <?php if ($hasVariants): ?>
                    <?php $hasStructuredOptions = !empty($variants['options']) && is_array($variants['options']); ?>
                    <?php if ($hasStructuredOptions): ?>
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
                    <?php endif ?>

                    <?php if ($variantRows !== []): ?>
                        <fieldset class="variant-option">
                            <legend>Phân loại</legend>
                            <div class="bank-codes" style="display:flex;flex-wrap:wrap;gap:8px;">
                                <?php foreach ($variantRows as $vRow): ?>
                                    <?php
                                    $vId = (int) ($vRow['id'] ?? 0);
                                    $vTitle = (string) ($vRow['title'] ?? '');
                                    $vImg = trim((string) ($vRow['image'] ?? ''));
                                    $vSrc = $vImg !== '' ? (preg_match('#^https?://#i', $vImg) === 1 ? $vImg : url($vImg)) : $mainDefaultSrc;
                                    $vVals = implode(',', array_map('intval', is_array($vRow['values'] ?? null) ? $vRow['values'] : []));
                                    ?>
                                    <button
                                        type="button"
                                        class="chip js-variant-pill"
                                        data-variant-id="<?= $vId ?>"
                                        data-thumb-src="<?= e($vSrc) ?>"
                                        data-variant-values="<?= e($vVals) ?>"
                                        <?= !$hasStructuredOptions ? 'data-required-variant="1"' : '' ?>
                                        style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;"
                                    >
                                        <?php if ($vSrc !== ''): ?>
                                            <img src="<?= e($vSrc) ?>" alt="<?= e($vTitle) ?>" style="width:20px;height:20px;object-fit:cover;border-radius:4px;">
                                        <?php endif ?>
                                        <span><?= e($vTitle) ?></span>
                                    </button>
                                <?php endforeach ?>
                            </div>
                        </fieldset>
                    <?php endif ?>

                    <script type="application/json" data-variant-json><?= json_encode($variantRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
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

    <?php if ($fullDescription !== ''): ?>
        <div class="card prose section" id="product-description-box">
            <h2>Mô tả sản phẩm</h2>
            <div id="description-preview" style="<?= $isLongDescription ? '' : 'display:none;' ?>">
                <?= nl2br(e($shortDescription)) ?>
            </div>
            <div id="description-full" style="<?= $isLongDescription ? 'display:none;' : '' ?>">
                <?= nl2br(e($fullDescription)) ?>
            </div>
            <?php if ($isLongDescription): ?>
                <div style="margin-top: 12px;">
                    <button type="button" class="btn btn-sm btn-ghost" id="toggle-description-btn" aria-expanded="false">
                        Xem thêm
                    </button>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <div class="card section" id="product-comments">
        <div class="section-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <h2 style="margin:0;">Đánh giá &amp; bình luận (<?= count($comments) ?>)</h2>
            <?php if ($comments !== []): ?>
                <span class="muted">Điểm trung bình: <strong><?= number_format((float) $avgRating, 1) ?> / 5</strong> ★</span>
            <?php endif ?>
        </div>

        <?php if ($isLoggedIn): ?>
            <form action="<?= url('/product/' . urlencode((string) $product['slug']) . '/comments') ?>" method="post" class="stack" style="margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid var(--border, #e5e7eb);">
                <?= csrf_field() ?>
                <label class="field">
                    <span>Đánh giá sao *</span>
                    <select name="rating" style="max-width:180px;">
                        <option value="5">5 ★ — Rất hài lòng</option>
                        <option value="4">4 ★ — Hài lòng</option>
                        <option value="3">3 ★ — Bình thường</option>
                        <option value="2">2 ★ — Chưa hài lòng</option>
                        <option value="1">1 ★ — Tệ</option>
                    </select>
                </label>
                <label class="field">
                    <span>Bình luận của bạn *</span>
                    <textarea name="body" rows="3" required maxlength="1000" placeholder="Chia sẻ cảm nhận về chất liệu, kích thước, độ hoàn thiện..."></textarea>
                </label>
                <div>
                    <button type="submit" class="btn">Gửi đánh giá &amp; bình luận</button>
                </div>
            </form>
        <?php else: ?>
            <p class="muted" style="margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border, #e5e7eb);">
                Vui lòng <a href="<?= route('login') ?>">đăng nhập</a> để gửi đánh giá và bình luận cho sản phẩm này.
            </p>
        <?php endif ?>

        <?php if ($comments === []): ?>
            <p class="muted">Chưa có bình luận nào cho sản phẩm này.</p>
        <?php else: ?>
            <div class="stack" style="gap:14px;">
                <?php foreach ($comments as $cmt): ?>
                    <?php $stars = max(1, min(5, (int) ($cmt['rating'] ?? 5))); ?>
                    <article style="padding:12px 14px;border:1px solid var(--border, #e5e7eb);border-radius:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                            <div>
                                <strong><?= e((string) ($cmt['user_name'] ?? 'Khách hàng')) ?></strong>
                                <span style="color:#d97706;margin-left:8px;"><?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?></span>
                            </div>
                            <span class="muted small"><?= e((string) ($cmt['created_at'] ?? '')) ?></span>
                        </div>
                        <p style="margin:0;white-space:pre-line;"><?= e((string) ($cmt['body'] ?? '')) ?></p>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

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

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleDescBtn = document.getElementById('toggle-description-btn');
    const descPreview = document.getElementById('description-preview');
    const descFull = document.getElementById('description-full');
    if (toggleDescBtn && descPreview && descFull) {
        toggleDescBtn.addEventListener('click', function () {
            const expanded = toggleDescBtn.getAttribute('aria-expanded') === 'true';
            toggleDescBtn.setAttribute('aria-expanded', String(!expanded));
            descPreview.style.display = expanded ? '' : 'none';
            descFull.style.display = expanded ? 'none' : '';
            toggleDescBtn.textContent = expanded ? 'Xem thêm' : 'Thu gọn';
        });
    }

    <?php if ($hasVariants): ?>
    const form = document.querySelector('form[data-variant-picker]');
    const mainBox = document.querySelector('[data-main-image-box]');
    const thumbs = Array.from(document.querySelectorAll('.variant-thumb-btn'));
    const pills = Array.from(document.querySelectorAll('.js-variant-pill'));
    const variantIdInput = document.querySelector('[data-selected-variant-id]');
    const skuEl = document.querySelector('[data-variant-sku]');
    const priceEl = document.querySelector('[data-variant-price] strong');
    const stockEl = document.querySelector('[data-variant-stock]');
    const variantJsonEl = document.querySelector('[data-variant-json]');

    let variantList = [];
    if (variantJsonEl) {
        try {
            variantList = JSON.parse(variantJsonEl.textContent || '[]');
        } catch (e) {
            variantList = [];
        }
    }

    const formatVnd = (amount) =>
        new Intl.NumberFormat('vi-VN').format(Math.max(0, Math.round(amount))) + ' ₫';

    function setMainImage(src, altText) {
        if (!src || !mainBox) return;
        let mainImg = mainBox.querySelector('img');
        if (!mainImg) {
            mainBox.innerHTML = '<img src="" alt="" loading="lazy">';
            mainImg = mainBox.querySelector('img');
        }
        if (mainImg) {
            mainImg.src = src;
            if (altText) mainImg.alt = altText;
        }
    }

    function setActiveThumb(src) {
        thumbs.forEach((t) => {
            const isMatch = src && t.getAttribute('data-thumb-src') === src;
            t.classList.toggle('is-active', isMatch);
            t.style.borderColor = isMatch ? 'var(--brand, #5c4d42)' : 'var(--border, #ddd)';
            t.style.borderWidth = isMatch ? '1.5px' : '1px';
        });
    }

    function setActivePill(activePill) {
        pills.forEach((p) => {
            const isMatch = p === activePill;
            p.classList.toggle('is-active', isMatch);
            p.style.borderColor = isMatch ? 'var(--brand, #5c4d42)' : '';
        });
    }

    function applyVariant(matched) {
        if (!matched) return;
        if (variantIdInput && matched.id) {
            variantIdInput.value = String(matched.id);
        }
        if (skuEl && matched.sku) {
            skuEl.textContent = matched.sku;
        }
        if (priceEl && matched.final !== undefined) {
            priceEl.textContent = formatVnd(Number(matched.final));
        }
        if (stockEl && matched.stock !== undefined) {
            const qty = Number(matched.stock);
            stockEl.textContent = qty > 0 ? 'Còn ' + qty + ' sản phẩm' : 'Tạm hết hàng';
        }
    }

    function selectByValues(valIds, fallbackSrc, fallbackTitle, pillEl) {
        const normalized = valIds.map(Number).filter(Boolean).sort();
        if (normalized.length > 0 && form) {
            normalized.forEach((vid) => {
                const radio = form.querySelector('input[type="radio"][value="' + vid + '"]');
                if (radio) radio.checked = true;
            });
        }

        const matched = variantList.find((v) => {
            const vVals = Array.isArray(v.values) ? v.values.map(Number).sort() : [];
            return vVals.length === normalized.length && normalized.length > 0 && vVals.every((val, i) => val === normalized[i]);
        }) || (pillEl ? variantList.find((v) => Number(v.id) === Number(pillEl.getAttribute('data-variant-id'))) : null);

        if (fallbackSrc) {
            setMainImage(fallbackSrc, fallbackTitle || '');
            setActiveThumb(fallbackSrc);
        }
        if (pillEl) {
            setActivePill(pillEl);
        }
        applyVariant(matched);
    }

    thumbs.forEach((btn) => {
        btn.addEventListener('click', function () {
            const src = btn.getAttribute('data-thumb-src') || '';
            const valIds = (btn.getAttribute('data-variant-values') || '').split(',');
            const matchingPill = pills.find((p) => (p.getAttribute('data-variant-values') || '') === (btn.getAttribute('data-variant-values') || ''));
            selectByValues(valIds, src, btn.getAttribute('title') || '', matchingPill || null);
        });
    });

    pills.forEach((pill) => {
        pill.addEventListener('click', function () {
            const src = pill.getAttribute('data-thumb-src') || '';
            const valIds = (pill.getAttribute('data-variant-values') || '').split(',');
            selectByValues(valIds, src, pill.textContent.trim(), pill);
        });
    });

    if (form) {
        form.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', function () {
                const checkedIds = Array.from(form.querySelectorAll('input[type="radio"]:checked'))
                    .map((r) => Number(r.value))
                    .sort();
                if (checkedIds.length === 0) return;

                const matched = variantList.find((v) => {
                    const vVals = Array.isArray(v.values) ? v.values.map(Number).sort() : [];
                    return vVals.length === checkedIds.length && vVals.every((val, i) => val === checkedIds[i]);
                });
                applyVariant(matched);

                const matchingPill = pills.find((p) => {
                    const pVals = (p.getAttribute('data-variant-values') || '').split(',').map(Number).filter(Boolean).sort();
                    return pVals.length === checkedIds.length && pVals.every((val, i) => val === checkedIds[i]);
                });
                if (matchingPill) {
                    setActivePill(matchingPill);
                    const src = matchingPill.getAttribute('data-thumb-src') || '';
                    if (src) {
                        setMainImage(src, matchingPill.textContent.trim());
                        setActiveThumb(src);
                    }
                }
            });
        });

        form.addEventListener('submit', function (e) {
            const requiredPill = form.querySelector('.js-variant-pill[data-required-variant="1"]');
            if (requiredPill && (!variantIdInput || !variantIdInput.value)) {
                e.preventDefault();
                stockEl && (stockEl.textContent = 'Vui lòng chọn một phân loại trước khi thêm vào giỏ.');
            }
        });
    }
    <?php endif ?>
});
</script>
<?php $this->stop() ?>