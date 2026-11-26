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
        'refunded' => 'Đã hoàn tiền',
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

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function paymentStatusLabel(string $status): string
    {
        return self::PAYMENT_STATUSES[$status] ?? $status;
    }
}
