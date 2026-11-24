<?php

declare(strict_types=1);

namespace App\Services\Payment;

use InvalidArgumentException;

/** Registry các cổng thanh toán. */
final class PaymentManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->code()] = $gateway;
    }

    public function has(string $code): bool
    {
        return isset($this->gateways[$code]);
    }

    public function get(string $code): PaymentGatewayInterface
    {
        return $this->gateways[$code] ?? throw new InvalidArgumentException("Cổng thanh toán [{$code}] không tồn tại.");
    }

    /** @return array<string, PaymentGatewayInterface> */
    public function all(): array
    {
        return $this->gateways;
    }

    /** @return array<string, PaymentGatewayInterface> cổng đang bật và đã cấu hình đủ */
    public function enabled(): array
    {
        return array_filter($this->gateways, fn (PaymentGatewayInterface $g): bool => $g->isEnabled());
    }
}
