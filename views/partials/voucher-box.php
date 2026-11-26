<?php
$inForm = (bool) ($inForm ?? false);
$lines = $lines ?? (function_exists('cart') ? cart()->lines() : []);
$user = auth()->user();
$appliedVoucher = $appliedVoucher ?? (function_exists('voucher')
    ? voucher()->applied($lines, auth()->id(), $user['email'] ?? null, $user['phone'] ?? null)
    : null);
$availableVouchers = array_values(array_filter(
    $availableVouchers ?? (function_exists('voucher')
        ? voucher()->availableForCart($lines, auth()->id(), $user['email'] ?? null, $user['phone'] ?? null)
        : []),
    static fn (array $item): bool => !empty($item['eligible']),
));
$appliedCode = $appliedVoucher['voucher']['code'] ?? '';
$selectId = $inForm ? 'checkout-voucher-select' : 'cart-voucher-select';
$boxStyle = $inForm
    ? 'margin: 14px 0; padding: 12px; background: var(--bg); border: 1px dashed var(--border); border-radius: var(--radius-sm); color: var(--text);'
    : 'margin: 16px 0; padding: 14px; background: var(--bg); border: 1px dashed var(--border); border-radius: var(--radius-sm); color: var(--text);';
?>
<div class="<?= $inForm ? 'voucher-checkout-box' : 'voucher-box' ?>" style="<?= $boxStyle ?>">
    <?php if (!$inForm): ?>
        <form action="<?= url('/voucher/apply') ?>" method="post" style="margin: 0;">
            <?= csrf_field() ?>
    <?php endif ?>
            <label for="<?= $selectId ?>" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">Mã ưu đãi / Voucher</label>
            <div style="display: flex; gap: 6px; align-items: center;">
                <select name="voucher_code_input" id="<?= $selectId ?>" data-voucher-select data-apply-url="<?= url('/voucher/apply') ?>" data-remove-url="<?= url('/voucher/remove') ?>" style="flex: 1; font-size: 13px; padding: 8px 10px; min-width: 0;">
                    <option value=""><?= $availableVouchers ? '-- Không áp dụng voucher --' : '-- Không có voucher khả dụng --' ?></option>
                    <?php foreach ($availableVouchers as $item): ?>
                        <?php $vCode = (string) $item['voucher']['code']; ?>
                        <option value="<?= e($vCode) ?>" <?= $appliedCode === $vCode ? 'selected' : '' ?>>
                            <?= e($item['label']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <?php if ($inForm): ?>
                    <button type="submit" formaction="<?= url('/voucher/apply') ?>" formmethod="post" formnovalidate class="btn btn-sm btn-primary" style="white-space: nowrap;">Áp dụng</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-sm btn-primary" style="white-space: nowrap;">Áp dụng</button>
                <?php endif ?>
            </div>
            <small data-voucher-desc class="<?= $appliedVoucher ? 'text-success' : 'muted' ?>" style="display: block; margin-top: 6px; font-size: 12px;">
                <?php if ($appliedVoucher): ?>
                    ✓ Đang áp dụng <strong><?= e((string) $appliedVoucher['voucher']['code']) ?></strong>: <?= e((string) $appliedVoucher['description']) ?>
                <?php endif ?>
            </small>
    <?php if (!$inForm): ?>
        </form>
    <?php endif ?>
</div>
