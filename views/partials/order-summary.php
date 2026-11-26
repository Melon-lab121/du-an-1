<?php
/** @var array{subtotal: int, discount: int, shipping_fee: int, total: int, voucher_code?: ?string} $totals */
$appliedCode = $totals['voucher_code'] ?? (function_exists('voucher') ? voucher()->appliedCode() : null);
$subtotalVal = (int) ($totals['subtotal'] ?? 0);
$discountVal = (int) ($totals['discount'] ?? 0);
$shippingVal = (int) ($totals['shipping_fee'] ?? 0);
$totalVal = (int) ($totals['total'] ?? max(0, $subtotalVal - $discountVal + $shippingVal));
?>
<dl
    class="summary"
    data-initial-subtotal="<?= $subtotalVal ?>"
    data-initial-discount="<?= $discountVal ?>"
    data-initial-shipping="<?= $shippingVal ?>"
    data-initial-total="<?= $totalVal ?>"
>
    <div>
        <dt>Tạm tính</dt>
        <dd data-summary-subtotal><?= money($subtotalVal) ?></dd>
    </div>
    <?php if ($discountVal > 0): ?>
        <div data-summary-discount-row>
            <dt>
                Giảm giá
                <?php if ($appliedCode): ?>
                    <span style="font-size: 11px; padding: 2px 6px; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; font-weight: 600; margin-left: 4px;"><?= e($appliedCode) ?></span>
                <?php endif ?>
            </dt>
            <dd class="text-success" data-summary-discount>−<?= money($discountVal) ?></dd>
        </div>
    <?php endif ?>
    <div>
        <dt>Phí vận chuyển</dt>
        <dd data-summary-shipping><?= $shippingVal > 0 ? money($shippingVal) : 'Miễn phí' ?></dd>
    </div>
    <div class="summary-total">
        <dt>Tổng cộng</dt>
        <dd data-summary-total><?= money($totalVal) ?></dd>
    </div>
</dl>