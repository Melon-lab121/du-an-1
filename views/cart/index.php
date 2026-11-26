<?php $this->extend('layouts/main', ['title' => 'Giỏ hàng']) ?>
<?php
$remainingGuestSeconds = method_exists(cart(), 'guestRemainingSeconds') ? (int) cart()->guestRemainingSeconds() : 0;
?>

<section class="container section">
    <div class="cart-header">
        <h1>Giỏ hàng</h1>
        <?php if ($lines && $remainingGuestSeconds > 0): ?>
            <p class="muted">
                Giỏ hàng tạm đang giữ chỗ trong khoảng <?= ceil($remainingGuestSeconds / 60) ?> phút. Đăng nhập để lưu giỏ hàng lâu dài.
            </p>
        <?php endif ?>
    </div>

    <?php if (!$lines): ?>
        <div class="empty">
            <p>Giỏ hàng của bạn đang trống.</p>
            <a class="btn" href="<?= url('/products') ?>">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <form action="<?= url('/cart/update') ?>" method="post" class="card" id="cart-update-form">
                <?= csrf_field() ?>
                <table class="table cart-table">
                    <thead>
                        <tr>
                            <th class="col-check">
                                <input type="checkbox" id="select-all-cart" checked aria-label="Chọn tất cả sản phẩm">
                            </th>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr data-cart-row data-line-total="<?= (int) $line['total'] ?>">
                            <td class="col-check">
                                <input
                                    type="checkbox"
                                    class="cart-item-checkbox"
                                    name="selected_keys[]"
                                    value="<?= e((string) $line['key']) ?>"
                                    checked
                                    aria-label="Chọn <?= e((string) $line['name']) ?> để thanh toán"
                                >
                            </td>
                            <td>
                                <a class="cart-item" href="<?= route('product.show', ['slug' => $line['slug']]) ?>">
                                    <span class="cart-thumb">
                                        <?= $this->insert('partials/product-image', [
                                            'product' => $line['product'],
                                            'image' => $line['image'] ?? ($line['product']['image'] ?? null),
                                        ]) ?>
                                    </span>
                                    <span>
                                        <?= e((string) $line['name']) ?>
                                        <?php if (!empty($line['variant_label'])): ?>
                                            <br><small class="muted">Phân loại: <?= e((string) $line['variant_label']) ?></small>
                                        <?php endif ?>
                                    </span>
                                </a>
                            </td>
                            <td data-label="Đơn giá"><?= money((int) $line['price']) ?></td>
                            <td data-label="Số lượng">
                                <div class="qty" data-qty>
                                    <button type="button" data-step="-1" aria-label="Giảm">−</button>
                                    <input
                                        type="number"
                                        name="quantities[<?= e((string) $line['key']) ?>]"
                                        value="<?= (int) $line['quantity'] ?>"
                                        min="0"
                                        max="<?= (int) $line['stock'] ?>"
                                        aria-label="Số lượng"
                                    >
                                    <button type="button" data-step="1" aria-label="Tăng">+</button>
                                </div>
                            </td>
                            <td data-label="Thành tiền"><strong><?= money((int) $line['total']) ?></strong></td>
                            <td>
                                <button
                                    class="btn-icon"
                                    type="submit"
                                    form="remove-<?= e(str_replace(':', '-', (string) $line['key'])) ?>"
                                    aria-label="Xoá"
                                >✕</button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <div class="card-footer">
                    <a href="<?= url('/products') ?>">← Tiếp tục mua sắm</a>
                    <div class="cart-footer-actions">
                        <label class="inline-check" for="select-all-footer">
                            <input type="checkbox" id="select-all-footer" checked>
                            <span>Chọn cả giỏ</span>
                        </label>
                        <button class="btn btn-ghost" type="submit">Cập nhật giỏ hàng</button>
                    </div>
                </div>
            </form>

            <?php foreach ($lines as $line): ?>
                <form id="remove-<?= e(str_replace(':', '-', (string) $line['key'])) ?>" action="<?= url('/cart/remove') ?>" method="post" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="key" value="<?= e((string) $line['key']) ?>">
                </form>
            <?php endforeach ?>

            <aside class="card">
                <h3>Tóm tắt đơn hàng</h3>
                <p class="muted" id="selected-items-summary">Đang chọn toàn bộ giỏ hàng</p>
                <?= $this->insert('partials/voucher-box') ?>
                <?= $this->insert('partials/order-summary', ['totals' => $totals]) ?>
                <a
                    class="btn btn-lg btn-block"
                    id="proceed-checkout-btn"
                    href="<?= url('/checkout') ?>"
                    data-base-checkout-url="<?= e(url('/checkout')) ?>"
                >Tiến hành đặt hàng</a>
            </aside>
        </div>
    <?php endif ?>
</section>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(document.querySelectorAll('.cart-item-checkbox'));
    if (checkboxes.length === 0) return;

    const selectAllTop = document.getElementById('select-all-cart');
    const selectAllFooter = document.getElementById('select-all-footer');
    const checkoutBtn = document.getElementById('proceed-checkout-btn');
    const summaryText = document.getElementById('selected-items-summary');

    const summaryEl = document.querySelector('dl.summary');
    const subtotalEl = summaryEl ? summaryEl.querySelector('[data-summary-subtotal]') : null;
    const discountRowEl = summaryEl ? summaryEl.querySelector('[data-summary-discount-row]') : null;
    const discountEl = summaryEl ? summaryEl.querySelector('[data-summary-discount]') : null;
    const shippingEl = summaryEl ? summaryEl.querySelector('[data-summary-shipping]') : null;
    const totalEl = summaryEl ? summaryEl.querySelector('[data-summary-total]') : null;

    const initialSubtotal = summaryEl ? Number(summaryEl.dataset.initialSubtotal || 0) : 0;
    const initialDiscount = summaryEl ? Number(summaryEl.dataset.initialDiscount || 0) : 0;
    const initialShipping = summaryEl ? Number(summaryEl.dataset.initialShipping || 0) : 0;
    const initialTotal = summaryEl ? Number(summaryEl.dataset.initialTotal || 0) : 0;

    const initialSubtotalText = subtotalEl ? subtotalEl.textContent : '';
    const initialDiscountText = discountEl ? discountEl.textContent : '';
    const initialShippingText = shippingEl ? shippingEl.textContent : '';
    const initialTotalText = totalEl ? totalEl.textContent : '';

    const formatVnd = (amount) =>
        new Intl.NumberFormat('vi-VN').format(Math.max(0, Math.round(amount))) + ' ₫';

    function getSelectedSubtotal() {
        return checkboxes.reduce((sum, cb) => {
            if (!cb.checked) return sum;
            const row = cb.closest('[data-cart-row]');
            const lineTotal = row ? Number(row.dataset.lineTotal || 0) : 0;
            return sum + (Number.isFinite(lineTotal) ? lineTotal : 0);
        }, 0);
    }

    function updateTotals(checkedCount, totalCount) {
        if (!summaryEl) return;

        const isAllSelected = checkedCount === totalCount;

        if (isAllSelected) {
            if (subtotalEl) subtotalEl.textContent = initialSubtotalText;
            if (discountRowEl) discountRowEl.style.display = initialDiscount > 0 ? '' : 'none';
            if (discountEl) discountEl.textContent = initialDiscountText;
            if (shippingEl) shippingEl.textContent = initialShippingText;
            if (totalEl) totalEl.textContent = initialTotalText;
            return;
        }

        if (checkedCount === 0) {
            if (subtotalEl) subtotalEl.textContent = formatVnd(0);
            if (discountRowEl) discountRowEl.style.display = 'none';
            if (shippingEl) shippingEl.textContent = 'Miễn phí';
            if (totalEl) totalEl.textContent = formatVnd(0);
            return;
        }

        const selectedSubtotal = getSelectedSubtotal();
        const ratio = initialSubtotal > 0 ? Math.min(1, selectedSubtotal / initialSubtotal) : 0;
        const adjustedDiscount = initialDiscount > 0
            ? Math.min(selectedSubtotal, Math.round(initialDiscount * ratio))
            : 0;
        const adjustedTotal = Math.max(0, selectedSubtotal - adjustedDiscount + initialShipping);

        if (subtotalEl) subtotalEl.textContent = formatVnd(selectedSubtotal);
        if (discountRowEl) discountRowEl.style.display = adjustedDiscount > 0 ? '' : 'none';
        if (discountEl) discountEl.textContent = '−' + formatVnd(adjustedDiscount);
        if (shippingEl) shippingEl.textContent = initialShipping > 0 ? formatVnd(initialShipping) : 'Miễn phí';
        if (totalEl) totalEl.textContent = formatVnd(adjustedTotal);
    }

    function syncCheckoutState() {
        const checkedBoxes = checkboxes.filter((cb) => cb.checked);
        const allChecked = checkedBoxes.length === checkboxes.length;

        if (selectAllTop) {
            selectAllTop.checked = allChecked;
            selectAllTop.indeterminate = checkedBoxes.length > 0 && !allChecked;
        }
        if (selectAllFooter) {
            selectAllFooter.checked = allChecked;
            selectAllFooter.indeterminate = checkedBoxes.length > 0 && !allChecked;
        }

        if (summaryText) {
            if (checkedBoxes.length === 0) {
                summaryText.textContent = 'Chưa chọn sản phẩm nào để thanh toán';
            } else if (allChecked) {
                summaryText.textContent = 'Đang chọn cả giỏ (' + checkedBoxes.length + ' sản phẩm)';
            } else {
                summaryText.textContent = 'Đang chọn ' + checkedBoxes.length + '/' + checkboxes.length + ' sản phẩm để thanh toán';
            }
        }

        if (checkoutBtn) {
            const baseUrl = checkoutBtn.dataset.baseCheckoutUrl || '/checkout';
            if (checkedBoxes.length === 0) {
                checkoutBtn.setAttribute('aria-disabled', 'true');
                checkoutBtn.classList.add('disabled');
                checkoutBtn.href = '#';
            } else if (allChecked) {
                checkoutBtn.removeAttribute('aria-disabled');
                checkoutBtn.classList.remove('disabled');
                checkoutBtn.href = baseUrl;
            } else {
                checkoutBtn.removeAttribute('aria-disabled');
                checkoutBtn.classList.remove('disabled');
                const selectedValues = checkedBoxes.map((cb) => cb.value).join(',');
                const sep = baseUrl.includes('?') ? '&' : '?';
                checkoutBtn.href = baseUrl + sep + 'selected=' + encodeURIComponent(selectedValues);
            }
        }

        updateTotals(checkedBoxes.length, checkboxes.length);
    }

    function toggleAll(checked) {
        checkboxes.forEach((cb) => {
            cb.checked = checked;
        });
        syncCheckoutState();
    }

    if (selectAllTop) {
        selectAllTop.addEventListener('change', () => toggleAll(selectAllTop.checked));
    }
    if (selectAllFooter) {
        selectAllFooter.addEventListener('change', () => toggleAll(selectAllFooter.checked));
    }
    checkboxes.forEach((cb) => {
        cb.addEventListener('change', syncCheckoutState);
    });

    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function (e) {
            const checkedBoxes = checkboxes.filter((cb) => cb.checked);
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
            }
        });
    }

    syncCheckoutState();
});
</script>
<?php $this->stop() ?>