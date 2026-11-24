<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Hợp đồng cho mọi cổng thanh toán. Thêm cổng mới (MoMo, ZaloPay, Stripe...):
 *   1. Tạo class implements interface này
 *   2. Đăng ký: app(PaymentManager::class)->register(new MomoGateway($config))  (trong Provider/Module)
 *   3. Thêm route callback/IPN riêng cho cổng đó
 */
interface PaymentGatewayInterface
{
    /** Mã định danh, lưu vào orders.payment_method (vd: "sepay") */
    public function code(): string;

    public function name(): string;

    public function description(): string;

    public function isEnabled(): bool;

    /** true nếu cần chuyển hướng khách sang trang của cổng thanh toán */
    public function isOnline(): bool;

    /**
     * Dữ liệu để chuyển khách sang trang thanh toán của cổng: trang của ta in form ẩn
     * (method POST, action = 'action', các input = 'fields') rồi tự submit.
     *
     * @param array{txn_ref: string, amount: int, order_info: string, method?: ?string} $payment
     * @return array{action: string, fields: array<string, string>}
     */
    public function createCheckout(array $payment): array;
}
