<?php
declare(strict_types=1);

/**
 * Import danh mục + sản phẩm từ DB noi_that_luxe cũ sang Base Shop.
 * Chạy sau khi import noi_that_luxe.sql vào database riêng tên noi_that_luxe.
 * Không ghi đè sản phẩm hiện có. Giá nguồn là USD, mặc định quy đổi 1 USD = 25.000 VND.
 */
$base = dirname(__DIR__);
require $base . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\Database;

$config = app(Config::class);
$target = app(Database::class)->pdo();
$host = getenv('LEGACY_DB_HOST') ?: '127.0.0.1';
$port = getenv('LEGACY_DB_PORT') ?: '3306';
$name = getenv('LEGACY_DB_DATABASE') ?: 'noi_that_luxe';
$user = getenv('LEGACY_DB_USERNAME') ?: 'root';
$pass = getenv('LEGACY_DB_PASSWORD') ?: '';
$rate = (float) (getenv('USD_TO_VND') ?: '25000');

try {
    $source = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $target->beginTransaction();

    $categoryMap = [];
    foreach ($source->query('SELECT id, name FROM categories ORDER BY id') as $row) {
        $slug = slugify((string)$row['name']);
        $slugBase = $slug ?: 'danh-muc-' . (int)$row['id'];
        $slug = $slugBase;
        $suffix = 2;
        while (true) {
            $check = $target->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
            $check->execute([$slug]);
            $existing = $check->fetchColumn();
            if (!$existing) break;
            $same = $target->prepare('SELECT id FROM categories WHERE slug = ? AND name = ? LIMIT 1');
            $same->execute([$slug, $row['name']]);
            if ($same->fetchColumn()) break;
            $slug = $slugBase . '-' . $suffix++;
        }
        $same = $target->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
        $same->execute([$row['name']]);
        $id = $same->fetchColumn();
        if (!$id) {
            $insert = $target->prepare('INSERT INTO categories (name, slug, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
            $insert->execute([$row['name'], $slug]);
            $id = (int)$target->lastInsertId();
        }
        $categoryMap[(int)$row['id']] = (int)$id;
    }

    $categoryLinks = [];
    foreach ($source->query('SELECT product_id, category_id FROM product_categories ORDER BY category_id') as $link) {
        $categoryLinks[(int)$link['product_id']][] = (int)$link['category_id'];
    }
    $variantQuery = $source->query('SELECT product_id, price, stock, image FROM product_variants ORDER BY id');
    $variants = [];
    foreach ($variantQuery as $v) $variants[(int)$v['product_id']][] = $v;

    $count = 0;
    $products = $source->query('SELECT id, name, description FROM products ORDER BY id');
    foreach ($products as $p) {
        $exists = $target->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
        $exists->execute([$p['name']]);
        if ($exists->fetchColumn()) continue;
        $items = $variants[(int)$p['id']] ?? [];
        $prices = array_map(static fn($v) => (int) round((float)$v['price'] * $rate), $items);
        $stocks = array_map(static fn($v) => max(0, (int)$v['stock']), $items);
        $price = $prices ? min($prices) : 0;
        $stock = $stocks ? array_sum($stocks) : 0;
        $image = null;
        foreach ($items as $v) {
            if (!empty($v['image'])) { $image = trim((string)$v['image']); break; }
        }
        $categoryId = null;
        foreach ($categoryLinks[(int)$p['id']] ?? [] as $legacyCategoryId) {
            if (isset($categoryMap[$legacyCategoryId])) { $categoryId = $categoryMap[$legacyCategoryId]; break; }
        }
        $slugBase = slugify((string)$p['name']) ?: 'san-pham-' . (int)$p['id'];
        $slug = $slugBase;
        $n = 2;
        do {
            $check = $target->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
            $check->execute([$slug]);
            $taken = (bool)$check->fetchColumn();
            if ($taken) $slug = $slugBase . '-' . $n++;
        } while ($taken);

        $insert = $target->prepare('INSERT INTO products (category_id, name, slug, short_description, description, price, stock, image, status, is_featured, has_variants, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW(), NOW())');
        $description = (string)$p['description'];
        $insert->execute([$categoryId, $p['name'], $slug, mb_substr_safe($description, 0, 490), $description, $price, $stock, $image, 'active']);
        $count++;
    }
    $target->commit();
    echo "Import thành công: {$count} sản phẩm mới; " . count($categoryMap) . " danh mục đã đối chiếu.\n";
    echo "Tỷ giá quy đổi đang dùng: 1 USD = " . number_format($rate, 0, ',', '.') . " VND. Có thể đổi bằng biến môi trường USD_TO_VND.\n";
} catch (Throwable $e) {
    if ($target->inTransaction()) $target->rollBack();
    fwrite(STDERR, "Import thất bại: " . $e->getMessage() . "\n");
    exit(1);
}

function slugify(string $value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}
function mb_substr_safe(string $value, int $start, int $length): string {
    if (function_exists('mb_substr')) return mb_substr($value, $start, $length, 'UTF-8');
    $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    return $chars === false ? substr($value, $start, $length) : implode('', array_slice($chars, $start, $length));
}
