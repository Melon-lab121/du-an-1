<?php /** @var array<int, array<string, mixed>> $orders */ ?>
<?php if (!$orders): ?>
    <div class="empty">Chưa có đơn hàng.</div>
<?php else: ?>
    <table class="table">
        <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Tổng tiền</th><th>PTTT</th><th>Thanh toán</th><th>Trạng thái</th><th>Ngày đặt</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><a href="<?= url('/admin/orders/' . (int) $order['id']) ?>"><strong><?= e($order['code']) ?></strong></a></td>
                <td data-label="Khách hàng"><?= e($order['customer_name']) ?><br><small class="muted"><?= e($order['customer_phone']) ?></small></td>
                <td data-label="Tổng tiền"><?= money($order['total']) ?></td>
                <td data-label="PTTT"><?= e(strtoupper($order['payment_method'])) ?></td>
                <td data-label="Thanh toán"><?= $this->insert('partials/status-pill', ['status' => $order['payment_status'], 'type' => 'payment']) ?></td>
                <td data-label="Trạng thái"><?= $this->insert('partials/status-pill', ['status' => $order['status']]) ?></td>
                <td data-label="Ngày đặt"><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
