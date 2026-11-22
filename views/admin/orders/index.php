<?php
use App\Models\Order;

$this->extend('layouts/admin', ['title' => 'Đơn hàng', 'heading' => 'Đơn hàng']);
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
