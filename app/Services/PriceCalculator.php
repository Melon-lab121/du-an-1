<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hàm tính tiền thuần (không phụ thuộc DB/session) => dễ test, dễ thay đổi chính sách giá.
 * Tiền tệ: VND, luôn là số nguyên.
 */
final class PriceCalculator
{
    /** @param array<string, mixed> $product */
    public static function unitPrice(array $product): int
    {
        $price = (int) $product['price'];
        $sale = isset($product['sale_price']) ? (int) $product['sale_price'] : 0;
        return $sale > 0 && $sale < $price ? $sale : $price;
    }

    /**
     * @param array<int, array{total: int}> $lines
     * @return array{subtotal: int, discount: int, shipping_fee: int, total: int}
     */
    public static function totals(array $lines, int $shippingFee, int $freeShippingThreshold = 0, int $discount = 0): array
    {
        $subtotal = array_sum(array_map(fn (array $l): int => (int) $l['total'], $lines));
        $discount = min(max(0, $discount), $subtotal);
        $shipping = ($subtotal === 0 || ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold)) ? 0 : max(0, $shippingFee);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shipping,
            'total' => $subtotal - $discount + $shipping,
        ];
    }
}
