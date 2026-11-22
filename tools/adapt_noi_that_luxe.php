<?php
declare(strict_types=1);

/**
 * Script đồng bộ và nâng cấp database `noi_that_luxe` trực tiếp:
 * - Bảo toàn 100% 22 bảng gốc của noi_that_luxe.sql.
 * - Bổ sung các cột cần thiết cho `categories`, `products`, `users`, `orders`.
 * - Quy đổi giá USD -> VND (1 USD = 25.000 VND).
 * - Nạp link ảnh IKEA từ bảng product_variants vào products.
 * - Set deleted_at = NULL, status = 'active' để hiển thị sản phẩm ngay.
 * - Tạo tài khoản Admin (admin@gmail.com / admin123) và Customer (customer@gmail.com / customer123).
 * - Tạo các bảng bổ trợ: payments, order_items, settings.
 */

$host = '127.0.0.1';
$port = 3306;
$dbName = 'noi_that_luxe';
$username = 'root';
$password = '';
$rate = 25000; // 1 USD = 25.000 VND

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "1. Đã kết nối thành công tới database `{$dbName}`.\n";

    // Helper kiểm tra cột tồn tại
    $hasColumn = function(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    };

    // Helper tạo slug tiếng Việt
    $slugify = function(string $str): string {
        $str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u', 'a', $str);
        $str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u', 'e', $str);
        $str = preg_replace('/(ì|í|ị|ỉ|ĩ)/u', 'i', $str);
        $str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u', 'o', $str);
        $str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u', 'u', $str);
        $str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/u', 'y', $str);
        $str = preg_replace('/(đ)/u', 'd', $str);
        $str = preg_replace('/[^a-zA-Z0-9\s-]/', '', $str);
        $str = preg_replace('/[\s-]+/', '-', trim($str));
        return strtolower($str);
    };

    // ==========================================
    // 2. NÂNG CẤP BẢNG CATEGORIES
    // ==========================================
    echo "2. Nâng cấp bảng `categories`...\n";
    if (!$hasColumn($pdo, 'categories', 'slug')) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `slug` VARCHAR(255) NULL AFTER `name`");
    }
    if (!$hasColumn($pdo, 'categories', 'parent_id')) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `parent_id` INT(11) NULL DEFAULT NULL AFTER `id`");
    }
    if (!$hasColumn($pdo, 'categories', 'description')) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `description` TEXT NULL AFTER `slug`");
    }
    if (!$hasColumn($pdo, 'categories', 'sort_order')) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `description`");
    }
    if (!$hasColumn($pdo, 'categories', 'created_at')) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `created_at` DATETIME NULL");
    }
    if (!$hasColumn($pdo, 'categories', 'updated_at')) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `updated_at` DATETIME NULL");
    }

    // Cập nhật slug cho categories
    $cats = $pdo->query("SELECT id, name FROM categories")->fetchAll();
    $usedSlugs = [];
    $updateCatStmt = $pdo->prepare("UPDATE categories SET slug = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
    foreach ($cats as $idx => $cat) {
        $baseSlug = $slugify($cat['name']) ?: 'danh-muc-' . $cat['id'];
        $slug = $baseSlug;
        $i = 2;
        while (isset($usedSlugs[$slug])) {
            $slug = $baseSlug . '-' . $i++;
        }
        $usedSlugs[$slug] = true;
        $updateCatStmt->execute([$slug, $idx + 1, $cat['id']]);
    }
    echo "   -> Đã tạo slug cho " . count($cats) . " danh mục.\n";

    // ==========================================
    // 3. NÂNG CẤP BẢNG PRODUCTS
    // ==========================================
    echo "3. Nâng cấp bảng `products`...\n";
    $productCols = [
        'category_id' => "INT(11) NULL AFTER `id`",
        'slug' => "VARCHAR(255) NULL AFTER `name`",
        'sku' => "VARCHAR(100) NULL AFTER `slug`",
        'short_description' => "TEXT NULL AFTER `sku`",
        'price' => "INT(11) NOT NULL DEFAULT 0 AFTER `description`",
        'sale_price' => "INT(11) NULL DEFAULT NULL AFTER `price`",
        'stock' => "INT(11) NOT NULL DEFAULT 0 AFTER `sale_price`",
        'image' => "VARCHAR(255) NULL DEFAULT NULL AFTER `stock`",
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'active' AFTER `image`",
        'is_featured' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`",
        'has_variants' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`",
        'created_at' => "DATETIME NULL",
        'updated_at' => "DATETIME NULL",
        'deleted_at' => "DATETIME NULL",
    ];

    foreach ($productCols as $col => $def) {
        if (!$hasColumn($pdo, 'products', $col)) {
            $pdo->exec("ALTER TABLE `products` ADD COLUMN `{$col}` {$def}");
        }
    }

    // Map category_id từ product_categories
    $prodCats = [];
    foreach ($pdo->query("SELECT product_id, category_id FROM product_categories ORDER BY category_id ASC") as $pc) {
        if (!isset($prodCats[$pc['product_id']])) {
            $prodCats[$pc['product_id']] = (int)$pc['category_id'];
        }
    }

    // Map giá, tồn kho, ảnh từ product_variants
    $prodVariants = [];
    foreach ($pdo->query("SELECT product_id, price, stock, image FROM product_variants ORDER BY id ASC") as $pv) {
        $pid = (int)$pv['product_id'];
        $prodVariants[$pid][] = $pv;
    }

    // Cập nhật từng sản phẩm
    $products = $pdo->query("SELECT id, name, description FROM products")->fetchAll();
    $updateProdStmt = $pdo->prepare("
        UPDATE products SET 
            category_id = ?,
            slug = ?,
            sku = ?,
            short_description = ?,
            price = ?,
            sale_price = ?,
            stock = ?,
            image = ?,
            status = 'active',
            is_featured = ?,
            has_variants = ?,
            created_at = NOW(),
            updated_at = NOW(),
            deleted_at = NULL
        WHERE id = ?
    ");

    $usedProdSlugs = [];
    $featuredIds = [29, 31, 34, 35, 41, 44, 49, 51, 52]; // Một số sản phẩm làm nổi bật
    foreach ($products as $p) {
        $pid = (int)$p['id'];
        $catId = $prodCats[$pid] ?? null;
        $variants = $prodVariants[$pid] ?? [];

        // Giá quy đổi VND (min price)
        $prices = array_map(fn($v) => (int)round((float)$v['price'] * $rate), $variants);
        $price = $prices ? min($prices) : 500000;

        // Tồn kho
        $stocks = array_map(fn($v) => max(0, (int)$v['stock']), $variants);
        $stock = $stocks ? array_sum($stocks) : 20;

        // Ảnh (lấy ảnh IKEA từ biến thể đầu tiên có ảnh)
        $image = null;
        foreach ($variants as $v) {
            if (!empty($v['image'])) {
                $rawImg = trim((string)$v['image']);
                if (str_starts_with($rawImg, 'images-us/')) {
                    $filename = basename($rawImg);
                    if ($pid === 73) {
                        $image = 'https://www.ikea.com/us/en/images/products/visslaan-drawer-organizers-set-of-3-gray__1499227_pe1006532_s5.jpg?f=xl';
                    } else {
                        $image = "https://www.ikea.com/us/en/images/products/{$filename}?f=xl";
                    }
                } else {
                    $image = $rawImg;
                }
                break;
            }
        }

        // Slug
        $baseSlug = $slugify($p['name']) ?: 'san-pham-' . $pid;
        $slug = $baseSlug;
        $n = 2;
        while (isset($usedProdSlugs[$slug])) {
            $slug = $baseSlug . '-' . $n++;
        }
        $usedProdSlugs[$slug] = true;

        $sku = sprintf('SP%04d', $pid);
        $shortDesc = mb_substr(strip_tags((string)$p['description']), 0, 250) . '...';
        $isFeatured = in_array($pid, $featuredIds, true) ? 1 : 0;
        $hasVariants = 0; // Chưa có thuộc tính trong product_options, để 0 để đồng bộ nút Thêm vào giỏ

        $updateProdStmt->execute([
            $catId,
            $slug,
            $sku,
            $shortDesc,
            $price,
            null, // sale_price
            $stock,
            $image,
            $isFeatured,
            $hasVariants,
            $pid,
        ]);
    }
    echo "   -> Đã đồng bộ " . count($products) . " sản phẩm (giá VND, ảnh IKEA, status=active, deleted_at=NULL).\n";

    // ==========================================
    // 4. NÂNG CẤP BẢNG PRODUCT_VARIANTS & OPTIONS
    // ==========================================
    echo "4. Nâng cấp bảng `product_variants` & options...\n";
    $variantCols = [
        'option_key' => "VARCHAR(255) NULL DEFAULT 'default' AFTER `product_id`",
        'title' => "VARCHAR(255) NULL DEFAULT 'Tiêu chuẩn' AFTER `option_key`",
        'sku' => "VARCHAR(100) NULL AFTER `title`",
        'sale_price' => "INT(11) NULL DEFAULT NULL AFTER `price`",
        'deleted_at' => "DATETIME NULL",
        'created_at' => "DATETIME NULL",
        'updated_at' => "DATETIME NULL",
    ];
    foreach ($variantCols as $col => $def) {
        if (!$hasColumn($pdo, 'product_variants', $col)) {
            $pdo->exec("ALTER TABLE `product_variants` ADD COLUMN `{$col}` {$def}");
        }
    }
    // Cập nhật giá biến thể sang VND nếu đang là giá USD (< 10000)
    $pdo->exec("UPDATE product_variants SET price = ROUND(price * {$rate}), option_key = COALESCE(option_key, 'default'), title = COALESCE(title, 'Tiêu chuẩn'), deleted_at = NULL WHERE price < 10000");

    // Nâng cấp product_options và product_option_values
    if (!$hasColumn($pdo, 'product_options', 'product_id')) {
        $pdo->exec("ALTER TABLE `product_options` ADD COLUMN `product_id` INT(11) NULL AFTER `id`");
    }
    if (!$hasColumn($pdo, 'product_options', 'name')) {
        $pdo->exec("ALTER TABLE `product_options` ADD COLUMN `name` VARCHAR(255) NULL AFTER `product_id`");
    }
    if (!$hasColumn($pdo, 'product_options', 'position')) {
        $pdo->exec("ALTER TABLE `product_options` ADD COLUMN `position` INT(11) NOT NULL DEFAULT 0 AFTER `name`");
    }
    if (!$hasColumn($pdo, 'product_options', 'deleted_at')) {
        $pdo->exec("ALTER TABLE `product_options` ADD COLUMN `deleted_at` DATETIME NULL AFTER `position`");
    }

    if (!$hasColumn($pdo, 'product_option_values', 'value')) {
        $pdo->exec("ALTER TABLE `product_option_values` ADD COLUMN `value` VARCHAR(255) NULL AFTER `option_id`");
    }
    if (!$hasColumn($pdo, 'product_option_values', 'position')) {
        $pdo->exec("ALTER TABLE `product_option_values` ADD COLUMN `position` INT(11) NOT NULL DEFAULT 0 AFTER `value`");
    }
    if (!$hasColumn($pdo, 'product_option_values', 'deleted_at')) {
        $pdo->exec("ALTER TABLE `product_option_values` ADD COLUMN `deleted_at` DATETIME NULL AFTER `position`");
    }
    echo "   -> Đã quy đổi giá biến thể và nâng cấp bảng options.\n";

    // ==========================================
    // 5. NÂNG CẤP ROLES & USERS
    // ==========================================
    echo "5. Nâng cấp bảng `roles` và `users`...\n";
    $pdo->exec("INSERT IGNORE INTO `roles` (`id`, `role`) VALUES (1, 'admin'), (2, 'customer')");

    $userCols = [
        'phone' => "VARCHAR(50) NULL AFTER `email`",
        'address' => "VARCHAR(255) NULL AFTER `phone`",
        'role' => "VARCHAR(50) NOT NULL DEFAULT 'customer' AFTER `address`",
        'created_at' => "DATETIME NULL",
        'updated_at' => "DATETIME NULL",
    ];
    foreach ($userCols as $col => $def) {
        if (!$hasColumn($pdo, 'users', $col)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$def}");
        }
    }

    // Thêm tài khoản admin và customer
    $pwAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $pwCust = password_hash('customer123', PASSWORD_DEFAULT);

    $userInsertStmt = $pdo->prepare("
        INSERT INTO `users` (`name`, `email`, `password`, `phone`, `address`, `role_id`, `role`, `created_at`, `updated_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role` = VALUES(`role`), `role_id` = VALUES(`role_id`)
    ");
    $userInsertStmt->execute(['Quản trị viên', 'admin@example.com', $pwAdmin, '0901234567', 'Hà Nội', 1, 'admin']);
    $userInsertStmt->execute(['Khách hàng Demo', 'customer@example.com', $pwCust, '0987654321', 'TP. Hồ Chí Minh', 2, 'customer']);
    echo "   -> Đã tạo tài khoản Admin (admin@example.com / admin123) và Customer (customer@example.com / customer123).\n";

    // ==========================================
    // 6. NÂNG CẤP ORDERS & TẠO ORDER_ITEMS, PAYMENTS, SETTINGS
    // ==========================================
    echo "6. Nâng cấp bảng `orders` và tạo bảng thanh toán...\n";
    $orderCols = [
        'code' => "VARCHAR(100) NULL AFTER `id`",
        'customer_name' => "VARCHAR(255) NULL AFTER `user_id`",
        'customer_email' => "VARCHAR(255) NULL AFTER `customer_name`",
        'customer_phone' => "VARCHAR(50) NULL AFTER `customer_email`",
        'shipping_address' => "VARCHAR(255) NULL AFTER `customer_phone`",
        'note' => "TEXT NULL AFTER `shipping_address`",
        'subtotal' => "INT(11) NOT NULL DEFAULT 0 AFTER `note`",
        'shipping_fee' => "INT(11) NOT NULL DEFAULT 0 AFTER `subtotal`",
        'discount' => "INT(11) NOT NULL DEFAULT 0 AFTER `shipping_fee`",
        'payment_status' => "VARCHAR(50) NOT NULL DEFAULT 'unpaid' AFTER `payment_method`",
        'status' => "VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER `payment_status`",
        'paid_at' => "DATETIME NULL",
        'created_at' => "DATETIME NULL",
        'updated_at' => "DATETIME NULL",
    ];
    foreach ($orderCols as $col => $def) {
        if (!$hasColumn($pdo, 'orders', $col)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `{$col}` {$def}");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT(11) NOT NULL,
            `product_id` INT(11) NULL,
            `variant_id` INT(11) NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `variant_label` VARCHAR(255) NULL,
            `sku` VARCHAR(100) NULL,
            `price` INT(11) NOT NULL,
            `quantity` INT(11) NOT NULL,
            `total` INT(11) NOT NULL,
            KEY `order_items_order_index` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `payments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT(11) NOT NULL,
            `gateway` VARCHAR(100) NOT NULL,
            `txn_ref` VARCHAR(191) NOT NULL,
            `amount` INT(11) NOT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
            `transaction_no` VARCHAR(255) NULL,
            `bank_code` VARCHAR(100) NULL,
            `response_code` VARCHAR(100) NULL,
            `gateway_create_date` VARCHAR(100) NULL,
            `raw_data` LONGTEXT NULL,
            `paid_at` DATETIME NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            UNIQUE KEY `payments_gateway_txn` (`gateway`, `txn_ref`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `key` VARCHAR(191) NOT NULL PRIMARY KEY,
            `value` LONGTEXT NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "   -> Hoàn tất nâng cấp orders, order_items, payments, settings.\n";

    echo "\n=== CHUYỂN ĐỔI THÀNH CÔNG 100%! ===\n";
    echo "Dự án hiện đã hoàn toàn sẵn sàng chạy trên database `noi_that_luxe`.\n";

} catch (Throwable $e) {
    echo "LỖI: " . $e->getMessage() . "\n";
    exit(1);
}
