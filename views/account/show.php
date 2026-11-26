<?php
$this->extend('layouts/main', ['title' => 'Tài khoản của tôi']);
$user = $user ?? [];
$orders = $orders ?? [];
$errors = $errors ?? [];
?>

<section class="container stack" style="padding:24px 0;gap:24px;">
    <div>
        <h1>Tài khoản của tôi</h1>
        <p class="muted">Quản lý thông tin cá nhân và theo dõi lịch sử mua hàng.</p>
    </div>

    <div class="card stack" style="max-width:720px;">
        <h2>Thông tin tài khoản</h2>

        <form action="<?= url('/account') ?>" method="post" class="stack" style="gap:14px;">
            <?= csrf_field() ?>

            <div>
                <label class="form-label" for="acc-email">Email</label>
                <input id="acc-email" type="email" class="form-control" value="<?= e((string) ($user['email'] ?? '')) ?>" disabled>
            </div>

            <div>
                <label class="form-label" for="acc-name">Họ và tên</label>
                <input id="acc-name" name="name" type="text" class="form-control" required maxlength="120"
                    value="<?= e((string) old('name', $user['name'] ?? '')) ?>">
                <?php if (!empty($errors['name'])): ?>
                    <div class="text-danger small"><?= e($errors['name']) ?></div>
                <?php endif ?>
            </div>

            <div>
                <label class="form-label" for="acc-phone">Số điện thoại</label>
                <input id="acc-phone" name="phone" type="text" class="form-control" maxlength="30"
                    value="<?= e((string) old('phone', $user['phone'] ?? '')) ?>">
                <?php if (!empty($errors['phone'])): ?>
                    <div class="text-danger small"><?= e($errors['phone']) ?></div>
                <?php endif ?>
            </div>

            <div>
                <label class="form-label" for="acc-address">Địa chỉ nhận hàng</label>
                <input id="acc-address" name="address" type="text" class="form-control" maxlength="255"
                    value="<?= e((string) old('address', $user['address'] ?? '')) ?>">
                <?php if (!empty($errors['address'])): ?>
                    <div class="text-danger small"><?= e($errors['address']) ?></div>
                <?php endif ?>
            </div>

            <div>
                <button type="submit" class="btn">Lưu thay đổi</button>
            </div>
        </form>
    </div>

    <div class="card stack">
        <h2>Lịch sử mua hàng (<?= count($orders) ?>)</h2>

        <?php if ($orders === []): ?>
            <p class="muted">Bạn chưa có đơn hàng nào.</p>
        <?php else: ?>
            <div class="stack" style="gap:16px;">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $orderCode = !empty($order['code']) ? (string) $order['code'] : ('#' . (int) $order['id']);
                    $statusLabel = match ((string) ($order['status'] ?? '')) {
                        'pending' => 'Chờ xác nhận',
                        'confirmed', 'processing' => 'Đang xử lý',
                        'shipping' => 'Đang giao hàng',
                        'completed', 'delivered' => 'Hoàn thành',
                        'cancelled' => 'Đã huỷ',
                        default => (string) ($order['status'] ?? 'Đã đặt'),
                    };
                    ?>
                    <div class="card stack" style="gap:10px;padding:16px;">
                        <?php
                        $status = (string) ($order['status'] ?? '');
                        $isCompleted = in_array($status, ['completed', 'delivered'], true);
                        ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <div>
                                <strong>Đơn hàng <?= e($orderCode) ?></strong>
                                <span class="muted small"> • <?= e((string) ($order['created_at'] ?? '')) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($order['items'])): ?>
                            <div style="overflow-x:auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Sản phẩm</th>
                                            <th>Số lượng</th>
                                            <th>Đơn giá</th>
                                            <th>Thành tiền</th>
                                            <?php if ($isCompleted): ?>
                                                <th>Đánh giá</th>
                                            <?php endif ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <?php
                                            $qty = max(1, (int) ($item['quantity'] ?? 1));
                                            $unitPrice = (int) ($item['price'] ?? 0);
                                            $lineTotal = $qty * $unitPrice;
                                            $productName = (string) ($item['product_name'] ?? ('Sản phẩm #' . (int) $item['product_id']));
                                            $productSlug = (string) ($item['product_slug'] ?? '');
                                            $productUrl = $productSlug !== ''
                                                ? url('/product/' . urlencode($productSlug))
                                                : (!empty($item['product_id']) ? url('/product/' . (int) $item['product_id']) : null);
                                            $reviewUrl = $productUrl !== null ? ($productUrl . '#product-comments') : null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <?php if ($productUrl !== null): ?>
                                                        <a href="<?= $productUrl ?>"><?= e($productName) ?></a>
                                                    <?php else: ?>
                                                        <?= e($productName) ?>
                                                    <?php endif ?>
                                                </td>
                                                <td><?= $qty ?></td>
                                                <td><?= number_format($unitPrice, 0, ',', '.') ?>₫</td>
                                                <td><?= number_format($lineTotal, 0, ',', '.') ?>₫</td>
                                                <?php if ($isCompleted): ?>
                                                    <td>
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
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>