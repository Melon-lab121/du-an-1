<?php $this->extend('layouts/admin', ['title' => 'Sản phẩm', 'heading' => 'Sản phẩm']) ?>

<div class="card">
    <div class="toolbar">
        <form method="get" class="inline">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tên hoặc SKU...">
            <button class="btn btn-sm" type="submit">Tìm</button>
        </form>
        <div class="inline">
            <a class="btn btn-ghost" href="<?= url('/admin/products/trash') ?>">🗑 Thùng rác (<?= (int) $trashCount ?>)</a>
            <a class="btn" href="<?= url('/admin/products/create') ?>">+ Thêm sản phẩm</a>
        </div>
    </div>

    <?php if (!$pager['data']): ?>
        <div class="empty">Chưa có sản phẩm.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th></th><th>Tên</th><th>Danh mục</th><th>Giá</th><th>Tồn kho</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pager['data'] as $p): ?>
                <tr>
                    <td><span class="cart-thumb"><?= $this->insert('partials/product-image', ['product' => $p]) ?></span></td>
                    <td><strong><?= e($p['name']) ?></strong><br><small class="muted"><?= e($p['sku'] ?? '') ?></small><?= $p['is_featured'] ? ' <span class="pill pill-info">Nổi bật</span>' : '' ?><?= $p['has_variants'] ? ' <span class="pill pill-warning">Có phân loại</span>' : '' ?></td>
                    <td data-label="Danh mục"><?= e($categories[$p['category_id']] ?? '—') ?></td>
                    <td data-label="Giá">
                        <?= $p['has_variants'] ? 'Từ ' : '' ?><?= money(App\Services\PriceCalculator::unitPrice($p)) ?>
                        <?php if ($p['sale_price']): ?><br><del class="muted"><?= money($p['price']) ?></del><?php endif ?>
                    </td>
                    <td data-label="Tồn kho" class="<?= (int) $p['stock'] <= 5 ? 'text-danger' : '' ?>"><?= (int) $p['stock'] ?></td>
                    <td data-label="Trạng thái"><span class="pill pill-<?= $p['status'] === 'active' ? 'success' : 'warning' ?>"><?= $p['status'] === 'active' ? 'Đang bán' : 'Nháp' ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-ghost" href="<?= url('/admin/products/' . (int) $p['id'] . '/edit') ?>">Sửa</a>
                        <form action="<?= url('/admin/products/' . (int) $p['id']) ?>" method="post">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Chuyển sản phẩm này vào thùng rác?">Xoá</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
    <?php endif ?>
</div>
