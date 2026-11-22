<?php $this->extend('layouts/admin', ['title' => 'Tổng quan', 'heading' => 'Tổng quan']) ?>

<div class="stat-grid">
    <div class="card stat"><span class="muted">Doanh thu (đã thanh toán)</span><strong><?= money($stats['revenue']) ?></strong></div>
    <div class="card stat"><span class="muted">Tổng đơn hàng</span><strong><?= number_format($stats['orders']) ?></strong></div>
    <div class="card stat"><span class="muted">Đơn chờ xác nhận</span><strong><?= number_format($stats['pending']) ?></strong></div>
    <div class="card stat"><span class="muted">Sản phẩm</span><strong><?= number_format($stats['products']) ?></strong></div>
    <div class="card stat"><span class="muted">Khách hàng</span><strong><?= number_format($stats['customers']) ?></strong></div>
    <div class="card stat"><span class="muted">Sắp hết hàng (≤ 5)</span><strong><?= number_format($stats['low_stock']) ?></strong></div>
</div>

<div class="card section">
    <div class="section-head"><h3>Đơn hàng gần đây</h3><a href="<?= url('/admin/orders') ?>">Xem tất cả →</a></div>
    <?= $this->insert('admin/orders/table', ['orders' => $recentOrders]) ?>
</div>
