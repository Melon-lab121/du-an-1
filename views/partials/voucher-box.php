<?php
$appliedVoucher = function_exists('voucher') && function_exists('cart')
    ? voucher()->applied(cart()->lines(), auth()->id(), auth()->user()['email'] ?? null, auth()->user()['phone'] ?? null)
    : null;
?>
<div class="voucher-box" style="margin: 16px 0; padding: 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
    <?php if ($appliedVoucher): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
            <div>
                <div style="font-weight: 600; color: #0284c7; display: flex; align-items: center; gap: 6px;">
                    <span>🏷</span>
                    <span><?= e($appliedVoucher['voucher']['code']) ?></span>
                </div>
                <small class="muted" style="display: block; margin-top: 2px;">
                    <?= e($appliedVoucher['description']) ?>
                </small>
            </div>
            <form action="<?= url('/voucher/remove') ?>" method="post" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-ghost" style="color: #ef4444; border-color: #fca5a5;" title="Hủy mã ưu đãi này">Gỡ mã</button>
            </form>
        </div>
    <?php else: ?>
        <form action="<?= url('/voucher/apply') ?>" method="post" style="margin: 0;">
            <?= csrf_field() ?>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Mã ưu đãi / Voucher</label>
            <div style="display: flex; gap: 8px;">
                <input type="text" name="code" placeholder="Nhập mã ưu đãi..." required style="flex: 1; text-transform: uppercase; padding: 7px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-primary" style="white-space: nowrap;">Áp dụng</button>
            </div>
        </form>
    <?php endif ?>
</div>
