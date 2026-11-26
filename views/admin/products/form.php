<?php
$isEdit = isset($product['id']);
$this->extend('layouts/admin', [
    'title' => $isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm',
    'heading' => $isEdit ? 'Sửa: ' . $product['name'] : 'Thêm sản phẩm',
]);
$v = static fn(string $key) => e(old($key, $product[$key] ?? ''));
$err = static fn(string $key): string => ($m = error($key)) ? '<small class="error">' . e($m) . '</small>' : '';
$hasVariants = (bool) ($product['has_variants'] ?? false);
$computed = $hasVariants ? 'readonly title="Tính tự động từ các biến thể"' : '';
$trashedVariants = $trashedVariants ?? [];
$currentImage = (string) ($product['image'] ?? '');
$defaultImageUrl = old('image_url', preg_match('#^https?://#i', $currentImage) ? $currentImage : '');
$productId = (int) ($product['id'] ?? 0);
?>
<form action="<?= url($isEdit ? '/admin/products/' . $productId : '/admin/products') ?>" method="post" enctype="multipart/form-data" class="cart-layout" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>

    <div class="card">
        <div class="form-grid">
            <label class="field field-full"><span>Tên sản phẩm *</span><input name="name" value="<?= $v('name') ?>" required><?= $err('name') ?></label>
            <label class="field"><span>Slug (để trống = tự sinh)</span><input name="slug" value="<?= $v('slug') ?>"><?= $err('slug') ?></label>
            <label class="field"><span>SKU</span><input name="sku" value="<?= $v('sku') ?>"><?= $err('sku') ?></label>
            <label class="field"><span>Giá gốc (VND) *</span><input type="number" min="0" step="1000" name="price" value="<?= $v('price') ?>" <?= $hasVariants ? $computed : 'required' ?>><?= $err('price') ?></label>
            <label class="field"><span>Giá khuyến mãi</span><input type="number" min="0" step="1000" name="sale_price" value="<?= $v('sale_price') ?>" <?= $computed ?>><?= $err('sale_price') ?></label>
            <?php if ($hasVariants): ?>
                <p class="field-full muted small">Sản phẩm có phân loại: giá hiển thị = giá thấp nhất, tồn kho = tổng tồn các biến thể (tự tính). Sửa ở bảng biến thể bên dưới.</p>
            <?php endif ?>
            <label class="field field-full"><span>Mô tả</span><textarea name="description" rows="8"><?= $v('description') ?></textarea></label>
        </div>
    </div>

    <aside class="stack">
        <div class="card stack">
            <label class="field"><span>Trạng thái</span>
                <select name="status">
                    <option value="active" <?= old('status', $product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Đang bán</option>
                    <option value="draft" <?= old('status', $product['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Nháp (ẩn)</option>
                </select>
            </label>
            <label class="field"><span>Danh mục</span>
                <select name="category_id">
                    <option value="">— Không —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (string) old('category_id', $product['category_id'] ?? '') === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach ?>
                </select><?= $err('category_id') ?>
            </label>
            <label class="field"><span>Tồn kho<?= $hasVariants ? ' (tổng các biến thể)' : ' *' ?></span><input type="number" min="0" name="stock" value="<?= $v('stock') ?>" <?= $computed ?>><?= $err('stock') ?></label>
            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= old('is_featured', $product['is_featured'] ?? 0) ? 'checked' : '' ?>> Sản phẩm nổi bật</label>
        </div>

        <div class="card stack">
            <span>Ảnh sản phẩm</span>
            <?php if (!empty($product['image'])): ?>
                <span class="preview"><?= $this->insert('partials/product-image', ['product' => $product]) ?></span>
            <?php endif ?>

            <label class="field">
                <span>Tải ảnh từ máy</span>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <?= $err('image') ?>
                <small class="muted">JPG/PNG/WEBP/GIF, tối đa 2MB.</small>
            </label>

            <label class="field">
                <span>Hoặc điền link ảnh (URL)</span>
                <input
                    type="url"
                    name="image_url"
                    id="product-image-url"
                    value="<?= e($defaultImageUrl) ?>"
                    placeholder="https://example.com/sofa.jpg">
                <?= $err('image_url') ?>
                <small class="muted">Hỗ trợ link ảnh ngoài (http/https). Nếu vừa tải file vừa nhập link, hệ thống sẽ ưu tiên file tải lên.</small>
            </label>
            <div id="product-url-preview" style="display:none;">
                <img id="product-url-preview-img" src="" alt="Xem trước ảnh URL" style="max-width:100%;max-height:140px;border-radius:6px;object-fit:cover;">
            </div>
        </div>

        <button class="btn btn-lg btn-block" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm sản phẩm' ?></button>
        <a class="center" href="<?= url('/admin/products') ?>">Huỷ</a>
    </aside>
</form>

<?php // HTML không cho lồng form: quản lý biến thể nằm tách biệt bên dưới 
?>
<div class="card stack" id="product-variants-manager" style="margin-top:24px">
    <div class="toolbar" style="justify-content:space-between;align-items:center;">
        <h3 style="margin:0;">Thêm và chỉnh sửa biến thể</h3>
        <?php if ($isEdit): ?>
            <a class="btn btn-sm btn-ghost" href="<?= url('/admin/products/' . $productId . '/variants/trash') ?>">
                Thùng rác biến thể<?= $trashedVariants !== [] ? ' (' . count($trashedVariants) . ')' : '' ?>
            </a>
        <?php endif ?>
    </div>

    <?php // Form thêm nhiều biến thể trực tiếp (hỗ trợ cả tạo mới sản phẩm và chỉnh sửa) 
    ?>
    <form action="<?= url($isEdit ? '/admin/products/' . $productId . '/variants/batch' : '/admin/products/variants/draft') ?>" method="post" enctype="multipart/form-data" class="stack" id="add-multiple-variants-form" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border, #e5e7eb);">
        <?= csrf_field() ?>
        <div class="toolbar" style="justify-content:space-between;align-items:center;">
            <div>
                <strong>Thêm nhiều biến thể nhanh</strong>
                <div class="muted small">Nhập nhiều thuộc tính trên 1 biến thể bằng dấu phẩy: <code>Màu: Xanh, Kích thước: 2m</code> (hoặc dùng <code>;</code>).</div>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" id="add-new-variant-row">+ Thêm dòng biến thể</button>
        </div>

        <div style="overflow-x:auto;">
            <table class="table" id="new-variants-table">
                <thead>
                    <tr>
                        <th>Tên biến thể *</th>
                        <th>SKU</th>
                        <th>Giá gốc</th>
                        <th>Giá KM</th>
                        <th>Tồn kho</th>
                        <th>Ảnh biến thể (File / URL)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="new-variants-tbody">
                    <tr class="new-variant-row">
                        <td data-label="Tên biến thể"><input type="text" name="new_variants[0][title]" placeholder="VD: Màu: Xám, Chiều dài: 2m" required></td>
                        <td data-label="SKU"><input type="text" name="new_variants[0][sku]" placeholder="VD: SP0029-XAM" maxlength="64"></td>
                        <td data-label="Giá gốc"><input type="number" min="0" step="1000" name="new_variants[0][price]" value="0" style="width:125px;"></td>
                        <td data-label="Giá KM"><input type="number" min="0" step="1000" name="new_variants[0][sale_price]" placeholder="Tuỳ chọn" style="width:125px;"></td>
                        <td data-label="Tồn kho"><input type="number" min="0" name="new_variants[0][stock]" value="10" style="width:95px;"></td>
                        <td data-label="Ảnh biến thể">
                            <div class="stack" style="gap:4px;min-width:190px;">
                                <input type="file" name="new_variants[0][image]" accept="image/jpeg,image/png,image/webp,image/gif">
                                <input type="url" name="new_variants[0][image_url]" placeholder="https://... (hoặc link ảnh)">
                            </div>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-ghost remove-variant-row" title="Xoá dòng">✕</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div><button class="btn" type="submit">Thêm các biến thể này</button></div>
    </form>

    <?php if ($isEdit && !empty($variants)): ?>
        <div class="toolbar" style="margin-top: 16px;padding-top:12px;border-top:1px solid var(--border, #e5e7eb);">
            <strong>Điền nhanh cho tất cả biến thể</strong>
            <div class="inline">
                <input type="number" id="bulk-price" min="0" step="1000" placeholder="Giá gốc..." style="width: 150px;">
                <input type="number" id="bulk-sale-price" min="0" step="1000" placeholder="Giá KM..." style="width: 150px;">
                <input type="number" id="bulk-stock" min="0" placeholder="Tồn kho..." style="width: 130px;">
                <button type="button" class="btn btn-sm btn-ghost" id="apply-bulk-values">Áp dụng</button>
            </div>
        </div>

        <form action="<?= url('/admin/products/' . $productId . '/variants') ?>" method="post" enctype="multipart/form-data" id="bulk-variants-form">
            <?= csrf_field() ?>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Biến thể</th>
                            <th>Ảnh hiện tại</th>
                            <th>Đổi ảnh (File / Link)</th>
                            <th>SKU</th>
                            <th>Giá gốc</th>
                            <th>Giá KM</th>
                            <th>Tồn kho</th>
                            <th>Thao tác nhanh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variants as $variant):
                            $vid = (int) $variant['id'];
                            $vImg = (string) ($variant['image'] ?? '');
                            $vImgUrlVal = old("variants.{$vid}.image_url", preg_match('#^https?://#i', $vImg) ? $vImg : '');
                        ?>
                            <tr class="js-variant-row" data-variant-id="<?= $vid ?>">
                                <td data-label="Biến thể">
                                    <input
                                        type="text"
                                        class="js-quick-title"
                                        value="<?= e((string) ($variant['title'] ?? '')) ?>"
                                        placeholder="Tên biến thể"
                                        style="min-width:130px;">
                                </td>
                                <td data-label="Ảnh hiện tại">
                                    <?php if ($vImg !== ''): ?>
                                        <img src="<?= e($vImg) ?>" alt="<?= e((string) $variant['title']) ?>" style="width:52px;height:52px;object-fit:cover;border-radius:6px;">
                                    <?php else: ?>
                                        <span class="muted small">—</span>
                                    <?php endif ?>
                                </td>
                                <td data-label="Đổi ảnh">
                                    <div class="stack" style="gap:4px;min-width:190px;">
                                        <input type="file" class="js-quick-file" name="variants[<?= $vid ?>][image]" accept="image/jpeg,image/png,image/webp,image/gif">
                                        <input type="url" class="js-quick-url" name="variants[<?= $vid ?>][image_url]" value="<?= e($vImgUrlVal) ?>" placeholder="https://... (hoặc link ảnh)">
                                    </div>
                                </td>
                                <td data-label="SKU"><input class="js-quick-sku" name="variants[<?= $vid ?>][sku]" value="<?= e(old("variants.{$vid}.sku", (string) ($variant['sku'] ?? ''))) ?>" maxlength="64" style="width:120px;"></td>
                                <td data-label="Giá gốc"><input type="number" class="js-v-price js-quick-price" min="0" step="1000" name="variants[<?= $vid ?>][price]" value="<?= e(old("variants.{$vid}.price", (string) (int) ($variant['price'] ?? 0))) ?>" required style="width:115px;"></td>
                                <td data-label="Giá KM"><input type="number" class="js-v-sale js-quick-sale" min="0" step="1000" name="variants[<?= $vid ?>][sale_price]" value="<?= e(old("variants.{$vid}.sale_price", $variant['sale_price'] !== null ? (string) (int) $variant['sale_price'] : '')) ?>" style="width:115px;"></td>
                                <td data-label="Tồn kho"><input type="number" class="js-v-stock js-quick-stock" min="0" name="variants[<?= $vid ?>][stock]" value="<?= e(old("variants.{$vid}.stock", (string) (int) ($variant['stock'] ?? 0))) ?>" required style="width:85px;"></td>
                                <td data-label="Thao tác nhanh" style="white-space:nowrap;">
                                    <div class="inline" style="gap:4px;flex-wrap:wrap;">
                                        <button
                                            type="button"
                                            class="btn btn-sm js-quick-save-variant"
                                            data-action="<?= url('/admin/products/' . $productId . '/variants/' . $vid . '/quick-update') ?>"
                                            title="Lưu riêng biến thể này">Lưu</button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-ghost js-variant-action"
                                            data-action="<?= url('/admin/products/' . $productId . '/variants/' . $vid . '/delete') ?>"
                                            data-confirm="Chuyển biến thể này vào thùng rác (xoá mềm)?"
                                            title="Xoá mềm">Xoá mềm</button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-ghost js-variant-action"
                                            data-action="<?= url('/admin/products/' . $productId . '/variants/' . $vid . '/force-delete') ?>"
                                            data-confirm="Xoá vĩnh viễn biến thể này khỏi database? Không thể khôi phục."
                                            style="color:#dc2626;"
                                            title="Xoá vĩnh viễn">Xoá hẳn</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?= $err('variants') ?>
            <div class="card-footer">
                <span class="muted small"><?= count($variants) ?> biến thể đang bán</span>
                <button class="btn" type="submit">Lưu tất cả biến thể, giá &amp; ảnh</button>
            </div>
        </form>
    <?php endif ?>

    <?php if ($isEdit && $trashedVariants !== []): ?>
        <details style="margin-top: 8px;" open>
            <summary class="muted small" style="cursor: pointer;">Biến thể đã xoá mềm / trong thùng rác (<?= count($trashedVariants) ?>)</summary>
            <div style="overflow-x:auto;">
                <table class="table" style="margin-top: 8px;">
                    <thead>
                        <tr>
                            <th>Biến thể</th>
                            <th>Ảnh</th>
                            <th>SKU</th>
                            <th>Giá gốc</th>
                            <th>Tồn kho</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashedVariants as $tv): ?>
                            <?php $tvId = (int) $tv['id']; ?>
                            <tr>
                                <td><?= e((string) $tv['title']) ?></td>
                                <td>
                                    <?php if (!empty($tv['image'])): ?>
                                        <img src="<?= e((string) $tv['image']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <span class="muted small">—</span>
                                    <?php endif ?>
                                </td>
                                <td><?= e((string) ($tv['sku'] ?? '—')) ?></td>
                                <td><?= money((int) ($tv['price'] ?? 0)) ?></td>
                                <td><?= (int) ($tv['stock'] ?? 0) ?></td>
                                <td style="white-space:nowrap;">
                                    <div class="inline" style="gap:4px;">
                                        <?php if ($isEdit && $trashedVariants !== []): ?>
                                            <div class="toolbar" style="margin-top: 8px;padding-top:12px;border-top:1px solid var(--border, #e5e7eb);justify-content:space-between;align-items:center;">
                                                <span class="muted small">Có <strong><?= count($trashedVariants) ?></strong> biến thể đã xoá mềm trong thùng rác.</span>
                                                <a class="btn btn-sm btn-ghost" href="<?= url('/admin/products/' . $productId . '/variants/trash') ?>">
                                                    Mở thùng rác biến thể →
                                                </a>
                                            </div>
                                        <?php endif ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endif ?>
</div>

<?php // Form ẩn dùng chung để gửi action từng biến thể (quick-update / soft-delete / restore / force-delete) không lồng form 
?>
<form id="single-variant-action-form" method="post" enctype="multipart/form-data" style="display:none;">
    <?= csrf_field() ?>
</form>

<?php $this->start('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Bulk fill for existing variants
        const bulkBtn = document.getElementById('apply-bulk-values');
        if (bulkBtn) {
            bulkBtn.addEventListener('click', function() {
                const p = document.getElementById('bulk-price')?.value;
                const s = document.getElementById('bulk-sale-price')?.value;
                const k = document.getElementById('bulk-stock')?.value;

                if (p !== '' && p !== undefined) {
                    document.querySelectorAll('.js-v-price').forEach((el) => {
                        el.value = p;
                    });
                }
                if (s !== '' && s !== undefined) {
                    document.querySelectorAll('.js-v-sale').forEach((el) => {
                        el.value = s;
                    });
                }
                if (k !== '' && k !== undefined) {
                    document.querySelectorAll('.js-v-stock').forEach((el) => {
                        el.value = k;
                    });
                }
            });
        }

        // 2. Add / remove multiple variant rows dynamically
        const addRowBtn = document.getElementById('add-new-variant-row');
        const tbody = document.getElementById('new-variants-tbody');
        let nextIdx = tbody ? tbody.querySelectorAll('tr').length : 1;

        if (addRowBtn && tbody) {
            addRowBtn.addEventListener('click', function() {
                const idx = nextIdx++;
                const tr = document.createElement('tr');
                tr.className = 'new-variant-row';
                tr.innerHTML = `
                <td data-label="Tên biến thể"><input type="text" name="new_variants[${idx}][title]" placeholder="VD: Trắng - 1m6" required></td>
                <td data-label="SKU"><input type="text" name="new_variants[${idx}][sku]" placeholder="VD: SP0029-TRG" maxlength="64"></td>
                <td data-label="Giá gốc"><input type="number" min="0" step="1000" name="new_variants[${idx}][price]" value="0" style="width:125px;"></td>
                <td data-label="Giá KM"><input type="number" min="0" step="1000" name="new_variants[${idx}][sale_price]" placeholder="Tuỳ chọn" style="width:125px;"></td>
                <td data-label="Tồn kho"><input type="number" min="0" name="new_variants[${idx}][stock]" value="10" style="width:95px;"></td>
                <td data-label="Ảnh biến thể">
                    <div class="stack" style="gap:4px;min-width:190px;">
                        <input type="file" name="new_variants[${idx}][image]" accept="image/jpeg,image/png,image/webp,image/gif">
                        <input type="url" name="new_variants[${idx}][image_url]" placeholder="https://... (hoặc link ảnh)">
                    </div>
                </td>
                <td><button type="button" class="btn btn-sm btn-ghost remove-variant-row" title="Xoá dòng">✕</button></td>
            `;
                tbody.appendChild(tr);
            });

            tbody.addEventListener('click', function(e) {
                const delBtn = e.target.closest('.remove-variant-row');
                if (!delBtn) return;
                const rows = tbody.querySelectorAll('tr');
                if (rows.length > 1) {
                    delBtn.closest('tr').remove();
                } else {
                    delBtn.closest('tr').querySelectorAll('input').forEach((inp) => {
                        if (inp.type === 'number') inp.value = inp.name.includes('[stock]') ? '10' : '0';
                        else if (inp.type !== 'file') inp.value = '';
                    });
                }
            });
        }

        // 3. Quick save single variant row (including optional file upload)
        const actionForm = document.getElementById('single-variant-action-form');
        if (actionForm) {
            document.querySelectorAll('.js-quick-save-variant').forEach((btn) => {
                btn.addEventListener('click', function() {
                    const actionUrl = btn.getAttribute('data-action');
                    const row = btn.closest('.js-variant-row');
                    if (!actionUrl || !row) return;

                    actionForm.action = actionUrl;
                    actionForm.querySelectorAll('.js-dynamic-field').forEach((el) => el.remove());

                    const fields = {
                        title: row.querySelector('.js-quick-title')?.value ?? '',
                        sku: row.querySelector('.js-quick-sku')?.value ?? '',
                        price: row.querySelector('.js-quick-price')?.value ?? '0',
                        sale_price: row.querySelector('.js-quick-sale')?.value ?? '',
                        stock: row.querySelector('.js-quick-stock')?.value ?? '0',
                        image_url: row.querySelector('.js-quick-url')?.value ?? '',
                    };

                    Object.entries(fields).forEach(([name, val]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = val;
                        input.className = 'js-dynamic-field';
                        actionForm.appendChild(input);
                    });

                    const fileInput = row.querySelector('.js-quick-file');
                    if (fileInput && fileInput.files && fileInput.files.length > 0) {
                        const clonedFile = fileInput.cloneNode(true);
                        clonedFile.name = 'image_file';
                        clonedFile.classList.add('js-dynamic-field');
                        const dt = new DataTransfer();
                        dt.items.add(fileInput.files[0]);
                        clonedFile.files = dt.files;
                        actionForm.appendChild(clonedFile);
                    }

                    actionForm.submit();
                });
            });

            // 4. Soft delete / Restore / Force delete variant actions
            document.querySelectorAll('.js-variant-action').forEach((btn) => {
                btn.addEventListener('click', function() {
                    const actionUrl = btn.getAttribute('data-action');
                    const confirmMsg = btn.getAttribute('data-confirm');
                    if (!actionUrl) return;
                    if (confirmMsg && !window.confirm(confirmMsg)) return;

                    actionForm.action = actionUrl;
                    actionForm.querySelectorAll('.js-dynamic-field').forEach((el) => el.remove());
                    actionForm.submit();
                });
            });
        }

        // 5. Preview external URL for main product image
        const urlInput = document.getElementById('product-image-url');
        const previewBox = document.getElementById('product-url-preview');
        const previewImg = document.getElementById('product-url-preview-img');

        function updateUrlPreview() {
            if (!urlInput || !previewBox || !previewImg) return;
            const val = urlInput.value.trim();
            if (/^https?:\/\/.+/i.test(val)) {
                previewImg.src = val;
                previewBox.style.display = 'block';
            } else {
                previewBox.style.display = 'none';
                previewImg.removeAttribute('src');
            }
        }

        if (urlInput) {
            urlInput.addEventListener('input', updateUrlPreview);
            updateUrlPreview();
        }
    });
</script>
<?php $this->stop() ?>