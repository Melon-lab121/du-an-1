-- Migration: Thiet lap he thong Voucher va cac loai giam gia
-- Yeu cau boi Truong nhom: Task 4 Voucher

-- 1. Bo sung cot voucher_code vao bang orders (neu chua co)
SET @dbname = DATABASE();
SET @tablename = "orders";
SET @columnname = "voucher_code";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE orders ADD COLUMN voucher_code VARCHAR(100) NULL DEFAULT NULL AFTER discount"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 1.1 Bo sung cot max_discount_amount vao bang vouchers (neu chua co)
SET @tablename = "vouchers";
SET @columnname = "max_discount_amount";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE vouchers ADD COLUMN max_discount_amount INT(11) NULL DEFAULT NULL AFTER discount_value"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Khoi tao cac loai voucher trong bang voucher_types
INSERT INTO voucher_types (id, voucher_type) VALUES
  (1, 'Giảm theo danh mục'),
  (2, 'Giảm theo sản phẩm'),
  (3, 'Freeship')
ON DUPLICATE KEY UPDATE voucher_type = VALUES(voucher_type);

-- 3. Khoi tao cac kieu giam gia trong bang discount_types
INSERT INTO discount_types (id, discount_type) VALUES
  (1, 'Giảm theo phần trăm'),
  (2, 'Giảm số tiền cố định')
ON DUPLICATE KEY UPDATE discount_type = VALUES(discount_type);
