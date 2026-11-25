-- Lịch sử đổi trạng thái đơn hàng: ai đổi, từ đâu sang đâu, lúc nào.
-- changed_by NULL = hệ thống tự đổi (IPN SePay, đối soát...).
-- Cố ý KHÔNG khai báo FOREIGN KEY: id của orders/users đang khác kiểu giữa các môi trường
-- (migration lõi dùng BIGINT UNSIGNED, file noi_that_luxe_1.sql dùng INT(11)) nên FK sẽ lỗi ở một trong hai.
CREATE TABLE IF NOT EXISTS order_status_histories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by BIGINT UNSIGNED NULL,
    created_at DATETIME NULL,
    KEY order_status_histories_order_index (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;