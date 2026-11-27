<?php
use App\Models\Order;

$history = $history ?? [];
$nextStatuses = $nextStatuses ?? [];
$failedAttempts = $failedAttempts ?? 0;

$this->extend('layouts/admin', ['title' => 'Đơn ' . $order['code'], 'heading' => 'Đơn hàng ' . $order['code']]);
?>
<div class="order-pills section-sm">
    <?= $this->insert('partials/status-pill', ['status' => $order['status']]) ?>
    <?= $this->insert('partials/status-pill', ['status' => $order['payment_status'], 'type' => 'payment']) ?>
    <span class="muted">Đặt lúc <?= e(date('H:i d/m/Y', strtotime((string) $order['created_at']))) ?></span>
</div>

<?php if ($order['payment_status'] === 'refund_needed'): ?>
    <div class="card section-sm">
        <p><strong>Cần hoàn tiền cho khách: <?= money($order['total']) ?></strong><br>
            <span class="muted">Khách đã thanh toán nhưng đơn đã bị huỷ. Hãy hoàn tiền thủ công qua cổng thanh toán rồi liên hệ khách.</span></p>
        <form action="<?= url('/admin/orders/' . (int) $order['id'] . '/refunded') ?>" method="post" class="stack">
            <?= csrf_field() ?>
            <input type="text" name="note" maxlength="255" placeholder="Ghi chú (vd: mã giao dịch hoàn tiền)">
            <button class="btn btn-sm" type="submit" data-confirm="Xác nhận đã hoàn tiền cho khách?">Đã hoàn tiền</button>
        </form>
    </div>
<?php endif ?>

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
            <h3>Lịch sử trạng thái</h3>
            <?php if (!$history): ?>
                <p class="muted">Chưa có lịch sử.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Thời gian</th><th>Từ</th><th>Sang</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= e(date('H:i d/m/Y', strtotime((string) $h['created_at']))) ?></td>
                            <td data-label="Từ"><?= $h['from_status'] !== null ? e(Order::statusLabel((string) $h['from_status'])) : '—' ?></td>
                            <td data-label="Sang"><?= $this->insert('partials/status-pill', ['status' => $h['to_status']]) ?></td>
                            <td data-label="Người thực hiện">
                                <?php if ($h['changed_by'] === null): ?>
                                    Hệ thống
                                <?php else: ?>
                                    <?= e($h['changed_by_name'] ?? ('#' . (int) $h['changed_by'])) ?>
                                <?php endif ?>
                            </td>
                            <td data-label="Ghi chú"><?= e($h['note'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
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
            <?php if ($nextStatuses === []): ?>
                <?php if ($order['status'] === 'delivered'): ?>
                    <p class="muted">Đang chờ khách xác nhận đã nhận hàng. Nếu khách không xác nhận, đơn tự chuyển sang Hoàn thành sau <?= Order::AUTO_COMPLETE_DAYS ?> ngày kể từ lúc giao hàng thành công (admin không bấm được bước này).</p>
                <?php else: ?>
                    <p class="muted">Đơn đang ở trạng thái cuối (<?= e(Order::statusLabel((string) $order['status'])) ?>), không thể đổi tiếp.</p>
                <?php endif ?>
            <?php else: ?>
                <form action="<?= url('/admin/orders/' . (int) $order['id'] . '/status') ?>" method="post" class="stack">
                    <?= csrf_field() ?>
                    <select name="status">
                        <?php foreach ($nextStatuses as $value): ?>
                            <option value="<?= e($value) ?>"><?= e(Order::statusLabel($value)) ?></option>
                        <?php endforeach ?>
                    </select>
                    <input type="text" name="note" maxlength="255" placeholder="Ghi chú (không bắt buộc)">
                    <button class="btn btn-block" type="submit" data-confirm="Cập nhật trạng thái đơn hàng?">Lưu</button>
                    <small class="muted">Chỉ huỷ được khi đơn ở Chờ xác nhận, Đã xác nhận, Đang đóng gói (hoặc sau <?= Order::MAX_DELIVERY_ATTEMPTS ?> lần lấy/giao hàng thất bại). Huỷ đơn sẽ tự hoàn lại tồn kho; đơn đã thanh toán mà huỷ sẽ chuyển sang "Cần hoàn tiền".</small>
                </form>
            <?php endif ?>
        </div>
        <?php if (in_array($order['status'], Order::DELIVERY_STAGES, true)): ?>
            <?php $isPickup = $order['status'] === 'awaiting_pickup'; ?>
            <div class="card section-sm">
                <h3><?= $isPickup ? 'Lấy hàng thất bại' : 'Giao hàng thất bại' ?></h3>
                <p class="muted">Đã ghi nhận <?= (int) $failedAttempts ?>/<?= Order::MAX_DELIVERY_ATTEMPTS ?> lần. Thất bại đủ <?= Order::MAX_DELIVERY_ATTEMPTS ?> lần mới được huỷ đơn.</p>
                <?php if ($failedAttempts < Order::MAX_DELIVERY_ATTEMPTS): ?>
                    <form action="<?= url('/admin/orders/' . (int) $order['id'] . '/delivery-failed') ?>" method="post" class="stack">
                        <?= csrf_field() ?>
                        <input type="text" name="note" maxlength="200" placeholder="Lý do (không bắt buộc)">
                        <button class="btn btn-ghost btn-block" type="submit" data-confirm="Ghi nhận <?= $isPickup ? 'lấy hàng' : 'giao hàng' ?> thất bại lần <?= (int) $failedAttempts + 1 ?>?">Ghi nhận thất bại lần <?= (int) $failedAttempts + 1 ?></button>
                    </form>
                <?php endif ?>
            </div>
        <?php endif ?>
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
