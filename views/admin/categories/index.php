<?php
$this->extend('layouts/admin', ['title' => 'Danh mục', 'heading' => 'Danh mục']);
$isEdit = $editing !== null;
?>
<div class="cart-layout">
    <div class="card">
        <div class="toolbar">
            <span class="muted">Xoá danh mục sẽ chuyển vào thùng rác, không xoá hẳn.</span>
            <a class="btn btn-ghost" href="<?= url('/admin/categories/trash') ?>">🗑 Thùng rác (<?= (int) $trashCount ?>)</a>
        </div>
        <?php if (!$categories): ?>
            <div class="empty">Chưa có danh mục.</div>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Tên</th><th>Slug</th><th>Thứ tự</th><th>Sản phẩm</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td data-label="Slug"><code><?= e($c['slug']) ?></code></td>
                        <td data-label="Thứ tự"><?= (int) $c['sort_order'] ?></td>
                        <td data-label="Sản phẩm"><?= (int) $c['product_count'] ?></td>
                        <td class="actions">
                            <a class="btn btn-sm btn-ghost" href="<?= url('/admin/categories', ['edit' => (int) $c['id']]) ?>">Sửa</a>
                            <form action="<?= url('/admin/categories/' . (int) $c['id']) ?>" method="post">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Chuyển danh mục này vào thùng rác? Khách sẽ không thấy danh mục nữa.">Xoá</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>

    <aside class="card">
        <h3><?= $isEdit ? 'Sửa danh mục' : 'Thêm danh mục' ?></h3>
        <form action="<?= url($isEdit ? '/admin/categories/' . (int) $editing['id'] : '/admin/categories') ?>" method="post" class="stack" novalidate>
            <?= csrf_field() ?>
            <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>
            <label class="field"><span>Tên *</span><input name="name" value="<?= e(old('name', $editing['name'] ?? '')) ?>" required>
                <?php if ($m = error('name')): ?><small class="error"><?= e($m) ?></small><?php endif ?></label>
            <label class="field"><span>Slug</span><input name="slug" value="<?= e(old('slug', $editing['slug'] ?? '')) ?>">
                <?php if ($m = error('slug')): ?><small class="error"><?= e($m) ?></small><?php endif ?></label>
            <label class="field"><span>Thứ tự</span><input type="number" name="sort_order" value="<?= e(old('sort_order', $editing['sort_order'] ?? 0)) ?>"></label>
            <label class="field"><span>Mô tả</span><textarea name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea></label>
            <button class="btn btn-block" type="submit"><?= $isEdit ? 'Lưu' : 'Thêm' ?></button>
            <?php if ($isEdit): ?><a class="center" href="<?= url('/admin/categories') ?>">Huỷ sửa</a><?php endif ?>
        </form>
    </aside>
</div>
