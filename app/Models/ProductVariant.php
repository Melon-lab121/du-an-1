<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ProductVariant extends Model
{
    protected static string $table = 'product_variants';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'product_id',
        'option_key',
        'title',
        'sku',
        'price',
        'sale_price',
        'stock',
        'product_status_id',
        'image',
        'deleted_at',
    ];

    /**
     * Tạo khoá tổ hợp chuẩn hoá từ danh sách option_value_id.
     * Sắp xếp tăng dần để đảm bảo thứ tự luôn nhất quán giữa sync() và findByValues().
     *
     * @param int[] $valueIds
     */
    public static function keyOf(array $valueIds): string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $valueIds),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids, SORT_NUMERIC);

        return implode('-', $ids);
    }

    /**
     * Lấy danh sách biến thể đang hoạt động của một sản phẩm.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forProduct(int $productId): array
    {
        return static::db()->select(
            'SELECT id, product_id, option_key, title, sku, price, sale_price, stock, product_status_id, image, created_at, updated_at, deleted_at
             FROM product_variants
             WHERE product_id = ? AND deleted_at IS NULL
             ORDER BY id ASC',
            [$productId],
        );
    }

    /**
     * Xoá mềm biến thể theo id (trả về số dòng bị ảnh hưởng, đồng nhất với Model).
     */
    public static function deleteById(int $id): int
    {
        return parent::deleteById($id);
    }
}