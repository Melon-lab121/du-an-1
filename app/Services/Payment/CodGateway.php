<?php

declare(strict_types=1);

namespace App\Services\Payment;

use LogicException;

final class CodGateway implements PaymentGatewayInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function code(): string
    {
        return 'cod';
    }

    public function name(): string
    {
        return 'Thanh toán khi nhận hàng (COD)';
    }

    public function description(): string
    {
        return 'Trả tiền mặt khi nhận hàng.';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function isOnline(): bool
    {
        return false;
    }

    public function createCheckout(array $payment): array
    {
        throw new LogicException('COD không có trang thanh toán online.');
    }
}
