<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Category extends Model
{
    /** Hậu tố gắn vào slug khi vào thùng rác để nhả giá trị UNIQUE. */
    private const TRASH_SLUG_SUFFIX = '--xoa-';

    protected static string $table = 'categories';
    protected static bool $softDeletes = true;
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
             WHERE c.deleted_at IS NULL
             ORDER BY (product_count > 0) DESC, product_count DESC, c.sort_order ASC, c.name ASC',
            ['active'],
        );
    }

    /**
     * Slug trong thùng rác vẫn chiếm UNIQUE, nên gắn hậu tố khi xoá và gỡ khi khôi phục.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function attributesOnTrash(array $row): array
    {
        $id = (int) $row['id'];
        return ['slug' => $row['slug'] . self::TRASH_SLUG_SUFFIX . $id];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function attributesOnRestore(array $row): array
    {
        $id = (int) $row['id'];
        $slug = self::stripSuffix((string) $row['slug'], self::TRASH_SLUG_SUFFIX . $id);
        if (self::withTrashed()->where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = self::uniqueSlug($slug, $id);
        }
        return ['slug' => $slug];
    }

    /** Sinh slug không trùng, kể cả với danh mục đang nằm trong thùng rác. */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = str_slug($name) ?: 'danh-muc';
        $slug = $base;
        $i = 2;
        while (true) {
            $query = self::withTrashed()->where('slug', $slug);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
            if (!$query->exists()) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    private static function stripSuffix(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? substr($value, 0, -strlen($suffix)) : $value;
    }

    /**
     * Xoá vĩnh viễn danh mục, gỡ liên kết trong product_categories và đưa sản phẩm / danh mục con về không danh mục.
     */
    public static function forceDeleteById(int $id): int
    {
        return (int) self::db()->transaction(static function ($db) use ($id): int {
            $db->delete('product_categories', 'category_id = ?', [$id]);
            $db->update('products', ['category_id' => null], 'category_id = ?', [$id]);
            $db->update('categories', ['parent_id' => null], 'parent_id = ?', [$id]);

            return parent::forceDeleteById($id);
        });
    }
}
