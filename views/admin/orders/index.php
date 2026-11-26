<?php
use App\Models\Order;

/** @var array<int, array<string, mixed>> $guestInquiries */

$this->extend('layouts/admin', ['title' => 'Đơn hàng', 'heading' => 'Đơn hàng']);
$guestInquiries = $guestInquiries ?? [];
?>
<div class="card">
    <form class="toolbar" method="get">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Mã đơn, tên, SĐT...">
        <select name="status">
            <option value="">Tất cả trạng thái</option>
            <?php foreach (Order::STATUSES as $value => $label): ?>
                <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach ?>
        </select>
        <button class="btn btn-sm" type="submit">Lọc</button>
    </form>
    <?= $this->insert('admin/orders/table', ['orders' => $pager['data']]) ?>
    <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
</div>

<div class="card section">
    <div class="section-head">
        <h3>Danh sách đặt hàng của khách vãng lai</h3>
        <span class="muted">SĐT, Tên, Địa chỉ giao hàng để CSKH liên hệ và tạo đơn sau</span>
    </div>
    <?php if ($guestInquiries === []): ?>
        <div class="dash-empty">Chưa có thông tin đặt hàng từ khách vãng lai.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Số điện thoại</th>
                    <th>Họ tên</th>
                    <th>Địa chỉ giao hàng</th>
                    <th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($guestInquiries as $gi): ?>
                <tr>
                    <td data-label="Số điện thoại"><strong><?= e((string) $gi['phone']) ?></strong></td>
                    <td data-label="Họ tên"><?= e((string) $gi['name']) ?></td>
                    <td data-label="Địa chỉ giao hàng"><?= e((string) $gi['address']) ?></td>
                    <td data-label="Thời gian"><?= e(date('d/m/Y H:i', strtotime((string) $gi['created_at']))) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>