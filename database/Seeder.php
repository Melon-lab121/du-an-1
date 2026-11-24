<?php

declare(strict_types=1);

namespace Database;

use App\Core\Database;
use App\Models\ProductVariant;
use App\Services\VariantService;

/**
 * Dữ liệu mẫu: 1 admin, 1 khách hàng, danh mục và sản phẩm demo.
 * Viết bằng API Database chung nên chạy được cả MySQL lẫn SQLite.
 */
final class Seeder
{
    public function __construct(private Database $db)
    {
    }

    public function run(?callable $output = null): void
    {
        $now = date('Y-m-d H:i:s');

        $users = [
            ['Quản trị viên', 'admin@example.com', 'admin123', 'admin'],
            ['Khách hàng Demo', 'customer@example.com', 'customer123', 'customer'],
        ];
        foreach ($users as [$name, $email, $password, $role]) {
            if (!$this->db->table('users')->where('email', $email)->exists()) {
                $this->db->insert('users', [
                    'name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        $output && $output('Seeded: users (admin@example.com / admin123, customer@example.com / customer123)');

        $categories = [
            'dien-thoai' => ['Điện thoại', 1],
            'laptop' => ['Laptop', 2],
            'phu-kien' => ['Phụ kiện', 3],
            'thoi-trang' => ['Thời trang', 4],
        ];
        $catIds = [];
        foreach ($categories as $slug => [$name, $order]) {
            $id = $this->db->table('categories')->where('slug', $slug)->value('id');
            $catIds[$slug] = $id !== null ? (int) $id : $this->db->insert('categories', [
                'name' => $name, 'slug' => $slug, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $output && $output('Seeded: categories');

        // [danh mục, tên, giá, giá KM, tồn kho, nổi bật, mô tả ngắn]
        $products = [
            ['dien-thoai', 'Điện thoại Nova X 128GB', 7990000, 7490000, 25, 1, 'Màn hình AMOLED 6.5", pin 5000mAh, sạc nhanh 33W.'],
            ['dien-thoai', 'Điện thoại Nova Lite 64GB', 3290000, null, 40, 0, 'Nhỏ gọn, pin bền, phù hợp học sinh – sinh viên.'],
            ['dien-thoai', 'Điện thoại Zen Pro 256GB', 15990000, 14490000, 10, 1, 'Camera 108MP, chip hiệu năng cao, màn 120Hz.'],
            ['laptop', 'Laptop AirBook 14 i5', 16490000, 15290000, 12, 1, 'Mỏng nhẹ 1.2kg, RAM 16GB, SSD 512GB.'],
            ['laptop', 'Laptop WorkStation 15 i7', 28990000, null, 5, 0, 'Card đồ hoạ rời, màn 2K, chuyên đồ hoạ – lập trình.'],
            ['laptop', 'Laptop StudyMate 13', 9990000, 9490000, 18, 0, 'Giá tốt cho học tập, pin 10 giờ.'],
            ['phu-kien', 'Tai nghe Bluetooth ANC', 1290000, 990000, 60, 1, 'Chống ồn chủ động, pin 30 giờ.'],
            ['phu-kien', 'Sạc nhanh GaN 65W', 590000, null, 100, 0, '3 cổng USB-C/USB-A, sạc được laptop.'],
            ['phu-kien', 'Chuột không dây Silent', 290000, 249000, 80, 0, 'Click êm, kết nối 2.4GHz + Bluetooth.'],
            ['phu-kien', 'Bàn phím cơ 87 phím', 1190000, null, 30, 1, 'Switch hot-swap, LED RGB.'],
            ['thoi-trang', 'Áo thun Cotton Basic', 199000, 159000, 150, 0, '100% cotton, form regular, nhiều màu.'],
            ['thoi-trang', 'Balo Laptop Chống Nước', 690000, 590000, 45, 1, 'Ngăn laptop 15.6", vải chống nước.'],
        ];

        foreach ($products as $i => [$cat, $name, $price, $sale, $stock, $featured, $short]) {
            $slug = str_slug($name);
            if ($this->db->table('products')->where('slug', $slug)->exists()) {
                continue;
            }
            $this->db->insert('products', [
                'category_id' => $catIds[$cat],
                'name' => $name,
                'slug' => $slug,
                'sku' => sprintf('SP%04d', $i + 1),
                'short_description' => $short,
                'description' => $short . "\n\nĐây là sản phẩm mẫu được tạo bởi seeder. Bạn có thể sửa/xoá trong trang quản trị.",
                'price' => $price,
                'sale_price' => $sale,
                'stock' => $stock,
                'status' => 'active',
                'is_featured' => $featured,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $output && $output('Seeded: products');

        $this->seedVariantProduct($catIds['thoi-trang'], $now);
        $output && $output('Seeded: product variants (Áo hoodie: Màu × Size)');
    }

    /** Sản phẩm mẫu có biến thể động: 2 màu × 3 size = 6 SKU, size XL đắt hơn, vài tổ hợp hết hàng. */
    private function seedVariantProduct(int $categoryId, string $now): void
    {
        $slug = 'ao-hoodie-ni-basic';
        if ($this->db->table('products')->where('slug', $slug)->exists()) {
            return;
        }
        // Transaction: lỗi giữa chừng thì không để lại sản phẩm "nửa vời" (có dòng nhưng chưa có biến thể)
        $this->db->transaction(fn () => $this->createVariantProduct($slug, $categoryId, $now));
    }

    private function createVariantProduct(string $slug, int $categoryId, string $now): void
    {
        $productId = $this->db->insert('products', [
            'category_id' => $categoryId,
            'name' => 'Áo hoodie nỉ Basic',
            'slug' => $slug,
            'sku' => 'SP0013',
            'short_description' => 'Nỉ bông dày, mũ 2 lớp. Chọn màu và size.',
            'description' => "Sản phẩm mẫu có PHÂN LOẠI (biến thể động): Màu × Size.\n\nMỗi tổ hợp có giá và tồn kho riêng.",
            'price' => 350000,
            'stock' => 0,
            'status' => 'active',
            'is_featured' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $variants = new VariantService($this->db);
        $variants->sync($productId, [
            ['name' => 'Màu', 'values' => ['Đen', 'Xám']],
            ['name' => 'Size', 'values' => ['M', 'L', 'XL']],
        ]);

        // [tồn kho] theo thứ tự tổ hợp; size XL đắt hơn 30.000đ, Xám giảm giá
        $stock = ['Đen / M' => 10, 'Đen / L' => 8, 'Đen / XL' => 0, 'Xám / M' => 5, 'Xám / L' => 0, 'Xám / XL' => 3];
        $rows = [];
        foreach (ProductVariant::forProduct($productId) as $v) {
            $isXl = str_ends_with((string) $v['title'], 'XL');
            $price = $isXl ? 380000 : 350000;
            $rows[(int) $v['id']] = [
                'sku' => $v['sku'],
                'price' => $price,
                'sale_price' => str_starts_with((string) $v['title'], 'Xám') ? $price - 50000 : null,
                'stock' => $stock[(string) $v['title']] ?? 0,
            ];
        }
        $variants->updateVariants($productId, $rows);
    }
}
