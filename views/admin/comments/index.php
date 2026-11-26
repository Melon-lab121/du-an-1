<div class="admin-page-header">
    <h1>Quản lý Comment & Review</h1>
</div>

<form method="GET" action="/admin/comments" class="filter-bar" style="margin-bottom: 16px; display: flex; gap: 8px;">
    <input type="text" name="q" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Tìm theo nội dung, khách hàng, sản phẩm...">
    <select name="status">
        <option value="">-- Tất cả trạng thái --</option>
        <option value="approved" <?= ($status ?? '') === 'approved' ? 'selected' : '' ?>>Hiển thị</option>
        <option value="hidden" <?= ($status ?? '') === 'hidden' ? 'selected' : '' ?>>Đã ẩn</option>
        <option value="pending" <?= ($status ?? '') === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
    </select>
    <button type="submit" class="btn btn-primary">Lọc</button>
</form>

<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Sản phẩm</th>
            <th>Người dùng</th>
            <th>Đánh giá</th>
            <th>Nội dung</th>
            <th>Ngày tạo</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($comments)): ?>
            <tr>
                <td colspan="8" style="text-align: center;">Chưa có bình luận hoặc đánh giá nào.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($comments as $item): ?>
                <tr>
                    <td>#<?= (int) $item['id'] ?></td>
                    <td><?= htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?= htmlspecialchars($item['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?><br>
                        <small><?= htmlspecialchars($item['user_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    </td>
                    <td><?= (int) ($item['rating'] ?? 5) ?> / 5 ★</td>
                    <td><?= nl2br(htmlspecialchars($item['content'] ?? '', ENT_QUOTES, 'UTF-8')) ?></td>
                    <td><?= htmlspecialchars($item['created_at'] ?? $item['date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php
                        $commentStatus = (string) ($item['status'] ?? 'approved');
                        echo htmlspecialchars($commentStatus, ENT_QUOTES, 'UTF-8');
                        ?>
                    </td>
                    <td style="display: flex; gap: 6px;">
                        <form method="POST" action="/admin/comments/status">
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <input type="hidden" name="status" value="<?= $commentStatus === 'hidden' ? 'approved' : 'hidden' ?>">
                            <button type="submit" class="btn btn-sm">
                                <?= $commentStatus === 'hidden' ? 'Hiện' : 'Ẩn' ?>
                            </button>
                        </form>
                        <form method="POST" action="/admin/comments/delete" onsubmit="return confirm('Bạn chắc chắn muốn xóa đánh giá này?');">
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        <?php endif ?>
    </tbody>
</table>
