<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\QueryBuilder;

final class Product extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAFT = 'draft';

    /** Hậu tố gắn vào slug/sku khi vào thùng rác để nhả giá trị UNIQUE. */
    private const TRASH_SLUG_SUFFIX = '--xoa-';
    private const TRASH_SKU_SUFFIX = '#';

    protected static string $table = 'products';
    protected static bool $softDeletes = true;
    protected static array $fillable = [
        'category_id', 'name', 'slug', 'sku', 'short_description', 'description',
        'price', 'sale_price', 'stock', 'image', 'status', 'is_featured', 'has_variants',
    ];

    /** Sản phẩm đang bán (dùng cho storefront). */
    public static function active(): QueryBuilder
    {
        return self::query()->where('status', self::STATUS_ACTIVE);
    }

    /** @return array<string, mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return self::active()->where('slug', $slug)->first();
    }

    /**
     * Bẫy UNIQUE của xoá mềm: dòng trong thùng rác vẫn giữ slug/sku nên chặn sản phẩm mới dùng lại.
     * Cách của Base: gắn hậu tố khi xoá, gỡ hậu tố khi khôi phục.
     */
    protected static function attributesOnTrash(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'slug' => $row['slug'] . self::TRASH_SLUG_SUFFIX . $id,
            'sku' => $row['sku'] === null ? null : $row['sku'] . self::TRASH_SKU_SUFFIX . $id,
        ];
    }

    protected static function attributesOnRestore(array $row): array
    {
        $id = (int) $row['id'];
        $slug = self::stripSuffix((string) $row['slug'], self::TRASH_SLUG_SUFFIX . $id);
        $sku = $row['sku'] === null ? null : self::stripSuffix((string) $row['sku'], self::TRASH_SKU_SUFFIX . $id);

        // Trong lúc nằm thùng rác, slug/sku cũ có thể đã bị sản phẩm khác dùng
        if (self::query()->where('slug', $slug)->exists()) {
            $slug = self::uniqueSlug($slug, $id);
        }
        if ($sku !== null && self::query()->where('sku', $sku)->exists()) {
            $sku = null; // admin đặt lại SKU sau khi khôi phục
        }
        return ['slug' => $slug, 'sku' => $sku];
    }

    /** Có nằm trong đơn hàng nào không => nếu có thì không cho xoá vĩnh viễn. */
    public static function hasOrders(int $id): bool
    {
        return self::db()->table('order_items')->where('product_id', $id)->exists();
    }

    private static function stripSuffix(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? substr($value, 0, -strlen($suffix)) : $value;
    }

    /** Sinh slug không trùng (kể cả với sản phẩm trong thùng rác). */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = str_slug($name) ?: 'san-pham';
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

    /**
     * Lọc + sắp xếp cho trang danh sách.
     *
     * @param array{q?: string, category_id?: int|null, sort?: string, min_price?: int|null, max_price?: int|null} $filters
     */
    public static function search(array $filters): QueryBuilder
    {
        $query = self::active();

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $query->whereRaw('name LIKE ? OR sku LIKE ?', [$like, $like]);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        $priceExpr = 'CASE WHEN sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price THEN sale_price ELSE price END';
        if (!empty($filters['min_price'])) {
            $query->whereRaw("$priceExpr >= ?", [(int) $filters['min_price']]);
        }
        if (!empty($filters['max_price'])) {
            $query->whereRaw("$priceExpr <= ?", [(int) $filters['max_price']]);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderByRaw("$priceExpr ASC"),
            'price_desc' => $query->orderByRaw("$priceExpr DESC"),
            'name' => $query->orderBy('name', 'ASC'),
            default => $query->orderBy('id', 'DESC'),
        };

        return $query;
    }
}
