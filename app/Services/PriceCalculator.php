<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hàm tính tiền thuần (không phụ thuộc DB/session) => dễ test, dễ thay đổi chính sách giá.
 * Tiền tệ: VND, luôn là số nguyên.
 */
final class PriceCalculator
{
    /**
     * Tính đơn giá hiệu lực của sản phẩm hoặc biến thể (ưu tiên giá khuyến mãi hợp lệ).
     *
     * @param array<string, mixed> $item
     */
    public static function unitPrice(array $item): int
    {
        $price = (int) round((float) ($item['price'] ?? 0));
        $sale = isset($item['sale_price']) && $item['sale_price'] !== null
            ? (int) round((float) $item['sale_price'])
            : 0;

        return ($sale > 0 && $sale < $price) ? $sale : max(0, $price);
    }

    /**
     * Tính tổng tiền theo danh sách dòng hàng (đã lọc theo sản phẩm được chọn nếu thanh toán một phần).
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array{subtotal: int, discount: int, shipping_fee: int, total: int}
     */
    public static function totals(array $lines, int $shippingFee, int $freeShippingThreshold = 0, int $discount = 0): array
    {
        $subtotal = (int) array_sum(array_map(
            static fn (array $l): int => (int) round((float) ($l['total'] ?? 0)),
            $lines,
        ));

        $safeDiscount = min(max(0, (int) round((float) $discount)), $subtotal);
        $baseShipping = max(0, (int) round((float) $shippingFee));

        $shipping = ($subtotal === 0 || ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold))
            ? 0
            : $baseShipping;

        return [
            'subtotal' => $subtotal,
            'discount' => $safeDiscount,
            'shipping_fee' => $shipping,
            'total' => max(0, $subtotal - $safeDiscount + $shipping),
        ];
    }
}