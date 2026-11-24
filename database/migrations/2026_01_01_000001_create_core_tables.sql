-- Bảng lõi của hệ thống TMĐT. Tiền tệ lưu dạng số nguyên VND (BIGINT).

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    address VARCHAR(255) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'customer',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY categories_slug_unique (slug),
    CONSTRAINT categories_parent_fk FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(210) NOT NULL,
    sku VARCHAR(64) NULL,
    short_description VARCHAR(500) NULL,
    description TEXT NULL,
    price BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sale_price BIGINT UNSIGNED NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY products_slug_unique (slug),
    UNIQUE KEY products_sku_unique (sku),
    KEY products_status_index (status, is_featured),
    KEY products_category_index (category_id),
    CONSTRAINT products_category_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(190) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    note VARCHAR(500) NULL,
    subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    shipping_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY orders_code_unique (code),
    KEY orders_user_index (user_id),
    KEY orders_status_index (status),
    CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(190) NOT NULL,
    sku VARCHAR(64) NULL,
    price BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    total BIGINT UNSIGNED NOT NULL,
    KEY order_items_order_index (order_id),
    CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT order_items_product_fk FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mỗi lần khách bấm thanh toán online = 1 dòng (txn_ref gửi sang cổng)
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(30) NOT NULL,
    txn_ref VARCHAR(64) NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    transaction_no VARCHAR(64) NULL,
    bank_code VARCHAR(30) NULL,
    response_code VARCHAR(10) NULL,
    gateway_create_date VARCHAR(14) NULL,
    raw_data TEXT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY payments_txn_ref_unique (gateway, txn_ref),
    KEY payments_order_index (order_id),
    CONSTRAINT payments_order_fk FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cấu hình động dạng key/value cho các tính năng sau này
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(120) NOT NULL PRIMARY KEY,
    `value` TEXT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
