<?php
$isEdit = isset($product['id']);
$this->extend('layouts/admin', ['title' => $isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm', 'heading' => $isEdit ? 'Sửa: ' . $product['name'] : 'Thêm sản phẩm']);
$v = static fn (string $key) => e(old($key, $product[$key] ?? ''));
$err = static fn (string $key): string => ($m = error($key)) ? '<small class="error">' . e($m) . '</small>' : '';
$hasVariants = (bool) ($product['has_variants'] ?? false);
$computed = $hasVariants ? 'readonly title="Tính tự động từ các biến thể"' : '';
?>
<form action="<?= url($isEdit ? '/admin/products/' . (int) $product['id'] : '/admin/products') ?>" method="post" enctype="multipart/form-data" class="cart-layout" novalidate>
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
            <label class="field field-full"><span>Mô tả ngắn</span><textarea name="short_description" rows="2"><?= $v('short_description') ?></textarea><?= $err('short_description') ?></label>
            <label class="field field-full"><span>Mô tả chi tiết</span><textarea name="description" rows="8"><?= $v('description') ?></textarea></label>
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
            <?php if (!empty($product['image'])): ?><span class="preview"><?= $this->insert('partials/product-image', ['product' => $product]) ?></span><?php endif ?>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
            <?= $err('image') ?>
            <small class="muted">JPG/PNG/WEBP/GIF, tối đa 2MB.</small>
        </div>
        <button class="btn btn-lg btn-block" type="submit"><?= $isEdit ? 'Lưu thay đổi' : 'Thêm sản phẩm' ?></button>
        <a class="center" href="<?= url('/admin/products') ?>">Huỷ</a>
    </aside>
</form>

<?php if ($isEdit): ?>
    <?php // Form riêng (HTML không cho lồng form). Mỗi form một việc: thuộc tính / giá-tồn từng biến thể ?>
    <div class="card stack" style="margin-top:24px">
        <h3>Phân loại hàng (biến thể)</h3>
        <form action="<?= url('/admin/products/' . (int) $product['id'] . '/options') ?>" method="post" class="stack">
            <?= csrf_field() ?>
            <label class="field"><span>Mỗi dòng một thuộc tính, dạng <code>Tên: giá trị 1, giá trị 2</code>. Để trống = sản phẩm không phân loại.</span>
                <textarea name="options_text" rows="3" placeholder="Màu: Đen, Xám&#10;Size: M, L, XL"><?= e(old('options_text', $optionText ?? '')) ?></textarea>
            </label>
            <?= $err('options_text') ?>
            <small class="muted">Lưu sẽ sinh mọi tổ hợp. Tổ hợp bị bỏ được chuyển vào thùng rác (đơn cũ vẫn giữ), thêm lại đúng tổ hợp đó sẽ khôi phục cả giá và tồn kho cũ.</small>
            <div><button class="btn" type="submit">Lưu thuộc tính &amp; sinh biến thể</button></div>
        </form>

        <?php if (!empty($variants)): ?>
            <form action="<?= url('/admin/products/' . (int) $product['id'] . '/variants') ?>" method="post">
                <?= csrf_field() ?>
                <table class="table">
                    <thead><tr><th>Biến thể</th><th>SKU</th><th>Giá gốc</th><th>Giá KM</th><th>Tồn kho</th></tr></thead>
                    <tbody>
                    <?php foreach ($variants as $variant): $vid = (int) $variant['id']; ?>
                        <tr>
                            <td><strong><?= e($variant['title']) ?></strong></td>
                            <td data-label="SKU"><input name="variants[<?= $vid ?>][sku]" value="<?= e(old("variants.{$vid}.sku", $variant['sku'] ?? '')) ?>" maxlength="64"></td>
                            <td data-label="Giá gốc"><input type="number" min="0" step="1000" name="variants[<?= $vid ?>][price]" value="<?= (int) $variant['price'] ?>" required></td>
                            <td data-label="Giá KM"><input type="number" min="0" step="1000" name="variants[<?= $vid ?>][sale_price]" value="<?= e($variant['sale_price'] ?? '') ?>"></td>
                            <td data-label="Tồn kho"><input type="number" min="0" name="variants[<?= $vid ?>][stock]" value="<?= (int) $variant['stock'] ?>" required></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <?= $err('variants') ?>
                <div class="card-footer"><span class="muted small"><?= count($variants) ?> biến thể đang bán</span><button class="btn" type="submit">Lưu giá &amp; tồn kho</button></div>
            </form>
        <?php endif ?>
    </div>
<?php endif ?>
