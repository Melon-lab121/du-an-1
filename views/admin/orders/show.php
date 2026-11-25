<?php
use App\Models\Order;

$this->extend('layouts/admin', ['title' => 'Đơn ' . $order['code'], 'heading' => 'Đơn hàng ' . $order['code']]);
?>
<div class="order-pills section-sm">
    <?= $this->insert('partials/status-pill', ['status' => $order['status']]) ?>
    <?= $this->insert('partials/status-pill', ['status' => $order['payment_status'], 'type' => 'payment']) ?>
    <span class="muted">Đặt lúc <?= e(date('H:i d/m/Y', strtotime((string) $order['created_at']))) ?></span>
</div>

<div class="cart-layout">
    <div>
        <div class="card">
            <h3>Sản phẩm</h3>
            <table class="table">
                <thead><tr><th>Sản phẩm</th><th>SKU</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>
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

        <div class="card section">
            <h3>Lịch sử thanh toán</h3>
            <?php if (!$payments): ?>
                <p class="muted">Chưa có giao dịch online.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Cổng</th><th>TxnRef</th><th>Số tiền</th><th>Mã GD</th><th>Mã phản hồi</th><th>Trạng thái</th><th></th></tr></thead>
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
                <select name="status" <?= $order['status'] === 'cancelled' ? 'disabled' : '' ?>>
                    <?php foreach (Order::STATUSES as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $order['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach ?>
                </select>
                <button class="btn btn-block" type="submit" <?= $order['status'] === 'cancelled' ? 'disabled' : '' ?> data-confirm="Cập nhật trạng thái đơn hàng?">Lưu</button>
                <small class="muted">Huỷ đơn sẽ tự hoàn lại tồn kho.</small>
            </form>
        </div>
        <div class="card section-sm">
            <h3>Khách hàng</h3>
            <p><strong><?= e($order['customer_name']) ?></strong><br><?= e($order['customer_phone']) ?><br><?= e($order['customer_email']) ?><br><?= e($order['shipping_address']) ?></p>
            <?php if ($order['note']): ?><p class="muted">Ghi chú: <?= e($order['note']) ?></p><?php endif ?>
        </div>
        <div class="card section-sm">
            <?= $this->insert('partials/order-summary', ['totals' => [
                'subtotal' => (int) $order['subtotal'], 'discount' => (int) $order['discount'],
                'shipping_fee' => (int) $order['shipping_fee'], 'total' => (int) $order['total'],
                'voucher_code' => $order['voucher_code'] ?? null,
            ]]) ?>
        </div>
    </aside>
</div>
