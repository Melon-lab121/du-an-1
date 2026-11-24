-- Bài 4: xoá mềm (soft delete) + biến thể sản phẩm động.
-- Chạy: php cli.php migrate  (migration cũ không bao giờ sửa, luôn thêm file mới)

-- ---------- Xoá mềm sản phẩm ----------
-- deleted_at NULL = đang tồn tại; có giá trị = đã vào thùng rác (lúc nào)
ALTER TABLE products
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN has_variants TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured,
    ADD KEY products_deleted_index (deleted_at);

-- ---------- Biến thể động ----------
-- Tên thuộc tính do admin tự đặt cho từng sản phẩm: "Màu", "Size", "Dung lượng"...
-- Thuộc tính và giá trị cũng XOÁ MỀM: giữ nguyên id để thêm lại thì khớp đúng biến thể cũ.
CREATE TABLE IF NOT EXISTS product_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    UNIQUE KEY product_options_name_unique (product_id, name),
    CONSTRAINT product_options_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Giá trị của thuộc tính: "Đen", "Xám" / "M", "L", "XL"
CREATE TABLE IF NOT EXISTS product_option_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    option_id BIGINT UNSIGNED NOT NULL,
    value VARCHAR(60) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    UNIQUE KEY product_option_values_unique (option_id, value),
    CONSTRAINT product_option_values_option_fk FOREIGN KEY (option_id) REFERENCES product_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mỗi tổ hợp giá trị = 1 biến thể (1 SKU) có giá, tồn kho riêng.
-- option_key: id các giá trị đã sắp xếp, vd "3-7". UNIQUE gồm cả dòng đã xoá mềm =>
-- tạo lại đúng tổ hợp cũ thì KHÔI PHỤC dòng cũ chứ không tạo dòng mới.
CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    option_key VARCHAR(190) NOT NULL,
    title VARCHAR(190) NOT NULL,
    sku VARCHAR(64) NULL,
    price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sale_price BIGINT UNSIGNED NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY product_variants_key_unique (product_id, option_key),
    UNIQUE KEY product_variants_sku_unique (sku),
    KEY product_variants_product_index (product_id, deleted_at),
    CONSTRAINT product_variants_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng nối nhiều-nhiều: biến thể gồm những giá trị nào (mỗi thuộc tính đúng 1 giá trị)
CREATE TABLE IF NOT EXISTS product_variant_values (
    variant_id BIGINT UNSIGNED NOT NULL,
    option_value_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (variant_id, option_value_id),
    KEY product_variant_values_value_index (option_value_id),
    CONSTRAINT pvv_variant_fk FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    CONSTRAINT pvv_value_fk FOREIGN KEY (option_value_id) REFERENCES product_option_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đơn hàng lưu biến thể + nhãn chụp lại tại thời điểm mua (đổi/xoá biến thể không làm sai lịch sử)
ALTER TABLE order_items
    ADD COLUMN variant_id BIGINT UNSIGNED NULL AFTER product_id,
    ADD COLUMN variant_label VARCHAR(190) NULL AFTER product_name,
    ADD KEY order_items_variant_index (variant_id),
    ADD CONSTRAINT order_items_variant_fk FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL;
