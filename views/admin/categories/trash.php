<?php $this->extend('layouts/admin', ['title' => 'Thùng rác danh mục', 'heading' => 'Thùng rác danh mục']) ?>

<div class="card">
    <div class="toolbar">
        <p class="muted">Danh mục đã xoá: không hiện trên trang khách. Sản phẩm vẫn giữ danh mục cho đến khi xoá vĩnh viễn.</p>
        <a class="btn btn-ghost" href="<?= url('/admin/categories') ?>">← Danh sách danh mục</a>
    </div>

    <?php if (!$pager['data']): ?>
        <div class="empty">Thùng rác trống.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Tên</th><th>Slug lúc này</th><th>Xoá lúc</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pager['data'] as $category): ?>
                <tr>
                    <td><strong><?= e($category['name']) ?></strong></td>
                    <td data-label="Slug"><code><?= e($category['slug']) ?></code></td>
                    <td data-label="Xoá lúc"><?= e($category['deleted_at']) ?></td>
                    <td class="actions">
                        <form action="<?= url('/admin/categories/' . (int) $category['id'] . '/restore') ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit">Khôi phục</button>
                        </form>
                        <form action="<?= url('/admin/categories/' . (int) $category['id'] . '/force') ?>" method="post">
                            <?= csrf_field() ?><?= method_field('DELETE') ?>
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Xoá VĨNH VIỄN. Sản phẩm trong danh mục sẽ thành không danh mục. Tiếp tục?">Xoá vĩnh viễn</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
    <?php endif ?>
</div>
