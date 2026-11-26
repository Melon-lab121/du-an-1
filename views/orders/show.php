<?php
use App\Models\Order;

$this->extend('layouts/main', ['title' => 'Đơn hàng ' . $order['code']]);
$canPay = $order['payment_method'] !== 'cod' && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled';
$canCancel = Order::canCustomerCancel($order);
$canConfirmReceived = Order::canConfirmReceived($order);
$canReview = Order::canReviewItems($order);
$canReturn = Order::canRequestReturn($order);
$isPaid = ($order['payment_status'] ?? '') === 'paid';
$returns = $returns ?? [];
?>
<section class="container section">
    <nav class="breadcrumb"><a href="<?= url('/') ?>">Trang chủ</a> / <?php if (auth()->check()): ?><a href="<?= url('/orders') ?>">Đơn hàng</a> / <?php endif ?><span><?= e($order['code']) ?></span></nav>

    <div class="order-head">
        <div>
            <h1>Đơn hàng <?= e($order['code']) ?></h1>
            <p class="muted">
                Đặt lúc <?= e(date('H:i d/m/Y', strtotime((string) $order['created_at']))) ?>
                · Hình thức: <strong><?= Order::isOnlineMethod($order) ? 'Thanh toán Online (' . e(strtoupper((string) $order['payment_method'])) . ')' : 'COD' ?></strong>
                <?php if (($order['refund_status'] ?? 'none') === 'pending_refund'): ?>
                    · <span class="text-warning">Chờ hoàn tiền</span>
                <?php elseif (($order['refund_status'] ?? 'none') === 'refunded'): ?>
                    · <span class="text-success">Đã hoàn tiền</span>
                <?php endif ?>
            </p>
        </div>
        <div class="order-pills">
            <?= $this->insert('partials/status-pill', ['status' => $order['status']]) ?>
            <?= $this->insert('partials/status-pill', ['status' => $order['payment_status'], 'type' => 'payment']) ?>
        </div>
    </div>

    <?php if ($canPay): ?>
        <div class="alert alert-info pay-again">
            <span>Đơn hàng chưa được thanh toán qua <?= e(strtoupper($order['payment_method'])) ?>.</span>
            <form action="<?= route('order.pay', ['code' => $order['code']]) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn" type="submit">Thanh toán ngay</button>
            </form>
        </div>
    <?php endif ?>

    <div class="order-actions section-sm" style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if ($canCancel): ?>
            <form action="<?= url('/orders/' . urlencode((string) $order['code']) . '/cancel') ?>" method="post" onsubmit="return confirm('Xác nhận hủy đơn hàng này?');">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit" style="color:#dc2626;">Hủy đơn hàng</button>
            </form>
        <?php endif ?>

        <?php if ($canConfirmReceived): ?>
            <form action="<?= url('/orders/' . urlencode((string) $order['code']) . '/confirm-received') ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">Xác nhận đã nhận hàng</button>
            </form>
        <?php endif ?>
    </div>

    <div class="cart-layout">
        <div>
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>SL</th>
                            <th>Thành tiền</th>
                            <?php if ($canReview): ?>
                                <th>Đánh giá</th>
                            <?php endif ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $slug = (string) ($item['product_slug'] ?? '');
                        $pid = (int) ($item['product_id'] ?? 0);
                        $reviewUrl = $slug !== ''
                            ? url('/product/' . urlencode($slug) . '#product-comments')
                            : ($pid > 0 ? url('/product/' . $pid . '#product-comments') : null);
                        ?>
                        <tr>
                            <td>
                                <?= e($item['product_name']) ?>
                                <?php if (!empty($item['variant_label'])): ?><br><small class="muted"><?= e($item['variant_label']) ?></small><?php endif ?>
                            </td>
                            <td data-label="Đơn giá"><?= money($item['price']) ?></td>
                            <td data-label="SL"><?= (int) $item['quantity'] ?></td>
                            <td data-label="Thành tiền"><strong><?= money($item['total']) ?></strong></td>
                            <?php if ($canReview): ?>
                                <td data-label="Đánh giá">
                                    <?php if ($reviewUrl !== null): ?>
                                        <a href="<?= $reviewUrl ?>" class="btn btn-sm">Đánh giá sản phẩm</a>
                                    <?php endif ?>
                                </td>
                            <?php endif ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canReturn && $returns === []): ?>
                <div class="card section" id="return-box">
                    <h3>Trả lại hàng (trong vòng 7 ngày kể từ khi giao thành công)</h3>
                    <form action="<?= url('/orders/' . urlencode((string) $order['code']) . '/return') ?>" method="post" enctype="multipart/form-data" class="stack">
                        <?= csrf_field() ?>
                        <label class="field">
                            <span>Lý do trả hàng *</span>
                            <select name="reason" required>
                                <option value="">-- Chọn lý do --</option>
                                <?php foreach (Order::RETURN_REASONS as $val => $label): ?>
                                    <option value="<?= e($val) ?>"><?= e($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </label>

                        <label class="field">
                            <span>Ảnh minh chứng *</span>
                            <input type="file" name="proof_image" accept=".jpg,.jpeg,.png,.webp" required>
                        </label>

                        <label class="field">
                            <span>Mô tả chi tiết<?= $isPaid ? ' (vui lòng ghi rõ Số tài khoản + Tên ngân hàng để hoàn tiền)' : '' ?> *</span>
                            <textarea name="description" rows="3" required placeholder="<?= $isPaid ? 'Mô tả tình trạng hàng và điền rõ STK ngân hàng để nhận hoàn tiền...' : 'Mô tả tình trạng sản phẩm...' ?>"></textarea>
                        </label>

                        <div>
                            <button type="submit" class="btn">Gửi yêu cầu trả lại hàng</button>
                        </div>
                    </form>
                </div>
            <?php endif ?>

            <?php if ($returns !== []): ?>
                <div class="card section">
                    <h3>Yêu cầu trả lại hàng</h3>
                    <?php foreach ($returns as $ret): ?>
                        <div style="padding:10px 0;border-bottom:1px solid var(--border, #e5e7eb);">
                            <div>
                                <strong>Lý do:</strong> <?= e(Order::RETURN_REASONS[$ret['reason']] ?? (string) $ret['reason']) ?>
                                · <span class="tag"><?= e((string) $ret['status']) ?></span>
                            </div>
                            <p style="margin:6px 0;white-space:pre-line;"><?= e((string) $ret['description']) ?></p>
                            <?php if (!empty($ret['proof_image'])): ?>
                                <a href="<?= url((string) $ret['proof_image']) ?>" target="_blank" rel="noopener">Xem ảnh minh chứng</a>
                            <?php endif ?>
                            <?php if (!empty($ret['admin_note'])): ?>
                                <p class="muted small" style="margin:4px 0 0;">Phản hồi admin: <?= e((string) $ret['admin_note']) ?></p>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <aside class="card">
            <h3>Giao đến</h3>
            <p><strong><?= e($order['customer_name']) ?></strong> · <?= e($order['customer_phone']) ?><br>
                <?= e($order['customer_email']) ?><br><?= e($order['shipping_address']) ?></p>
            <?php if ($order['note']): ?><p class="muted">Ghi chú: <?= e($order['note']) ?></p><?php endif ?>
            <?= $this->insert('partials/order-summary', ['totals' => [
                'subtotal' => (int) $order['subtotal'], 'discount' => (int) $order['discount'],
                'shipping_fee' => (int) $order['shipping_fee'], 'total' => (int) $order['total'],
                'voucher_code' => $order['voucher_code'] ?? null,
            ]]) ?>
            <p class="muted small">Thanh toán: <?= e(strtoupper($order['payment_method'])) ?></p>
        </aside>
    </div>
</section>