<?php
$productId = (int) $product['id'];
$trashedVariants = $trashedVariants ?? [];
$this->extend('layouts/admin', [
    'title' => 'Thùng rác biến thể - ' . $product['name'],
    'heading' => 'Thùng rác biến thể: ' . $product['name'],
]);
?>

<div class="card stack">
    <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px;">
        <div>
            <strong>Biến thể đã xoá mềm (<?= count($trashedVariants) ?>)</strong>
            <div class="muted small">Khôi phục để đưa biến thể bán lại hoặc xoá vĩnh viễn khỏi cơ sở dữ liệu.</div>
        </div>
        <div class="inline" style="gap:8px;">
            <a class="btn btn-sm btn-ghost" href="<?= url('/admin/products/' . $productId . '/edit') ?>">← Quay lại sửa sản phẩm</a>
            <a class="btn btn-sm btn-ghost" href="<?= url('/admin/products') ?>">Danh sách sản phẩm</a>
        </div>
    </div>

    <?php if ($trashedVariants === []): ?>
        <p class="muted">Thùng rác biến thể hiện đang trống.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ảnh</th>
                        <th>Tên phân loại</th>
                        <th>SKU</th>
                        <th>Giá gốc</th>
                        <th>Tồn kho</th>
                        <th>Ngày xoá</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($trashedVariants as $tv): ?>
                    <?php
                    $tvId = (int) $tv['id'];
                    $imgRaw = trim((string) ($tv['image'] ?? ''));
                    $imgSrc = $imgRaw !== '' ? (preg_match('#^https?://#i', $imgRaw) === 1 ? $imgRaw : url($imgRaw)) : '';
                    ?>
                    <tr>
                        <td>#<?= $tvId ?></td>
                        <td>
                            <?php if ($imgSrc !== ''): ?>
                                <img src="<?= e($imgSrc) ?>" alt="<?= e((string) ($tv['title'] ?? '')) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;">
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif ?>
                        </td>
                        <td><strong><?= e((string) ($tv['title'] ?? '')) ?></strong></td>
                        <td><?= e((string) ($tv['sku'] ?? '—')) ?></td>
                        <td><?= money((int) ($tv['price'] ?? 0)) ?></td>
                        <td><?= (int) ($tv['stock'] ?? 0) ?></td>
                        <td><?= e((string) ($tv['deleted_at'] ?? '')) ?></td>
                        <td style="white-space:nowrap;">
                            <form action="<?= url('/admin/products/' . $productId . '/variants/' . $tvId . '/restore') ?>" method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm">Khôi phục</button>
                            </form>

                            <form action="<?= url('/admin/products/' . $productId . '/variants/' . $tvId . '/force-delete') ?>" method="post" style="display:inline;" onsubmit="return confirm('Xoá vĩnh viễn biến thể này khỏi database? Hành động này không thể hoàn tác.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-ghost" style="color:#dc2626;">Xoá vĩnh viễn</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>