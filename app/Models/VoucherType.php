<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class VoucherType extends Model
{
    public const CATEGORY = 1;
    public const PRODUCT = 2;
    public const FREESHIP = 3;

    protected static string $table = 'voucher_types';
    protected static array $fillable = ['voucher_type'];
    protected static bool $timestamps = false;

    public const LABELS = [
        self::CATEGORY => 'Giảm theo danh mục',
        self::PRODUCT => 'Giảm theo sản phẩm',
        self::FREESHIP => 'Miễn phí vận chuyển',
    ];

    public static function label(int $id): string
    {
        return self::LABELS[$id] ?? 'Không xác định';
    }

    /** @return array<int, array<string, mixed>> */
    public static function allTypes(): array
    {
        return self::query()->orderBy('id')->get();
    }
}
