<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected static string $table = 'payments';
    protected static array $fillable = [
        'order_id', 'gateway', 'txn_ref', 'amount', 'status', 'transaction_no', 'bank_code',
        'response_code', 'gateway_create_date', 'raw_data', 'paid_at',
    ];

    /** @return array<string, mixed>|null */
    public static function findByTxnRef(string $gateway, string $txnRef): ?array
    {
        return self::query()->where('gateway', $gateway)->where('txn_ref', $txnRef)->first();
    }
}
