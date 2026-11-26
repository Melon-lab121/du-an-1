USE `noi_that_luxe_1`;

ALTER TABLE `comments`
  ADD COLUMN `rating` tinyint(1) UNSIGNED NOT NULL DEFAULT 5 AFTER `product_id`,
  ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'approved' AFTER `content`,
  ADD COLUMN `created_at` datetime DEFAULT CURRENT_TIMESTAMP AFTER `date`,
  ADD COLUMN `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
