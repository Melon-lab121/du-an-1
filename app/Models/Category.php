<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Category extends Model
{
    protected static string $table = 'categories';
    protected static array $fillable = ['parent_id', 'name', 'slug', 'description', 'sort_order'];

    /** @return array<int, array<string, mixed>> */
    public static function ordered(): array
    {
        return self::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Danh mục kèm số sản phẩm đang bán.
     * SQL viết tay KHÔNG đi qua Product::query() nên phải tự lọc "p.deleted_at IS NULL".
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withProductCount(): array
    {
        return self::db()->select(
            'SELECT c.*, (SELECT COUNT(*) FROM products p
                          WHERE p.category_id = c.id AND p.status = ? AND p.deleted_at IS NULL) AS product_count
             FROM categories c 
             ORDER BY (product_count > 0) DESC, product_count DESC, c.sort_order ASC, c.name ASC',
            ['active'],
        );
    }
}
