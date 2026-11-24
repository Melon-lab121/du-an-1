<?php $this->extend('layouts/admin', ['title' => 'Quản lý Voucher', 'heading' => 'Quản lý Voucher & Khuyến mãi']) ?>

<div class="card">
    <div class="toolbar">
        <div>
            <strong>Danh sách mã giảm giá (<?= count($vouchers) ?> mã)</strong>
        </div>
        <div class="inline">
            <a class="btn" href="<?= url('/admin/vouchers/create') ?>">+ Thêm Voucher</a>
        </div>
    </div>

    <?php if (!$vouchers): ?>
        <div class="empty">Chưa có mã voucher nào. Bấm "+ Thêm Voucher" để tạo mã đầu tiên.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Mã voucher</th>
                    <th>Loại voucher</th>
                    <th>Mức giảm</th>
                    <th>Đơn tối thiểu</th>
                    <th>Giới hạn/Khách</th>
                    <th>Đã dùng</th>
                    <th>Thời hạn</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vouchers as $v): ?>
                <?php
                    $now = date('Y-m-d H:i:s');
                    $isExpired = (!empty($v['end_date']) && $now > $v['end_date']);
                    $isPending = (!empty($v['start_date']) && $now < $v['start_date']);
                ?>
                <tr>
                    <td>
                        <strong style="letter-spacing: 0.5px; font-size: 14px;"><code><?= e($v['code']) ?></code></strong>
                    </td>
                    <td>
                        <?php if ((int)$v['voucher_type'] === 1): ?>
                            <span class="pill pill-info">Danh mục</span>
                        <?php elseif ((int)$v['voucher_type'] === 2): ?>
                            <span class="pill pill-warning">Sản phẩm</span>
                        <?php else: ?>
                            <span class="pill pill-success">Freeship</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ((int)$v['discount_type'] === 1): ?>
                            <strong><?= (int)$v['discount_value'] ?>%</strong>
                        <?php else: ?>
                            <strong><?= money($v['discount_value']) ?></strong>
                        <?php endif ?>
                    </td>
                    <td data-label="Đơn tối thiểu">
                        <?= (int)$v['min_spending'] > 0 ? money($v['min_spending']) : '<span class="muted">Không giới hạn</span>' ?>
                    </td>
                    <td data-label="Giới hạn">
                        <?= (int)$v['max_usage_per_user'] ?> lượt
                    </td>
                    <td data-label="Đã dùng">
                        <strong><?= (int)$v['total_usage'] ?></strong>
                    </td>
                    <td data-label="Thời hạn">
                        <small>
                            <?= date('d/m/Y', strtotime($v['start_date'])) ?> – <?= date('d/m/Y', strtotime($v['end_date'])) ?>
                        </small>
                        <?php if ($isExpired): ?>
                            <br><span class="pill pill-danger" style="font-size: 11px;">Hết hạn</span>
                        <?php elseif ($isPending): ?>
                            <br><span class="pill pill-warning" style="font-size: 11px;">Chưa tới hạn</span>
                        <?php endif ?>
                    </td>
                    <td data-label="Trạng thái">
                        <form action="<?= url('/admin/vouchers/' . (int) $v['id'] . '/toggle') ?>" method="post" style="display: inline;">
                            <?= csrf_field() ?>
                            <?php if ((int)$v['is_active']): ?>
                                <button type="submit" class="pill pill-success" style="cursor: pointer; border: none;" title="Bấm để tạm ngưng">Hoạt động</button>
                            <?php else: ?>
                                <button type="submit" class="pill pill-secondary" style="cursor: pointer; border: none;" title="Bấm để kích hoạt">Tạm khóa</button>
                            <?php endif ?>
                        </form>
                    </td>
                    <td class="actions">
                        <a class="btn btn-sm btn-ghost" href="<?= url('/admin/vouchers/' . (int) $v['id'] . '/edit') ?>">Sửa</a>
                        <form action="<?= url('/admin/vouchers/' . (int) $v['id']) ?>" method="post" style="display: inline;">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Bạn có chắc chắn muốn xóa voucher '<?= e($v['code']) ?>' không?">Xoá</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
