<?php $this->extend('layouts/main', ['title' => 'Đơn hàng của tôi']) ?>

<section class="container section">
    <h1>Đơn hàng của tôi</h1>
    <?php if (!$pager['data']): ?>
        <div class="empty"><p>Bạn chưa có đơn hàng nào.</p><a class="btn" href="<?= url('/products') ?>">Mua sắm ngay</a></div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th></tr></thead>
                <tbody>
                <?php foreach ($pager['data'] as $order): ?>
                    <tr>
                        <td><a href="<?= route('order.show', ['code' => $order['code']]) ?>"><strong><?= e($order['code']) ?></strong></a></td>
                        <td data-label="Ngày đặt"><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                        <td data-label="Tổng tiền"><?= money($order['total']) ?></td>
                        <td data-label="Thanh toán"><?= $this->insert('partials/status-pill', ['status' => $order['payment_status'], 'type' => 'payment']) ?></td>
                        <td data-label="Trạng thái"><?= $this->insert('partials/status-pill', ['status' => $order['status']]) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
    <?php endif ?>
</section>