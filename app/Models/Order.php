<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Order extends Model
{
    public const STATUSES = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao',
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

    /** Các trạng thái được phép chuyển tới từ mỗi trạng thái. completed/cancelled là trạng thái cuối. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

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

    /** @return array<int, string> các trạng thái được phép chuyển tới từ $status */
    public static function nextStatuses(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::nextStatuses($from), true);
    }
}
