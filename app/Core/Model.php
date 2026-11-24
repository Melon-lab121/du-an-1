<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Model cơ sở kiểu "table gateway": trả về mảng kết hợp, gọn và dễ debug.
 *
 *   class Product extends Model { protected static string $table = 'products'; ... }
 *   Product::find(1); Product::query()->where('status', 'active')->get();
 *
 * Xoá mềm (soft delete): đặt `protected static bool $softDeletes = true;` và bảng có cột deleted_at.
 *   query()/find()/all()  tự thêm "deleted_at IS NULL"  => bản ghi đã xoá biến mất khỏi mọi màn hình
 *   withTrashed()         lấy cả bản ghi đã xoá (trang thùng rác, báo cáo)
 *   onlyTrashed()         chỉ bản ghi đã xoá
 *   deleteById()          UPDATE deleted_at = NOW()  (không DELETE thật)
 *   restore()             đặt lại deleted_at = NULL
 *   forceDeleteById()     DELETE thật, không khôi phục được
 * LƯU Ý: SQL viết tay ($db->select('...'), $db->table('...')) KHÔNG đi qua Model nên phải tự lọc deleted_at.
 */
abstract class Model
{
    protected static string $table = '';
    /** @var string[] cột được phép ghi bằng create()/updateById() */
    protected static array $fillable = [];
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;

    public static function db(): Database
    {
        return App::instance()->get(Database::class);
    }

    public static function table(): string
    {
        return static::$table;
    }

    /** Truy vấn mặc định: với model xoá mềm, tự ẩn bản ghi đã xoá. */
    public static function query(): QueryBuilder
    {
        $query = static::db()->table(static::$table);
        return static::$softDeletes ? $query->whereNull(static::$table . '.deleted_at') : $query;
    }

    /** Gồm cả bản ghi đã xoá mềm. */
    public static function withTrashed(): QueryBuilder
    {
        return static::db()->table(static::$table);
    }

    /** Chỉ bản ghi đã xoá mềm (thùng rác). */
    public static function onlyTrashed(): QueryBuilder
    {
        static::assertSoftDeletes();
        return static::db()->table(static::$table)->whereNotNull(static::$table . '.deleted_at');
    }

    public static function usesSoftDeletes(): bool
    {
        return static::$softDeletes;
    }

    /** @param array<string, mixed> $row */
    public static function isTrashed(array $row): bool
    {
        return ($row['deleted_at'] ?? null) !== null;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return static::query()->find($id);
    }

    /** @return array<string, mixed> */
    public static function findOrFail(int $id): array
    {
        return static::find($id) ?? throw new HttpException(404);
    }

    /** @return array<string, mixed>|null */
    public static function findBy(string $column, mixed $value): ?array
    {
        return static::query()->where($column, $value)->first();
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $orderBy = 'id', string $direction = 'DESC'): array
    {
        return static::query()->orderBy($orderBy, $direction)->get();
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): int
    {
        $data = static::filterFillable($data);
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }
        return static::db()->insert(static::$table, $data);
    }

    /** @param array<string, mixed> $data */
    public static function updateById(int $id, array $data): int
    {
        $data = static::filterFillable($data);
        if (static::$timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($data === []) {
            return 0;
        }
        return static::db()->update(static::$table, $data, 'id = ?', [$id]);
    }

    /**
     * Xoá. Model xoá mềm => chỉ đánh dấu deleted_at (gọi lại lần 2 không ghi đè thời điểm xoá).
     * @return int số dòng bị ảnh hưởng
     */
    public static function deleteById(int $id): int
    {
        if (!static::$softDeletes) {
            return static::db()->delete(static::$table, 'id = ?', [$id]);
        }
        $row = static::query()->find($id);
        if ($row === null) {
            return 0;
        }
        $data = static::attributesOnTrash($row) + ['deleted_at' => date('Y-m-d H:i:s')];
        return static::db()->update(static::$table, $data, 'id = ? AND deleted_at IS NULL', [$id]);
    }

    /** Khôi phục bản ghi đã xoá mềm. @return int số dòng bị ảnh hưởng */
    public static function restore(int $id): int
    {
        static::assertSoftDeletes();
        $row = static::onlyTrashed()->find($id);
        if ($row === null) {
            return 0;
        }
        $data = static::attributesOnRestore($row) + ['deleted_at' => null];
        if (static::$timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return static::db()->update(static::$table, $data, 'id = ? AND deleted_at IS NOT NULL', [$id]);
    }

    /** Xoá thật khỏi database (kể cả bản ghi đang trong thùng rác). */
    public static function forceDeleteById(int $id): int
    {
        return static::db()->delete(static::$table, 'id = ?', [$id]);
    }

    /**
     * Cột cần đổi khi đưa vào thùng rác. Ghi đè để "nhả" giá trị UNIQUE (slug, sku...)
     * cho bản ghi mới dùng lại.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function attributesOnTrash(array $row): array
    {
        return [];
    }

    /**
     * Cột cần đổi khi khôi phục (vd trả lại slug gốc).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function attributesOnRestore(array $row): array
    {
        return [];
    }

    private static function assertSoftDeletes(): void
    {
        if (!static::$softDeletes) {
            throw new \LogicException(static::class . ' không bật xoá mềm ($softDeletes = true).');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function filterFillable(array $data): array
    {
        return static::$fillable === [] ? $data : array_intersect_key($data, array_flip(static::$fillable));
    }
}
