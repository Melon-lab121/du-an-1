<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class DiscountType extends Model
{
    public const PERCENT = 1;
    public const FIXED = 2;

    protected static string $table = 'discount_types';
    protected static array $fillable = ['discount_type'];
    protected static bool $timestamps = false;

    public const LABELS = [
        self::PERCENT => 'Giảm theo phần trăm (%)',
        self::FIXED => 'Giảm số tiền cố định (₫)',
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
