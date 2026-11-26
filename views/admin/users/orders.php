<?php
use App\Models\Order;

/** @var array<string, mixed> $user */
/** @var array<int, array<string, mixed>> $orders */

$this->extend('layouts/admin', [
    'title' => 'Lịch sử mua hàng - ' . ($user['name'] ?? ''),
    'heading' => 'Lịch sử mua hàng: ' . ($user['name'] ?? ''),
]);
?>
<div class="card section-sm">
    <p>
        <strong>Email:</strong> <?= e((string) ($user['email'] ?? '—')) ?> ·
        <strong>SĐT:</strong> <?= e((string) ($user['phone'] ?? '—')) ?> ·
        <strong>Vai trò:</strong> <?= e((string) ($user['role'] ?? 'customer')) ?>
    </p>
    <a href="<?= url('/admin/users') ?>">← Quay lại danh sách người dùng</a>
</div>

<?php if ($orders === []): ?>
    <div class="card empty">Người dùng này chưa có đơn hàng nào.</div>
<?php else: ?>
    <?php foreach ($orders as $ord): ?>
        <div class="card section-sm">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <a href="<?= url('/admin/orders/' . (int) $ord['id']) ?>"><strong><?= e((string) $ord['code']) ?></strong></a>
                    <span class="muted">· Đặt lúc <?= e(date('d/m/Y H:i', strtotime((string) $ord['created_at']))) ?></span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?= $this->insert('partials/status-pill', ['status' => $ord['status']]) ?>
                    <strong><?= money((int) $ord['total']) ?></strong>
                </div>
            </div>

            <?php if (!empty($ord['items'])): ?>
                <table class="table" style="margin-top:10px;">
                    <thead><tr><th>Sản phẩm</th><th>Phân loại</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>
                    <tbody>
                    <?php foreach ($ord['items'] as $it): ?>
                        <tr>
                            <td><?= e((string) $it['product_name']) ?></td>
                            <td><?= e((string) ($it['variant_label'] ?? '—')) ?></td>
                            <td><?= money((int) $it['price']) ?></td>
                            <td><?= (int) $it['quantity'] ?></td>
                            <td><?= money((int) $it['total']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>
    <?php endforeach ?>
<?php endif ?>