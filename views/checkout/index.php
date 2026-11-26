<?php
$this->extend('layouts/main', ['title' => 'Đặt hàng']);
/** @var array<string, App\Services\Payment\PaymentGatewayInterface> $gateways */
$selected = old('payment_method', array_key_first($gateways));
$field = static fn (string $name, string $fallback = ''): string => e(old($name, $fallback));
?>
<section class="container section">
    <h1>Thông tin đặt hàng</h1>

    <form action="<?= url('/checkout') ?>" method="post" class="checkout-layout" novalidate>
        <?= csrf_field() ?>
        <div class="card">
            <h3>Người nhận</h3>
            <div class="form-grid">
                <label class="field">
                    <span>Họ tên *</span>
                    <input name="name" value="<?= $field('name', $user['name'] ?? '') ?>" required autocomplete="name">
                    <?php if ($err = error('name')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <label class="field">
                    <span>Số điện thoại *</span>
                    <input name="phone" value="<?= $field('phone', $user['phone'] ?? '') ?>" required autocomplete="tel" inputmode="tel">
                    <?php if ($err = error('phone')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <label class="field field-full">
                    <span>Email *</span>
                    <input type="email" name="email" value="<?= $field('email', $user['email'] ?? '') ?>" required autocomplete="email">
                    <?php if ($err = error('email')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <label class="field field-full">
                    <span>Địa chỉ nhận hàng *</span>
                    <input name="address" value="<?= $field('address', $user['address'] ?? '') ?>" required autocomplete="street-address">
                    <?php if ($err = error('address')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <label class="field field-full">
                    <span>Ghi chú</span>
                    <textarea name="note" rows="3"><?= $field('note') ?></textarea>
                </label>
            </div>

            <h3>Phương thức thanh toán</h3>
            <div class="payment-options">
                <?php foreach ($gateways as $code => $gateway): ?>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="<?= e($code) ?>" <?= $selected === $code ? 'checked' : '' ?> data-payment-radio>
                        <span>
                            <strong><?= e($gateway->name()) ?></strong>
                            <small class="muted"><?= e($gateway->description()) ?></small>
                        </span>
                    </label>
                    <?php if ($code === 'sepay'): ?>
                        <div class="bank-codes" data-bank-codes <?= $selected === 'sepay' ? '' : 'hidden' ?>>
                            <?php foreach ($sepayMethods as $value => $label): ?>
                                <label class="chip">
                                    <input type="radio" name="sepay_method" value="<?= e($value) ?>" <?= old('sepay_method', '') === $value ? 'checked' : '' ?>>
                                    <span><?= e($label) ?></span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                <?php endforeach ?>
                <?php if (!isset($gateways['sepay'])): ?>
                    <p class="muted small">SePay chưa bật — điền <code>SEPAY_MERCHANT_ID</code> và <code>SEPAY_SECRET_KEY</code> trong file <code>.env</code>.</p>
                <?php endif ?>
                <?php if ($err = error('payment_method')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
            </div>
        </div>

        <aside class="card sticky">
            <h3>Đơn hàng (<?= array_sum(array_column($lines, 'quantity')) ?> sản phẩm)</h3>
            <ul class="mini-lines">
                <?php foreach ($lines as $line): ?>
                    <li><span><?= e($line['name']) ?><?= $line['variant_label'] ? ' (' . e($line['variant_label']) . ')' : '' ?> × <?= $line['quantity'] ?></span><strong><?= money($line['total']) ?></strong></li>
                <?php endforeach ?>
            </ul>

            <?php
            $checkoutVoucher = $checkoutVoucher ?? (function_exists('voucher')
                ? voucher()->applied($lines, auth()->id(), $user['email'] ?? null, $user['phone'] ?? null)
                : null);
            $availableVouchers = $availableVouchers ?? (function_exists('voucher')
                ? voucher()->availableForCart($lines, auth()->id(), $user['email'] ?? null, $user['phone'] ?? null)
                : []);
            $appliedCode = $checkoutVoucher['voucher']['code'] ?? '';
            ?>
            <div class="voucher-checkout-box" style="margin: 14px 0; padding: 12px; background: var(--bg); border: 1px dashed var(--border); border-radius: var(--radius-sm); color: var(--text);">
                <label for="checkout-voucher-select" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">Mã ưu đãi / Voucher</label>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <select name="voucher_code_input" id="checkout-voucher-select" data-voucher-select data-apply-url="<?= url('/voucher/apply') ?>" data-remove-url="<?= url('/voucher/remove') ?>" style="flex: 1; font-size: 13px; padding: 8px 10px; min-width: 0;">
                        <option value=""><?= $availableVouchers ? '-- Không áp dụng voucher --' : '-- Không có voucher khả dụng --' ?></option>
                        <?php foreach ($availableVouchers as $item): ?>
                            <?php $vCode = (string) $item['voucher']['code']; ?>
                            <option value="<?= e($vCode) ?>" <?= $appliedCode === $vCode ? 'selected' : '' ?> <?= !$item['eligible'] ? 'disabled' : '' ?>>
                                <?= e($item['label']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <button type="submit" formaction="<?= url('/voucher/apply') ?>" formmethod="post" formnovalidate class="btn btn-sm btn-primary" style="white-space: nowrap;">Áp dụng</button>
                </div>
                <small data-voucher-desc class="<?= $checkoutVoucher ? 'text-success' : 'muted' ?>" style="display: block; margin-top: 6px; font-size: 12px;">
                    <?php if ($checkoutVoucher): ?>
                        ✓ Đang áp dụng <strong><?= e($checkoutVoucher['voucher']['code']) ?></strong>: <?= e($checkoutVoucher['description']) ?>
                    <?php endif ?>
                </small>
            </div>

            <div id="checkout-order-summary">
                <?= $this->insert('partials/order-summary', ['totals' => $totals]) ?>
            </div>
            <button class="btn btn-lg btn-block" type="submit">Đặt hàng</button>
            <p class="muted small">Bằng việc đặt hàng, bạn đồng ý với điều khoản mua hàng của chúng tôi.</p>
        </aside>
    </form>
</section>
