<?php
$this->extend('layouts/admin', ['title' => 'Quản lý bình luận', 'heading' => 'Quản lý bình luận & đánh giá']);
$comments = $comments ?? [];
$statusFilter = $statusFilter ?? '';
$q = $q ?? '';
?>

<div class="card stack">
    <div class="toolbar" style="justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div class="inline" style="gap:8px;flex-wrap:wrap;">
            <a class="btn btn-sm <?= $statusFilter === '' ? '' : 'btn-ghost' ?>" href="<?= url('/admin/comments' . ($q !== '' ? '?q=' . urlencode($q) : '')) ?>">Tất cả</a>
            <a class="btn btn-sm <?= $statusFilter === 'approved' ? '' : 'btn-ghost' ?>" href="<?= url('/admin/comments?status=approved' . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>">Đang hiển thị</a>
            <a class="btn btn-sm <?= $statusFilter === 'hidden' ? '' : 'btn-ghost' ?>" href="<?= url('/admin/comments?status=hidden' . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>">Đã ẩn</a>
        </div>

        <form action="<?= url('/admin/comments') ?>" method="get" class="inline" style="gap:8px;">
            <?php if ($statusFilter !== ''): ?>
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <?php endif ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tìm nội dung, sản phẩm, khách hàng...">
            <button type="submit" class="btn btn-sm">Tìm kiếm</button>
            <?php if ($q !== '' || $statusFilter !== ''): ?>
                <a class="btn btn-sm btn-ghost" href="<?= url('/admin/comments') ?>">Đặt lại</a>
            <?php endif ?>
        </form>
    </div>

    <?php if ($comments === []): ?>
        <p class="muted">Không có bình luận nào phù hợp.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sản phẩm</th>
                        <th>Người dùng</th>
                        <th>Đánh giá</th>
                        <th>Nội dung</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comments as $c): ?>
                    <?php
                    $cid = (int) $c['id'];
                    $isApproved = ($c['status'] ?? 'approved') === 'approved';
                    $stars = max(1, min(5, (int) ($c['rating'] ?? 5)));
                    ?>
                    <tr>
                        <td>#<?= $cid ?></td>
                        <td>
                            <?php if (!empty($c['product_slug'])): ?>
                                <a href="<?= url('/product/' . urlencode((string) $c['product_slug']) . '#product-comments') ?>" target="_blank" rel="noopener">
                                    <?= e((string) ($c['product_name'] ?? ('Sản phẩm #' . (int) $c['product_id']))) ?>
                                </a>
                            <?php else: ?>
                                <?= e((string) ($c['product_name'] ?? ('Sản phẩm #' . (int) $c['product_id']))) ?>
                            <?php endif ?>
                        </td>
                        <td>
                            <strong><?= e((string) ($c['user_name'] ?? ('User #' . (int) $c['user_id']))) ?></strong>
                            <?php if (!empty($c['user_email'])): ?>
                                <div class="muted small"><?= e((string) $c['user_email']) ?></div>
                            <?php endif ?>
                        </td>
                        <td style="white-space:nowrap;color:#d97706;">
                            <?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?>
                            <span class="muted small">(<?= $stars ?>/5)</span>
                        </td>
                        <td style="max-width:340px;white-space:pre-line;"><?= e((string) ($c['body'] ?? '')) ?></td>
                        <td>
                            <span class="tag"><?= $isApproved ? 'Hiển thị' : 'Đang ẩn' ?></span>
                        </td>
                        <td style="white-space:nowrap;"><?= e((string) ($c['created_at'] ?? '')) ?></td>
                        <td style="white-space:nowrap;">
                            <form action="<?= url('/admin/comments/' . $cid . '/toggle') ?>" method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-ghost">
                                    <?= $isApproved ? 'Ẩn' : 'Hiện' ?>
                                </button>
                            </form>
                            <form action="<?= url('/admin/comments/' . $cid . '/delete') ?>" method="post" style="display:inline;" onsubmit="return confirm('Xoá vĩnh viễn bình luận này?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-ghost" style="color:#dc2626;">Xoá</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>