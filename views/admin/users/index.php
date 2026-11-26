<?php
/** @var array<int, array<string, mixed>> $users */
/** @var string $q */
/** @var string $role */
/** @var list<string> $allowedRoles */
/** @var int $currentAdminId */
/** @var array<string, mixed> $pager */

$this->extend('layouts/admin', ['title' => 'Người dùng', 'heading' => 'Quản lý người dùng']);

$roleLabels = [
    'customer' => 'Khách hàng',
    'staff' => 'Nhân viên',
    'admin' => 'Quản trị',
];
?>
<div class="card">
    <form class="toolbar" method="get" action="<?= url('/admin/users') ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tìm tên, email, SĐT...">
        <select name="role" aria-label="Lọc theo vai trò">
            <option value="">Tất cả vai trò</option>
            <?php foreach ($allowedRoles as $r): ?>
                <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e($roleLabels[$r] ?? $r) ?></option>
            <?php endforeach ?>
        </select>
        <button class="btn btn-sm" type="submit">Lọc</button>
    </form>

    <?php if ($users === []): ?>
        <div class="empty">Không có người dùng nào.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người dùng</th>
                    <th>SĐT / Địa chỉ</th>
                    <th>Số đơn</th>
                    <th>Đã chi (đã TT)</th>
                    <th>Vai trò</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php $uid = (int) $u['id']; ?>
                <tr>
                    <td>#<?= $uid ?></td>
                    <td>
                        <strong><?= e((string) $u['name']) ?></strong><br>
                        <small class="muted"><?= e((string) $u['email']) ?></small>
                    </td>
                    <td>
                        <?= e((string) ($u['phone'] ?? '—')) ?><br>
                        <small class="muted"><?= e((string) ($u['address'] ?? '—')) ?></small>
                    </td>
                    <td data-label="Số đơn"><?= (int) ($u['order_count'] ?? 0) ?></td>
                    <td data-label="Đã chi"><?= money((int) ($u['total_spent'] ?? 0)) ?></td>
                    <td data-label="Vai trò">
                        <form action="<?= url('/admin/users/' . $uid . '/role') ?>" method="post" style="display:flex;gap:6px;align-items:center;">
                            <?= csrf_field() ?>
                            <select name="role" aria-label="Vai trò của <?= e((string) $u['name']) ?>">
                                <?php foreach ($allowedRoles as $r): ?>
                                    <option value="<?= e($r) ?>" <?= ($u['role'] ?? 'customer') === $r ? 'selected' : '' ?>>
                                        <?= e($roleLabels[$r] ?? $r) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                            <button class="btn btn-sm btn-ghost" type="submit">Lưu</button>
                        </form>
                    </td>
                    <td data-label="Thao tác">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn btn-sm" href="<?= url('/admin/users/' . $uid . '/orders') ?>">Lịch sử mua</a>
                            <?php if ($uid !== $currentAdminId): ?>
                                <form action="<?= url('/admin/users/' . $uid . '/delete') ?>" method="post" onsubmit="return confirm('Xóa người dùng này?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-ghost" type="submit" style="color:#dc2626;">Xóa</button>
                                </form>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
</div>