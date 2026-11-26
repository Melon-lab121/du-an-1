<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use DateTimeImmutable;

/**
 * Số liệu trang tổng quan admin: thống kê, biểu đồ 7 ngày, lượt trả hàng, gợi ý gom đơn và bộ lọc.
 */
final class DashboardStats
{
    public const PRODUCT_SORTS = [
        'sold_desc' => 'Lượt mua nhiều nhất',
        'sold_asc' => 'Lượt mua ít nhất',
        'stock_desc' => 'Tồn kho nhiều nhất',
        'stock_asc' => 'Tồn kho ít nhất',
    ];

    public const STOCK_FILTERS = [
        '' => 'Mọi tồn kho',
        'low' => 'Sắp hết (≤ 5)',
        'in' => 'Còn hàng',
        'out' => 'Hết hàng',
    ];

    public function __construct(private Database $db)
    {
    }

    /**
     * Điền đủ N ngày liên tiếp (kể cả hôm nay), ngày thiếu = 0.
     *
     * @param array<string, int|float> $revenueByDate key Y-m-d
     * @param array<string, int> $ordersByDate key Y-m-d
     * @return array{labels: list<string>, revenue: list<int>, orders: list<int>}
     */
    public static function fillDailyBuckets(
        int $days,
        array $revenueByDate,
        array $ordersByDate,
        ?DateTimeImmutable $today = null,
    ): array {
        $days = max(1, $days);
        $today ??= new DateTimeImmutable('today');
        $labels = [];
        $revenue = [];
        $orders = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify("-{$i} days");
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
            $revenue[] = (int) ($revenueByDate[$key] ?? 0);
            $orders[] = (int) ($ordersByDate[$key] ?? 0);
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'orders' => $orders];
    }

    /** @return array{revenue: int|float, orders: int, pending: int, products: int, customers: int, low_stock: int} */
    public function summary(): array
    {
        return [
            'revenue' => Order::query()->where('payment_status', 'paid')->sum('total'),
            'orders' => Order::query()->count(),
            'pending' => Order::query()->where('status', 'pending')->count(),
            'products' => Product::query()->count(),
            'customers' => User::query()->where('role', 'customer')->count(),
            'low_stock' => Product::query()->where('stock', '<=', 5)->count(),
        ];
    }

    /**
     * Doanh thu (paid) và số đơn theo ngày tạo trong N ngày gần nhất.
     *
     * @return array{labels: list<string>, revenue: list<int>, orders: list<int>}
     */
    public function lastDaysSeries(int $days = 7): array
    {
        $days = max(1, $days);
        $from = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
        $rows = Order::query()->where('created_at', '>=', $from)->get();

        $revenueByDate = [];
        $ordersByDate = [];
        foreach ($rows as $row) {
            $key = date('Y-m-d', strtotime((string) $row['created_at']));
            $ordersByDate[$key] = ($ordersByDate[$key] ?? 0) + 1;
            if (($row['payment_status'] ?? '') === 'paid') {
                $revenueByDate[$key] = ($revenueByDate[$key] ?? 0) + (int) $row['total'];
            }
        }

        return self::fillDailyBuckets($days, $revenueByDate, $ordersByDate);
    }

    /**
     * Số đơn theo từng trạng thái. Trạng thái chưa có đơn = 0.
     *
     * @return array<string, int>
     */
    public function orderStatusCounts(): array
    {
        $counts = [];
        foreach (array_keys(Order::STATUSES) as $status) {
            $counts[$status] = 0;
        }

        $rows = $this->db->select('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function orderCount(string $status): int
    {
        $query = Order::query();
        if (isset(Order::STATUSES[$status])) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    /** @return array<int, array<string, mixed>> */
    public function ordersByStatus(string $status, int $limit = 8): array
    {
        $query = Order::query()->latest();
        if (isset(Order::STATUSES[$status])) {
            $query->where('status', $status);
        }

        return $query->limit(max(1, $limit))->get();
    }

    /**
     * Lượt trả hàng gần đây để CSKH và quản trị theo dõi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentReturns(int $limit = 10): array
    {
        return $this->db->select(
            "SELECT r.*, o.code AS order_code, o.customer_name, o.customer_phone
             FROM order_returns r
             INNER JOIN orders o ON o.id = r.order_id
             ORDER BY r.id DESC
             LIMIT ?",
            [max(1, $limit)],
        );
    }

    /**
     * Tìm các khách hàng mua cùng một sản phẩm (và biến thể nếu có) trên nhiều đơn chưa kết thúc
     * để CSKH liên hệ gom đơn.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mergeGroups(int $limit = 20): array
    {
        return $this->db->select(
            "SELECT o.customer_phone,
                    MAX(o.customer_name) AS customer_name,
                    oi.product_id,
                    oi.variant_id,
                    MAX(oi.product_name) AS product_name,
                    MAX(oi.variant_label) AS variant_label,
                    COUNT(DISTINCT o.id) AS order_count,
                    SUM(oi.quantity) AS total_qty,
                    GROUP_CONCAT(DISTINCT o.code ORDER BY o.id DESC SEPARATOR ', ') AS order_codes
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.status NOT IN ('cancelled', 'completed', 'returned', 'delivery_failed')
               AND TRIM(COALESCE(o.customer_phone, '')) <> ''
             GROUP BY o.customer_phone, oi.product_id, COALESCE(oi.variant_id, 0)
             HAVING COUNT(DISTINCT o.id) >= 2
             ORDER BY order_count DESC, MAX(o.id) DESC
             LIMIT ?",
            [max(1, $limit)],
        );
    }

    /**
     * Sản phẩm đang bán, lọc tồn kho và sắp theo lượt mua hoặc số lượng kho.
     *
     * @return array<int, array{id: int, name: string, stock: int, sold: int}>
     */
    public function productsFiltered(string $sort, string $stock, int $limit = 10): array
    {
        $order = match ($sort) {
            'sold_asc' => 'sold ASC, p.name ASC',
            'stock_desc' => 'p.stock DESC, p.name ASC',
            'stock_asc' => 'p.stock ASC, p.name ASC',
            default => 'sold DESC, p.name ASC',
        };
        $stockSql = match ($stock) {
            'low' => ' AND p.stock <= 5',
            'in' => ' AND p.stock > 0',
            'out' => ' AND p.stock = 0',
            default => '',
        };

        $rows = $this->db->select(
            "SELECT p.id, p.name, p.stock, COALESCE(SUM(oi.quantity), 0) AS sold
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
             WHERE p.deleted_at IS NULL{$stockSql}
             GROUP BY p.id, p.name, p.stock
             ORDER BY {$order}
             LIMIT ?",
            [max(1, $limit)],
        );

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'stock' => (int) $row['stock'],
                'sold' => (int) $row['sold'],
            ];
        }, $rows);
    }
}