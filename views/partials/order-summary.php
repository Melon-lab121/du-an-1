<?php /** @var array{subtotal: int, discount: int, shipping_fee: int, total: int} $totals */ ?>
<dl class="summary">
    <div><dt>Tạm tính</dt><dd><?= money($totals['subtotal']) ?></dd></div>
    <?php if ($totals['discount'] > 0): ?>
        <div><dt>Giảm giá</dt><dd class="text-success">−<?= money($totals['discount']) ?></dd></div>
    <?php endif ?>
    <div><dt>Phí vận chuyển</dt><dd><?= $totals['shipping_fee'] > 0 ? money($totals['shipping_fee']) : 'Miễn phí' ?></dd></div>
    <div class="summary-total"><dt>Tổng cộng</dt><dd><?= money($totals['total']) ?></dd></div>
</dl>
