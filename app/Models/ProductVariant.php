<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Biến thể (1 tổ hợp giá trị thuộc tính = 1 SKU). Xoá mềm: biến thể bị bỏ khỏi tổ hợp vẫn còn trong DB
 * để đơn hàng cũ trỏ tới được, và tự khôi phục nếu admin thêm lại đúng tổ hợp đó.
 */
final class ProductVariant extends Model
{
    protected static string $table = 'product_variants';
    protected static bool $softDeletes = true;
    protected static array $fillable = ['product_id', 'option_key', 'title', 'sku', 'price', 'sale_price', 'stock'];

    /** @return array<int, array<string, mixed>> biến thể đang bán của 1 sản phẩm */
    public static function forProduct(int $productId): array
    {
        return self::query()->where('product_id', $productId)->orderBy('id')->get();
    }

    /** Khoá chuẩn của tổ hợp: id giá trị sắp xếp tăng dần, nối bằng "-". @param int[] $valueIds */
    public static function keyOf(array $valueIds): string
    {
        $ids = array_map('intval', $valueIds);
        sort($ids);
        return implode('-', $ids);
    }
}
