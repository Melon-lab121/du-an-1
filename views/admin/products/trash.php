<?php $this->extend('layouts/admin', ['title' => 'Thùng rác sản phẩm', 'heading' => 'Thùng rác sản phẩm']) ?>

<div class="card">
    <div class="toolbar">
        <p class="muted">Sản phẩm đã xoá mềm: khách không thấy, không đặt được, nhưng đơn hàng cũ vẫn trỏ tới được.</p>
        <a class="btn btn-ghost" href="<?= url('/admin/products') ?>">← Danh sách sản phẩm</a>
    </div>

    <?php if (!$pager['data']): ?>
        <div class="empty">Thùng rác trống.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Tên</th><th>Slug / SKU lúc này</th><th>Xoá lúc</th><th>Đã bán?</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pager['data'] as $p): ?>
                <?php $sold = isset($withOrders[(int) $p['id']]) ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong></td>
                    <td data-label="Slug / SKU"><code><?= e($p['slug']) ?></code><br><small class="muted"><?= e($p['sku'] ?? '—') ?></small></td>
                    <td data-label="Xoá lúc"><?= e($p['deleted_at']) ?></td>
                    <td data-label="Đã bán?"><?= $sold ? '<span class="pill pill-info">Có trong đơn</span>' : '<span class="muted">Chưa</span>' ?></td>
                    <td class="actions">
                        <form action="<?= url('/admin/products/' . (int) $p['id'] . '/restore') ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit">Khôi phục</button>
                        </form>
                        <?php if (!$sold): ?>
                            <form action="<?= url('/admin/products/' . (int) $p['id'] . '/force') ?>" method="post">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Xoá VĨNH VIỄN, không khôi phục được. Tiếp tục?">Xoá vĩnh viễn</button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
    <?php endif ?>
</div>
