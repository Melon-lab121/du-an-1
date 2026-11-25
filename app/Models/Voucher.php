<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Voucher extends Model
{
    protected static string $table = 'vouchers';
    protected static array $fillable = [
        'code',
        'voucher_type',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_spending',
        'max_usage_per_user',
        'start_date',
        'end_date',
        'is_active',
    ];
    protected static bool $timestamps = false;

    /** @return array<string, mixed>|null */
    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        return self::query()->where('code', $code)->first();
    }

    /** @return int[] */
    public static function categoryIds(int $voucherId): array
    {
        $rows = self::db()->table('voucher_categories')
            ->where('voucher_id', $voucherId)
            ->get();
        return array_map('intval', array_column($rows, 'category_id'));
    }

    /** @return int[] */
    public static function productIds(int $voucherId): array
    {
        $rows = self::db()->table('voucher_products')
            ->where('voucher_id', $voucherId)
            ->get();
        return array_map('intval', array_column($rows, 'product_id'));
    }

    /** @param int[] $categoryIds */
    public static function syncCategories(int $voucherId, array $categoryIds): void
    {
        self::db()->delete('voucher_categories', 'voucher_id = ?', [$voucherId]);
        foreach (array_unique(array_filter($categoryIds)) as $catId) {
            self::db()->insert('voucher_categories', [
                'voucher_id' => $voucherId,
                'category_id' => (int) $catId,
            ]);
        }
    }

    /** @param int[] $productIds */
    public static function syncProducts(int $voucherId, array $productIds): void
    {
        self::db()->delete('voucher_products', 'voucher_id = ?', [$voucherId]);
        foreach (array_unique(array_filter($productIds)) as $prodId) {
            self::db()->insert('voucher_products', [
                'voucher_id' => $voucherId,
                'product_id' => (int) $prodId,
            ]);
        }
    }

    /**
     * Đếm số lần 1 khách hàng (đăng nhập hoặc vãng lai) đã sử dụng voucher này.
     * Không tính các đơn đã bị huỷ (cancelled).
     */
    public static function usageCountForCustomer(string $code, ?int $userId = null, ?string $email = null, ?string $phone = null): int
    {
        $db = self::db();
        $code = strtoupper(trim($code));

        if ($userId !== null && $userId > 0) {
            $count = (int) $db->scalar(
                "SELECT COUNT(*) FROM orders WHERE voucher_code = ? AND user_id = ? AND status <> 'cancelled'",
                [$code, $userId]
            );
            if ($count > 0) {
                return $count;
            }
        }

        $conditions = [];
        $params = [$code];
        if ($email !== null && trim($email) !== '') {
            $conditions[] = 'customer_email = ?';
            $params[] = trim($email);
        }
        if ($phone !== null && trim($phone) !== '') {
            $conditions[] = 'customer_phone = ?';
            $params[] = trim($phone);
        }

        if ($conditions === []) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM orders WHERE voucher_code = ? AND (" . implode(' OR ', $conditions) . ") AND status <> 'cancelled'";
        return (int) $db->scalar($sql, $params);
    }

    /** Tổng số lượt voucher đã được sử dụng thành công trên toàn hệ thống */
    public static function totalUsage(string $code): int
    {
        return (int) self::db()->scalar(
            "SELECT COUNT(*) FROM orders WHERE voucher_code = ? AND status <> 'cancelled'",
            [strtoupper(trim($code))]
        );
    }
}
