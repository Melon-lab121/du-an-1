<?php
/** @var array{subtotal: int, discount: int, shipping_fee: int, total: int, voucher_code?: ?string} $totals */
$appliedCode = $totals['voucher_code'] ?? (function_exists('voucher') ? voucher()->appliedCode() : null);
?>
<dl class="summary">
    <div><dt>Tạm tính</dt><dd><?= money($totals['subtotal']) ?></dd></div>
    <?php if ($totals['discount'] > 0): ?>
        <div>
            <dt>
                Giảm giá
                <?php if ($appliedCode): ?>
                    <span style="font-size: 11px; padding: 2px 6px; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 4px; font-weight: 600; margin-left: 4px;"><?= e($appliedCode) ?></span>
                <?php endif ?>
            </dt>
            <dd class="text-success">−<?= money($totals['discount']) ?></dd>
        </div>
    <?php endif ?>
    <div><dt>Phí vận chuyển</dt><dd><?= $totals['shipping_fee'] > 0 ? money($totals['shipping_fee']) : 'Miễn phí' ?></dd></div>
    <div class="summary-total"><dt>Tổng cộng</dt><dd><?= money($totals['total']) ?></dd></div>
</dl>
