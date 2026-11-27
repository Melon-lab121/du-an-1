<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DateTimeImmutable;

final class Order extends Model
{
    public const MAX_SHIPPING_ATTEMPTS = 3;

    public const STATUSES = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận đơn hàng',
        'packing' => 'Đang đóng gói',
        'shipping' => 'Đang giao hàng',
        'delivered' => 'Giao hàng thành công',
        'delivery_failed' => 'Giao hàng thất bại',
        'completed' => 'Hoàn thành',
        'returned' => 'Trả lại hàng',
        'cancelled' => 'Đã huỷ',
    ];

    /** Thứ tự tiến độ chuẩn (không được quay lại trạng thái trước) */
    public const LINEAR_ORDER = [
        'pending' => 1,
        'confirmed' => 2,
        'packing' => 3,
        'shipping' => 4,
        'delivered' => 5,
        'completed' => 6,
    ];

    public const RETURN_REASONS = [
        'damaged' => 'Hàng hư hỏng / bể vỡ / móp méo',
        'wrong_item' => 'Giao sai sản phẩm / sai phân loại',
        'not_as_described' => 'Sản phẩm không đúng mô tả',
        'other' => 'Lý do khác',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Chưa thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán lỗi',
        'refunded' => 'Đã hoàn tiền',
    ];

    protected static string $table = 'orders';
    protected static array $fillable = [
        'code', 'user_id', 'customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'note',
        'subtotal', 'shipping_fee', 'discount', 'voucher_code', 'total', 'payment_method', 'payment_status',
        'status', 'paid_at', 'shipping_attempts', 'shipped_at', 'delivered_at', 'completed_at',
        'cancelled_at', 'refund_status', 'refund_note', 'refunded_at',
    ];

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        return self::findBy('code', $code);
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $orderId): array
    {
        return self::db()->select(
            'SELECT oi.*, p.slug AS product_slug, p.image AS product_image
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC',
            [$orderId],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function payments(int $orderId): array
    {
        return self::db()->table('payments')->where('order_id', $orderId)->latest()->get();
    }

    /** @return array<int, array<string, mixed>> */
    public static function returns(int $orderId): array
    {
        return self::db()->select(
            'SELECT * FROM order_returns WHERE order_id = ? ORDER BY id DESC',
            [$orderId],
        );
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function paymentStatusLabel(string $status): string
    {
        return self::PAYMENT_STATUSES[$status] ?? $status;
    }

    public static function isOnlineMethod(array $order): bool
    {
        return strtolower((string) ($order['payment_method'] ?? 'cod')) !== 'cod';
    }

    /** Khách chỉ được hủy trước khi đưa hàng tới đơn vị vận chuyển (pending / confirmed / packing) */
    public static function canCustomerCancel(array $order): bool
    {
        $status = (string) ($order['status'] ?? '');
        if (in_array($status, ['cancelled', 'completed', 'delivered', 'delivery_failed', 'returned'], true)) {
            return false;
        }
        $rank = self::LINEAR_ORDER[$status] ?? 0;
        return $rank > 0 && $rank < self::LINEAR_ORDER['shipping'];
    }

    /** Khách xác nhận đã nhận hàng khi shipper đã giao thành công (delivered) */
    public static function canConfirmReceived(array $order): bool
    {
        return (string) ($order['status'] ?? '') === 'delivered';
    }

/** Chỉ đơn hàng ở trạng thái Hoàn thành ('completed') mới mở nút Đánh giá sản phẩm. */
    public static function canReviewItems(array $order): bool
    {
        return (string) ($order['status'] ?? '') === 'completed';
    }

/** Mở nút Trả lại hàng trong vòng 1 tuần (7 ngày) kể từ khi giao thành công. */
    public static function canRequestReturn(array $order): bool
    {
        $status = (string) ($order['status'] ?? '');
        if (!in_array($status, ['delivered', 'completed'], true)) {
            return false;
        }
        $deliveredAt = (string) ($order['delivered_at'] ?? ($order['updated_at'] ?? ''));
        if ($deliveredAt === '') {
            return false;
        }
        $deadline = (new DateTimeImmutable($deliveredAt))->modify('+7 days');
        return (new DateTimeImmutable('now')) <= $deadline;
    }

/**
     * Tự động chuyển sang 'completed' sau 2 tuần (14 ngày) kể từ lúc giao thành công ('delivered')
     * nếu khách không bấm xác nhận và không có yêu cầu trả hàng.
     */
    public static function autoCompleteExpired(): int
    {
        $cutoff = (new DateTimeImmutable('now'))->modify('-14 days')->format('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');

        $rows = self::db()->select(
            "SELECT o.id
             FROM orders o
             WHERE o.status = 'delivered'
               AND o.delivered_at IS NOT NULL
               AND o.delivered_at <= ?
               AND NOT EXISTS (
                   SELECT 1 FROM order_returns r WHERE r.order_id = o.id
               )",
            [$cutoff],
        );

        $affected = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            self::db()->update('orders', [
                'status' => 'completed',
                'completed_at' => $now,
                'updated_at' => $now,
            ], 'id = ? AND status = ?', [$id, 'delivered']);
            $affected++;
        }

        return $affected;
    }
}