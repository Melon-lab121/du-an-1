-- Bổ sung bảng/cột cho quy trình đơn hàng, trả hàng và khách vãng lai (an toàn khi chạy lặp).

-- 1. Các cột mới trên bảng orders
SET @dbname = DATABASE();
SET @tablename = "orders";

SET @col = "shipping_attempts";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN shipping_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER updated_at"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "shipped_at";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN shipped_at DATETIME NULL DEFAULT NULL AFTER shipping_attempts"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "delivered_at";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN delivered_at DATETIME NULL DEFAULT NULL AFTER shipped_at"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "completed_at";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN completed_at DATETIME NULL DEFAULT NULL AFTER delivered_at"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "cancelled_at";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN cancelled_at DATETIME NULL DEFAULT NULL AFTER completed_at"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "refund_status";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN refund_status VARCHAR(30) NOT NULL DEFAULT 'none' AFTER cancelled_at"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "refund_note";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN refund_note TEXT NULL DEFAULT NULL AFTER refund_status"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = "refunded_at";
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @col) > 0,
    "SELECT 1",
    "ALTER TABLE orders ADD COLUMN refunded_at DATETIME NULL DEFAULT NULL AFTER refund_note"
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Bảng yêu cầu trả lại hàng
CREATE TABLE IF NOT EXISTS order_returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NULL,
    reason VARCHAR(64) NOT NULL,
    description TEXT NOT NULL,
    proof_image VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY order_returns_order_index (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Bảng lưu thông tin đặt hàng của khách vãng lai
CREATE TABLE IF NOT EXISTS guest_inquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
