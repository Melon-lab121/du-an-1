<?php
$this->extend('layouts/main', ['title' => 'Đặt hàng']);
/** @var array<string, App\Services\Payment\PaymentGatewayInterface> $gateways */
$isGuest = empty($user);
$selected = old('payment_method', array_key_first($gateways));
$field = static fn (string $name, string $fallback = ''): string => e(old($name, $fallback));
$checkoutSelectedKeys = $selectedKeys ?? array_map(static fn (array $l): string => (string) $l['key'], $lines);
?>
<section class="container section">
    <h1><?= $isGuest ? 'Thông tin liên hệ &amp; giao hàng (Khách vãng lai)' : 'Thông tin đặt hàng' ?></h1>

    <form action="<?= url('/checkout') ?>" method="post" class="checkout-layout" novalidate>
        <?= csrf_field() ?>
        <?php foreach ($checkoutSelectedKeys as $selKey): ?>
            <input type="hidden" name="selected_keys[]" value="<?= e((string) $selKey) ?>">
        <?php endforeach ?>

        <div class="card">
            <h3>Người nhận</h3>
            <div class="form-grid">
                <label class="field">
                    <span>Số điện thoại *</span>
                    <input name="phone" value="<?= $field('phone', $user['phone'] ?? '') ?>" required autocomplete="tel" inputmode="tel" placeholder="VD: 0912345678">
                    <?php if ($err = error('phone')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <label class="field">
                    <span>Họ tên *</span>
                    <input name="name" value="<?= $field('name', $user['name'] ?? '') ?>" required autocomplete="name" placeholder="Nhập họ và tên">
                    <?php if ($err = error('name')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <label class="field field-full">
                    <span>Địa chỉ giao hàng *</span>
                    <input name="address" value="<?= $field('address', $user['address'] ?? '') ?>" required autocomplete="street-address" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành">
                    <?php if ($err = error('address')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                </label>
                <?php if (!$isGuest): ?>
                    <label class="field field-full">
                        <span>Email *</span>
                        <input type="email" name="email" value="<?= $field('email', $user['email'] ?? '') ?>" required autocomplete="email">
                        <?php if ($err = error('email')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                    </label>
                    <label class="field field-full">
                        <span>Ghi chú</span>
                        <textarea name="note" rows="3"><?= $field('note') ?></textarea>
                    </label>
                <?php else: ?>
                    <input type="hidden" name="email" value="<?= $field('email', 'guest@noithatluxe.local') ?>">
                    <input type="hidden" name="is_guest_inquiry" value="1">
                <?php endif ?>
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
            <h3>Đơn hàng (<?= (int) array_sum(array_column($lines, 'quantity')) ?> sản phẩm)</h3>
            <ul class="mini-lines">
                <?php foreach ($lines as $line): ?>
                    <li>
                        <span><?= e((string) $line['name']) ?><?= !empty($line['variant_label']) ? ' (' . e((string) $line['variant_label']) . ')' : '' ?> × <?= (int) $line['quantity'] ?></span>
                        <strong><?= money((int) $line['total']) ?></strong>
                    </li>
                <?php endforeach ?>
            </ul>

            <?= $this->insert('partials/voucher-box', [
                'inForm' => true,
                'lines' => $lines,
                'availableVouchers' => $availableVouchers ?? null,
                'appliedVoucher' => $checkoutVoucher ?? null,
            ]) ?>

            <div id="checkout-order-summary" data-voucher-summary>
                <?= $this->insert('partials/order-summary', ['totals' => $totals]) ?>
            </div>
            <button class="btn btn-lg btn-block" type="submit"><?= $isGuest ? 'Gửi thông tin đặt hàng' : 'Đặt hàng' ?></button>
            <p class="muted small">Bằng việc đặt hàng, bạn đồng ý với điều khoản mua hàng của chúng tôi.</p>
        </aside>
    </form>
</section>