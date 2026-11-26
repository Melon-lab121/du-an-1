<?php
$this->extend('layouts/main', ['title' => 'Đơn hàng ' . $order['code']]);
$canPay =$order['payment_method'] !== 'cod' && $order['payment_status'] !== 'paid' &&$order['status'] !== 'cancelled';
$isCompleted = in_array((string) ($order['status'] ?? ''), ['completed', 'delivered'], true);
?>
<section class="container section">
    <nav class="breadcrumb"><a href="<?= url('/') ?>">Trang chủ</a> / <?php if (auth()->check()): ?><a href="<?= url('/orders') ?>">Đơn hàng</a> / <?php endif ?><span><?= e($order['code']) ?></span></nav>

    <div class="order-head">
        <div>
            <h1>Đơn hàng <?= e($order['code']) ?></h1>
            <p class="muted">Đặt lúc <?= e(date('H:i d/m/Y', strtotime((string) $order['created_at']))) ?></p>
        </div>
        <div class="order-pills">
            <?= $this->insert('partials/status-pill', ['status' =>$order['status']]) ?>
            <?= $this->insert('partials/status-pill', ['status' =>$order['payment_status'], 'type' => 'payment']) ?>
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

    <div class="cart-layout">
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Đơn giá</th>
                        <th>SL</th>
                        <th>Thành tiền</th>
                        <?php if ($isCompleted): ?>
                            <th>Đánh giá</th>
                        <?php endif ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as$item): ?>
                    <?php
                    $productSlug = (string) ($item['product_slug'] ?? '');
                    $productId = (int) ($item['product_id'] ?? 0);
                    $reviewUrl =$productSlug !== ''
                        ? url('/product/' . urlencode($productSlug) . '#product-comments')
                        : ($productId > 0 ? url('/product/' . $productId . '#product-comments') : null);
                    ?>
                    <tr>
                        <td>
                            <?= e($item['product_name']) ?>
                            <?php if (!empty($item['variant_label'])): ?>
                                <br><small class="muted"><?= e($item['variant_label']) ?></small>
                            <?php endif ?>
                        </td>
                        <td data-label="Đơn giá"><?= money($item['price']) ?></td>
                        <td data-label="SL"><?= (int) $item['quantity'] ?></td>
                        <td data-label="Thành tiền"><strong><?= money($item['total']) ?></strong></td>
                        <?php if ($isCompleted): ?>
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
        <aside class="card">
            <h3>Giao đến</h3>
            <p><strong><?= e($order['customer_name']) ?></strong> · <?= e($order['customer_phone']) ?><br>
                <?= e($order['customer_email']) ?><br><?= e($order['shipping_address']) ?></p>
            <?php if ($order['note']): ?><p class="muted">Ghi chú: <?= e($order['note']) ?></p><?php endif ?>
            <?= $this->insert('partials/order-summary', ['totals' => [
                'subtotal' => (int) $order['subtotal'], 'discount' => (int)$order['discount'],
                'shipping_fee' => (int) $order['shipping_fee'], 'total' => (int)$order['total'],
                'voucher_code' => $order['voucher_code'] ?? null,
            ]]) ?>
            <p class="muted small">Thanh toán: <?= e(strtoupper($order['payment_method'])) ?></p>
        </aside>
    </div>
</section>