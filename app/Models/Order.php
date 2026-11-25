<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Order extends Model
{
    public const STATUSES = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'packing' => 'Đang đóng gói',
        'awaiting_pickup' => 'Chờ đơn vị vận chuyển lấy',
        'shipping' => 'Đang giao',
        'delivered' => 'Đã giao',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Chưa thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán lỗi',
        'refund_needed' => 'Cần hoàn tiền',
        'refunded' => 'Đã hoàn tiền',
    ];

    /**
     * Các trạng thái được phép chuyển tới từ mỗi trạng thái. completed/cancelled là trạng thái cuối.
     * Chỉ huỷ được ở 3 trạng thái đầu. Khi đơn đã sang đơn vị vận chuyển (DELIVERY_STAGES) thì chỉ huỷ được
     * sau khi lấy/giao hàng thất bại đủ MAX_DELIVERY_ATTEMPTS lần (xem nextStatuses).
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['packing', 'cancelled'],
        'packing' => ['awaiting_pickup', 'cancelled'],
        'awaiting_pickup' => ['shipping'],
        'shipping' => ['delivered'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    /** Trạng thái mà việc lấy hàng / giao hàng có thể thất bại (mỗi lần thất bại là 1 dòng lịch sử, trạng thái giữ nguyên). */
    public const DELIVERY_STAGES = ['awaiting_pickup', 'shipping'];

    public const MAX_DELIVERY_ATTEMPTS = 3;

    protected static string $table = 'orders';
    protected static array $fillable = [
        'code', 'user_id', 'customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'note',
        'subtotal', 'shipping_fee', 'discount', 'voucher_code', 'total', 'payment_method', 'payment_status', 'status', 'paid_at',
    ];

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        return self::findBy('code', $code);
    }

    /** @return array<int, array<string, mixed>> */
    public static function items(int $orderId): array
    {
        return self::db()->table('order_items')->where('order_id', $orderId)->orderBy('id')->get();
    }

    /** @return array<int, array<string, mixed>> */
    public static function payments(int $orderId): array
    {
        return self::db()->table('payments')->where('order_id', $orderId)->latest()->get();
    }

    /** @return array<int, array<string, mixed>> lịch sử đổi trạng thái, cũ nhất trước (kèm tên người đổi) */
    public static function statusHistory(int $orderId): array
    {
        return self::db()->select(
            'SELECT h.*, u.name AS changed_by_name
             FROM order_status_histories h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.order_id = ?
             ORDER BY h.id',
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

    /**
     * Số lần lấy/giao hàng thất bại đã ghi nhận khi đơn đang ở $status
     * (dòng lịch sử có from_status = to_status = $status). Trạng thái khác DELIVERY_STAGES luôn trả 0.
     */
    public static function failedAttempts(int $orderId, string $status): int
    {
        if (!in_array($status, self::DELIVERY_STAGES, true)) {
            return 0;
        }
        return (int) self::db()->scalar(
            'SELECT COUNT(*) FROM order_status_histories WHERE order_id = ? AND from_status = ? AND to_status = ?',
            [$orderId, $status, $status],
        );
    }

    /**
     * @param int $failedAttempts số lần thất bại đã ghi nhận ở trạng thái hiện tại (xem failedAttempts)
     * @return array<int, string> các trạng thái được phép chuyển tới từ $status
     */
    public static function nextStatuses(string $status, int $failedAttempts = 0): array
    {
        $next = self::TRANSITIONS[$status] ?? [];
        if (in_array($status, self::DELIVERY_STAGES, true) && $failedAttempts >= self::MAX_DELIVERY_ATTEMPTS) {
            $next[] = 'cancelled';
        }
        return $next;
    }

    public static function canTransition(string $from, string $to, int $failedAttempts = 0): bool
    {
        return in_array($to, self::nextStatuses($from, $failedAttempts), true);
    }
}
