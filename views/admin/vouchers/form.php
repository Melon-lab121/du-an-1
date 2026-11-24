<?php
$isEdit = $editing !== null;
$title = $isEdit ? 'Sửa Voucher ' . $editing['code'] : 'Thêm Voucher mới';
$this->extend('layouts/admin', ['title' => $title, 'heading' => $title]);

$field = static fn (string $name, mixed $fallback = ''): mixed => old($name, $editing[$name] ?? $fallback);

$vType = (int) $field('voucher_type', 1);
$dType = (int) $field('discount_type', 1);

$startDate = (string) $field('start_date', date('Y-m-d'));
$endDate = (string) $field('end_date', date('Y-m-d', strtotime('+30 days')));

// Chuẩn hóa định dạng date cho thẻ input
$startDateVal = substr($startDate, 0, 10);
$endDateVal = substr($endDate, 0, 10);

$checkedCats = old('category_ids', $selectedCategoryIds ?? []);
$checkedProds = old('product_ids', $selectedProductIds ?? []);
?>

<div class="card" style="max-width: 860px; margin: 0 auto;">
    <form action="<?= $isEdit ? url('/admin/vouchers/' . (int) $editing['id']) : url('/admin/vouchers') ?>" method="post">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>

        <div class="form-grid">
            <label class="field">
                <span>Mã Voucher *</span>
                <input type="text" name="code" value="<?= e($field('code')) ?>" required placeholder="VD: SALE10, FREESHIP" style="text-transform: uppercase; font-weight: bold;">
                <?php if ($err = error('code')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                <small class="muted">Mã khách hàng nhập vào khi thanh toán (không dấu, tự viết hoa).</small>
            </label>

            <label class="field">
                <span>Loại Voucher *</span>
                <select name="voucher_type" id="voucher_type" required onchange="onVoucherTypeChange()">
                    <option value="1" <?= $vType === 1 ? 'selected' : '' ?>>1. Giảm theo danh mục</option>
                    <option value="2" <?= $vType === 2 ? 'selected' : '' ?>>2. Giảm theo sản phẩm</option>
                    <option value="3" <?= $vType === 3 ? 'selected' : '' ?>>3. Freeship (Miễn phí vận chuyển)</option>
                </select>
                <?php if ($err = error('voucher_type')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
            </label>

            <!-- Bộ chọn danh mục áp dụng -->
            <div class="field field-full" id="box-categories" style="<?= $vType === 1 ? '' : 'display: none;' ?>">
                <span style="font-weight: 600; display: block; margin-bottom: 6px;">Chọn danh mục áp dụng</span>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; max-height: 180px; overflow-y: auto; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg); color: var(--text);">
                    <?php foreach ($categories as $cat): ?>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--text); padding: 4px; border-radius: 4px;">
                            <input type="checkbox" name="category_ids[]" value="<?= (int) $cat['id'] ?>" <?= in_array((int) $cat['id'], $checkedCats, true) ? 'checked' : '' ?>>
                            <span><?= e($cat['name']) ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
                <small class="muted">Nếu không chọn danh mục nào, voucher sẽ áp dụng cho tất cả sản phẩm.</small>
            </div>

            <!-- Bộ chọn sản phẩm áp dụng -->
            <div class="field field-full" id="box-products" style="<?= $vType === 2 ? '' : 'display: none;' ?>">
                <span style="font-weight: 600; display: block; margin-bottom: 6px;">Chọn sản phẩm áp dụng</span>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px; max-height: 220px; overflow-y: auto; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg); color: var(--text);">
                    <?php foreach ($products as $prod): ?>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: var(--text); padding: 4px; border-radius: 4px;">
                            <input type="checkbox" name="product_ids[]" value="<?= (int) $prod['id'] ?>" <?= in_array((int) $prod['id'], $checkedProds, true) ? 'checked' : '' ?>>
                            <span><?= e($prod['name']) ?> <small class="muted">(<?= money($prod['price']) ?>)</small></span>
                        </label>
                    <?php endforeach ?>
                </div>
                <small class="muted">Nếu không chọn sản phẩm nào, voucher sẽ áp dụng cho tất cả sản phẩm.</small>
            </div>

            <label class="field">
                <span>Kiểu giảm giá *</span>
                <select name="discount_type" id="discount_type" required onchange="onDiscountTypeChange()">
                    <option value="1" <?= $dType === 1 ? 'selected' : '' ?>>Giảm theo phần trăm (%)</option>
                    <option value="2" <?= $dType === 2 ? 'selected' : '' ?>>Giảm số tiền cố định (₫)</option>
                </select>
                <?php if ($err = error('discount_type')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
            </label>

            <label class="field">
                <span id="label-discount-value">Giá trị giảm *</span>
                <input type="number" name="discount_value" id="discount_value" value="<?= e($field('discount_value')) ?>" required min="1" placeholder="VD: 10 (cho 10%) hoặc 50000 (cho 50.000₫)">
                <?php if ($err = error('discount_value')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                <small class="muted" id="hint-discount-value"><?= $dType === 1 ? 'Nhập số % (từ 1 đến 100)' : 'Nhập số tiền VND cần giảm' ?></small>
            </label>

            <label class="field">
                <span>Giảm tối đa (₫)</span>
                <input type="number" name="max_discount_amount" value="<?= e($field('max_discount_amount', '')) ?>" min="0" step="1000" placeholder="VD: 500000 (để trống nếu không giới hạn)">
                <?php if ($err = error('max_discount_amount')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                <small class="muted">Số tiền giảm tối đa (đặc biệt hữu ích cho mã giảm theo %)</small>
            </label>

            <label class="field">
                <span>Chi tiêu tối thiểu (₫)</span>
                <input type="number" name="min_spending" value="<?= e($field('min_spending', 0)) ?>" min="0" step="1000" placeholder="0">
                <?php if ($err = error('min_spending')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                <small class="muted">Giá trị đơn hàng tối thiểu để áp dụng mã (0 = không giới hạn).</small>
            </label>

            <label class="field">
                <span>Lượt dùng tối đa mỗi khách *</span>
                <input type="number" name="max_usage_per_user" value="<?= e($field('max_usage_per_user', 1)) ?>" required min="1" placeholder="1">
                <?php if ($err = error('max_usage_per_user')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
                <small class="muted">Số lần tối đa một tài khoản / email / số điện thoại được dùng mã này.</small>
            </label>

            <label class="field">
                <span>Ngày bắt đầu *</span>
                <input type="date" name="start_date" value="<?= e($startDateVal) ?>" required>
                <?php if ($err = error('start_date')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
            </label>

            <label class="field">
                <span>Ngày kết thúc *</span>
                <input type="date" name="end_date" value="<?= e($endDateVal) ?>" required>
                <?php if ($err = error('end_date')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
            </label>

            <div class="field field-full">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= $field('is_active', 1) ? 'checked' : '' ?>>
                    <strong>Kích hoạt mã này ngay bây giờ</strong>
                </label>
            </div>
        </div>

        <div class="card-footer" style="margin-top: 24px;">
            <a href="<?= url('/admin/vouchers') ?>" class="btn btn-ghost">← Quay lại danh sách</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Cập nhật Voucher' : 'Tạo Voucher' ?></button>
        </div>
    </form>
</div>

<script>
function onVoucherTypeChange() {
    const val = document.getElementById('voucher_type').value;
    document.getElementById('box-categories').style.display = (val === '1') ? 'block' : 'none';
    document.getElementById('box-products').style.display = (val === '2') ? 'block' : 'none';
}

function onDiscountTypeChange() {
    const val = document.getElementById('discount_type').value;
    const label = document.getElementById('label-discount-value');
    const hint = document.getElementById('hint-discount-value');
    const input = document.getElementById('discount_value');

    if (val === '1') {
        label.innerText = 'Phần trăm giảm (%) *';
        hint.innerText = 'Nhập số % (từ 1 đến 100)';
        input.max = '100';
    } else {
        label.innerText = 'Số tiền giảm (₫) *';
        hint.innerText = 'Nhập số tiền VND cần giảm';
        input.removeAttribute('max');
    }
}
</script>
