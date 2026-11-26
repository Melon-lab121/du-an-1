<?php

use App\Models\Order;

$this->extend('layouts/admin', ['title' => 'Đơn ' . $order['code'], 'heading' => 'Đơn hàng ' . $order['code']]);
$currentStatus = (string) ($order['status'] ?? 'pending');
$currentRank = Order::LINEAR_ORDER[$currentStatus] ?? 0;
$isTerminal = in_array($currentStatus, ['cancelled', 'completed'], true);
$attempts = (int) ($order['shipping_attempts'] ?? 0);
$needsRefund = (($order['refund_status'] ?? 'none') === 'pending_refund')
    || (Order::isOnlineMethod($order) && ($order['payment_status'] ?? '') === 'paid' && in_array($currentStatus, ['cancelled', 'returned', 'delivery_failed'], true));
$returns = $returns ?? Order::returns((int) $order['id']);
?>
<div class="order-pills section-sm">
    <?= $this->insert('partials/status-pill', ['status' => $order['status']]) ?>
    <?= $this->insert('partials/status-pill', ['status' => $order['payment_status'], 'type' => 'payment']) ?>
    <span class="muted">Đặt lúc <?= e(date('H:i d/m/Y', strtotime((string) $order['created_at']))) ?></span>
    <span class="muted">· Lần giao ĐVVC: <strong><?= $attempts ?>/<?= Order::MAX_SHIPPING_ATTEMPTS ?></strong></span>
</div>

<div class="cart-layout">
    <div>
        <div class="card">
            <h3>Sản phẩm</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>SKU</th>
                        <th>Đơn giá</th>
                        <th>SL</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?><?php if (!empty($item['variant_label'])): ?><br><small class="muted"><?= e($item['variant_label']) ?></small><?php endif ?></td>
                            <td data-label="SKU"><?= e($item['sku'] ?? '—') ?></td>
                            <td data-label="Đơn giá"><?= money($item['price']) ?></td>
                            <td data-label="SL"><?= (int) $item['quantity'] ?></td>
                            <td data-label="Thành tiền"><?= money($item['total']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <?php if ($returns !== []): ?>
            <div class="card section">
                <h3>Yêu cầu trả lại hàng &amp; minh chứng</h3>
                <?php foreach ($returns as $ret): ?>
                    <div style="padding:12px 0;border-bottom:1px solid var(--border, #e5e7eb);">
                        <div>
                            <strong>#<?= (int) $ret['id'] ?></strong> · Lý do: <strong><?= e(Order::RETURN_REASONS[$ret['reason']] ?? (string) $ret['reason']) ?></strong>
                            · <span class="tag"><?= e((string) $ret['status']) ?></span>
                        </div>
                        <p style="margin:6px 0;white-space:pre-line;"><?= e((string) $ret['description']) ?></p>
                        <?php if (!empty($ret['proof_image'])): ?>
                            <div style="margin:6px 0;">
                                <a href="<?= url((string) $ret['proof_image']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= url((string) $ret['proof_image']) ?>" alt="Ảnh minh chứng" style="max-width:220px;border-radius:8px;border:1px solid var(--border, #ddd);">
                                </a>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <div class="card section">
            <h3>Lịch sử thanh toán</h3>
            <?php if (!$payments): ?>
                <p class="muted">Chưa có giao dịch online.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cổng</th>
                            <th>TxnRef</th>
                            <th>Số tiền</th>
                            <th>Mã GD</th>
                            <th>Mã phản hồi</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= e(strtoupper($p['gateway'])) ?></td>
                                <td data-label="TxnRef"><code><?= e($p['txn_ref']) ?></code></td>
                                <td data-label="Số tiền"><?= money($p['amount']) ?></td>
                                <td data-label="Mã GD"><?= e($p['transaction_no'] ?? '—') ?></td>
                                <td data-label="Mã phản hồi"><?= e($p['response_code'] ?? '—') ?></td>
                                <td data-label="Trạng thái"><?= $this->insert('partials/status-pill', ['status' => $p['status'], 'type' => 'transaction']) ?></td>
                                <td>
                                    <?php if ($p['gateway'] === 'sepay'): ?>
                                        <form action="<?= url('/admin/orders/' . (int) $order['id'] . '/payments/' . (int) $p['id'] . '/reconcile') ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-ghost" type="submit" title="Hỏi API chi tiết đơn của SePay">Đối soát</button>
                                        </form>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
    </div>

    <aside>
        <div class="card">
            <h3>Cập nhật trạng thái</h3>
            <form action="<?= url('/admin/orders/' . (int) $order['id'] . '/status') ?>" method="post" class="stack">
                <?= csrf_field() ?>
                <select name="status" <?= $isTerminal ? 'disabled' : '' ?>>
                    <?php foreach (Order::STATUSES as $value => $label): ?>
                        <?php
                        $optRank = Order::LINEAR_ORDER[$value] ?? 0;
                        $isBackward = ($optRank > 0 && $currentRank > 0 && $optRank < $currentRank) || ($value === 'pending' && $currentStatus !== 'pending');
                        $isCancelDisabled = ($value === 'cancelled' && $currentRank >= Order::LINEAR_ORDER['shipping']);
                        $isCompletedBlocked = ($value === 'completed');
                        $isDisabled = $isBackward || $isCancelDisabled || $isCompletedBlocked;
                        ?>
                        <option value="<?= $value ?>" <?= $currentStatus === $value ? 'selected' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>>
                            <?= $label ?><?= $isBackward ? ' (không thể quay lại)' : ($isCompletedBlocked ? ' (chỉ khách xác nhận / tự động sau 2 tuần)' : '') ?>
                        </option>
                    <?php endforeach ?>
                </select>

                <?php if ($currentStatus === 'shipping' && $attempts < Order::MAX_SHIPPING_ATTEMPTS): ?>
                    <label class="inline" style="gap:6px;">
                        <input type="checkbox" name="carrier_failed" value="1">
                        <span class="small">Ghi nhận lần giao tới ĐVVC thất bại (còn <?= Order::MAX_SHIPPING_ATTEMPTS - $attempts ?> lần)</span>
                    </label>
                <?php endif ?>

                <button class="btn btn-block" type="submit" <?= $isTerminal ? 'disabled' : '' ?> data-confirm="Cập nhật trạng thái đơn hàng?">Lưu</button>
                <small class="muted">Không thể quay lại trạng thái trước. Huỷ đơn chỉ áp dụng trước khi giao ĐVVC và tự hoàn tồn kho.</small>
            </form>

            <?php if ($needsRefund && (($order['payment_status'] ?? '') !== 'refunded')): ?>
                <form action="<?= url('/admin/orders/' . (int) $order['id'] . '/refund') ?>" method="post" class="stack" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border, #e5e7eb);">
                    <?= csrf_field() ?>
                    <strong>Xác nhận hoàn tiền</strong>
                    <textarea name="refund_note" rows="2" placeholder="Ghi chú chuyển khoản / mã hoàn tiền..."><?= e((string) ($order['refund_note'] ?? '')) ?></textarea>
                    <button class="btn btn-sm btn-primary" type="submit" data-confirm="Xác nhận đã hoàn tiền cho đơn hàng này?">Xác nhận hoàn tiền</button>
                </form>
            <?php endif ?>
        </div>

        <div class="card section-sm">
            <h3>Khách hàng</h3>
            <p><strong><?= e($order['customer_name']) ?></strong><br><?= e($order['customer_phone']) ?><br><?= e($order['customer_email']) ?><br><?= e($order['shipping_address']) ?></p>
            <?php if ($order['note']): ?><p class="muted">Ghi chú: <?= e($order['note']) ?></p><?php endif ?>
        </div>

        <div class="card section-sm">
            <?= $this->insert('partials/order-summary', ['totals' => [
                'subtotal' => (int) $order['subtotal'],
                'discount' => (int) $order['discount'],
                'shipping_fee' => (int) $order['shipping_fee'],
                'total' => (int) $order['total'],
                'voucher_code' => $order['voucher_code'] ?? null,
            ]]) ?>
        </div>
    </aside>
</div>