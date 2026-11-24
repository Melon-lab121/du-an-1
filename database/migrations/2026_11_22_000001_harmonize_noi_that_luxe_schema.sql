-- Dong bo hoa schema noi_that_luxe voi Base Shop
-- Khac phuc cac cot NOT NULL legacy, khoa ngoai va them UNIQUE constraint

ALTER TABLE users MODIFY COLUMN role_id INT(11) NULL DEFAULT 2;

ALTER TABLE orders MODIFY COLUMN user_id INT(11) NULL DEFAULT NULL;
ALTER TABLE orders MODIFY COLUMN date DATE NULL DEFAULT NULL;

ALTER TABLE product_options MODIFY COLUMN option_name VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE product_option_values MODIFY COLUMN option_value VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE product_variants MODIFY COLUMN product_status_id INT(11) NULL DEFAULT NULL;
ALTER TABLE product_variants MODIFY COLUMN image VARCHAR(255) NULL DEFAULT NULL;
