<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use App\Models\DiscountType;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\VoucherType;

final class VoucherService
{
    private const SESSION_KEY = 'applied_voucher_code';

    public function __construct(
        private Session $session,
        private Config $config,
        private Database $db,
    ) {
    }

    /**
     * Kiểm tra tính hợp lệ và tính số tiền giảm của một voucher.
     *
     * @param array<string, mixed>|string $voucherOrCode
     * @param array<int, array<string, mixed>> $lines
     * @return array{
     *     valid: bool,
     *     error: ?string,
     *     voucher: ?array<string, mixed>,
     *     discount: int,
     *     qualifying_subtotal: int,
     *     description: string
     * }
     */
    public function evaluate(
        array|string $voucherOrCode,
        array $lines,
        ?int $userId = null,
        ?string $email = null,
        ?string $phone = null
    ): array {
        $voucher = is_array($voucherOrCode) ? $voucherOrCode : Voucher::findByCode($voucherOrCode);
        if ($voucher === null) {
            return [
                'valid' => false,
                'error' => 'Mã giảm giá không tồn tại.',
                'voucher' => null,
                'discount' => 0,
                'qualifying_subtotal' => 0,
                'description' => '',
            ];
        }

        // 1. Kiểm tra trạng thái kích hoạt
        if (!(int) ($voucher['is_active'] ?? 0)) {
            return [
                'valid' => false,
                'error' => 'Mã giảm giá hiện đang tạm khóa.',
                'voucher' => $voucher,
                'discount' => 0,
                'qualifying_subtotal' => 0,
                'description' => '',
            ];
        }

        // 2. Kiểm tra thời hạn hiệu lực
        $now = date('Y-m-d H:i:s');
        if (!empty($voucher['start_date']) && $now < $voucher['start_date']) {
            return [
                'valid' => false,
                'error' => 'Mã giảm giá chưa đến thời gian áp dụng (bắt đầu từ ' . date('d/m/Y', strtotime($voucher['start_date'])) . ').',
                'voucher' => $voucher,
                'discount' => 0,
                'qualifying_subtotal' => 0,
                'description' => '',
            ];
        }
        if (!empty($voucher['end_date']) && $now > $voucher['end_date']) {
            return [
                'valid' => false,
                'error' => 'Mã giảm giá đã hết hạn sử dụng (hết hạn ngày ' . date('d/m/Y', strtotime($voucher['end_date'])) . ').',
                'voucher' => $voucher,
                'discount' => 0,
                'qualifying_subtotal' => 0,
                'description' => '',
            ];
        }

        // 3. Kiểm tra giỏ hàng rỗng
        $subtotal = array_sum(array_column($lines, 'total'));
        if ($lines === [] || $subtotal <= 0) {
            return [
                'valid' => false,
                'error' => 'Giỏ hàng đang trống.',
                'voucher' => $voucher,
                'discount' => 0,
                'qualifying_subtotal' => 0,
                'description' => '',
            ];
        }

        // 4. Kiểm tra mức chi tiêu tối thiểu
        $minSpending = (int) ($voucher['min_spending'] ?? 0);
        if ($minSpending > 0 && $subtotal < $minSpending) {
            return [
                'valid' => false,
                'error' => 'Đơn hàng chưa đạt mức chi tiêu tối thiểu ' . money($minSpending) . ' (hiện tại: ' . money($subtotal) . ').',
                'voucher' => $voucher,
                'discount' => 0,
                'qualifying_subtotal' => 0,
                'description' => '',
            ];
        }

        // 5. Kiểm tra giới hạn lượt dùng mỗi người
        $maxPerUser = (int) ($voucher['max_usage_per_user'] ?? 0);
        if ($maxPerUser > 0) {
            $usedCount = Voucher::usageCountForCustomer((string) $voucher['code'], $userId, $email, $phone);
            if ($usedCount >= $maxPerUser) {
                return [
                    'valid' => false,
                    'error' => 'Bạn đã sử dụng hết giới hạn ' . $maxPerUser . ' lần cho mã này.',
                    'voucher' => $voucher,
                    'discount' => 0,
                    'qualifying_subtotal' => 0,
                    'description' => '',
                ];
            }
        }

        // 6. Kiểm tra theo loại voucher (Danh mục, Sản phẩm, Freeship)
        $voucherType = (int) ($voucher['voucher_type'] ?? 1);
        $discountType = (int) ($voucher['discount_type'] ?? 1);
        $discountValue = (int) ($voucher['discount_value'] ?? 0);
        $shippingFee = (int) $this->config->get('shop.shipping_fee', 0);
        $freeShippingThreshold = (int) $this->config->get('shop.free_shipping_threshold', 0);
        $isFreeShipByDefault = ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold);

        $qualifyingSubtotal = 0;
        $discount = 0;
        $description = '';

        if ($voucherType === VoucherType::FREESHIP) {
            // Freeship voucher
            if ($shippingFee <= 0 || $isFreeShipByDefault) {
                return [
                    'valid' => false,
                    'error' => 'Đơn hàng của bạn đã được hưởng chính sách miễn phí vận chuyển.',
                    'voucher' => $voucher,
                    'discount' => 0,
                    'qualifying_subtotal' => $subtotal,
                    'description' => 'Miễn phí vận chuyển',
                ];
            }

            if ($discountType === DiscountType::PERCENT) {
                $discount = (int) round(($shippingFee * min(100, max(0, $discountValue))) / 100);
                $description = 'Miễn phí vận chuyển ' . $discountValue . '%';
            } else {
                $discount = min($shippingFee, $discountValue);
                $description = 'Giảm phí vận chuyển ' . money($discount);
            }
            $qualifyingSubtotal = $shippingFee;

        } elseif ($voucherType === VoucherType::CATEGORY) {
            // Giảm theo danh mục
            $allowedCatIds = Voucher::categoryIds((int) $voucher['id']);
            $qualifyingLines = [];

            if ($allowedCatIds === []) {
                // Không gán danh mục cụ thể => áp dụng toàn bộ giỏ
                $qualifyingLines = $lines;
            } else {
                // Lọc các dòng có product thuộc danh mục chỉ định
                $productIdsInCart = array_unique(array_map('intval', array_column($lines, 'product_id')));
                $productCategories = [];
                if ($productIdsInCart !== []) {
                    // Tra cứu danh mục từ products.category_id và product_categories
                    $products = $this->db->select(
                        'SELECT id, category_id FROM products WHERE id IN (' . implode(',', $productIdsInCart) . ')'
                    );
                    foreach ($products as $p) {
                        if ($p['category_id'] !== null) {
                            $productCategories[(int) $p['id']][] = (int) $p['category_id'];
                        }
                    }

                    try {
                        $pivot = $this->db->select(
                            'SELECT product_id, category_id FROM product_categories WHERE product_id IN (' . implode(',', $productIdsInCart) . ')'
                        );
                        foreach ($pivot as $pv) {
                            $productCategories[(int) $pv['product_id']][] = (int) $pv['category_id'];
                        }
                    } catch (\Throwable) {
                        // Bỏ qua nếu môi trường test không có bảng phụ product_categories
                    }
                }

                foreach ($lines as $line) {
                    $pid = (int) $line['product_id'];
                    $cats = $productCategories[$pid] ?? [];
                    if (array_intersect($cats, $allowedCatIds) !== []) {
                        $qualifyingLines[] = $line;
                    }
                }
            }

            if ($qualifyingLines === []) {
                return [
                    'valid' => false,
                    'error' => 'Mã giảm giá này chỉ áp dụng cho một số danh mục sản phẩm nhất định không có trong giỏ hàng.',
                    'voucher' => $voucher,
                    'discount' => 0,
                    'qualifying_subtotal' => 0,
                    'description' => '',
                ];
            }

            $qualifyingSubtotal = array_sum(array_column($qualifyingLines, 'total'));

            if ($discountType === DiscountType::PERCENT) {
                $discount = (int) round(($qualifyingSubtotal * min(100, max(0, $discountValue))) / 100);
                $description = 'Giảm ' . $discountValue . '% cho sản phẩm danh mục hợp lệ';
            } else {
                $discount = min($qualifyingSubtotal, $discountValue);
                $description = 'Giảm ' . money($discountValue) . ' cho sản phẩm danh mục hợp lệ';
            }

        } elseif ($voucherType === VoucherType::PRODUCT) {
            // Giảm theo sản phẩm
            $allowedProdIds = Voucher::productIds((int) $voucher['id']);
            $qualifyingLines = [];

            if ($allowedProdIds === []) {
                $qualifyingLines = $lines;
            } else {
                foreach ($lines as $line) {
                    if (in_array((int) $line['product_id'], $allowedProdIds, true)) {
                        $qualifyingLines[] = $line;
                    }
                }
            }

            if ($qualifyingLines === []) {
                return [
                    'valid' => false,
                    'error' => 'Mã giảm giá chỉ áp dụng cho một số sản phẩm chỉ định không có trong giỏ hàng.',
                    'voucher' => $voucher,
                    'discount' => 0,
                    'qualifying_subtotal' => 0,
                    'description' => '',
                ];
            }

            $qualifyingSubtotal = array_sum(array_column($qualifyingLines, 'total'));

            if ($discountType === DiscountType::PERCENT) {
                $discount = (int) round(($qualifyingSubtotal * min(100, max(0, $discountValue))) / 100);
                $description = 'Giảm ' . $discountValue . '% cho sản phẩm chỉ định';
            } else {
                $discount = min($qualifyingSubtotal, $discountValue);
                $description = 'Giảm ' . money($discountValue) . ' cho sản phẩm chỉ định';
            }
        }

        $discount = max(0, $discount);

        return [
            'valid' => true,
            'error' => null,
            'voucher' => $voucher,
            'discount' => $discount,
            'qualifying_subtotal' => $qualifyingSubtotal,
            'description' => $description,
        ];
    }

    /**
     * Áp dụng mã voucher vào session sau khi kiểm tra hợp lệ.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array{success: bool, message: string, discount: int, voucher: ?array<string, mixed>}
     */
    public function apply(
        string $code,
        array $lines,
        ?int $userId = null,
        ?string $email = null,
        ?string $phone = null
    ): array {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['success' => false, 'message' => 'Vui lòng nhập mã giảm giá.', 'discount' => 0, 'voucher' => null];
        }

        $res = $this->evaluate($code, $lines, $userId, $email, $phone);
        if (!$res['valid']) {
            return [
                'success' => false,
                'message' => $res['error'] ?? 'Mã giảm giá không hợp lệ.',
                'discount' => 0,
                'voucher' => null,
            ];
        }

        $this->session->set(self::SESSION_KEY, $code);

        return [
            'success' => true,
            'message' => 'Áp dụng mã ' . $code . ' thành công! Tiết kiệm ' . money($res['discount']) . '.',
            'discount' => $res['discount'],
            'voucher' => $res['voucher'],
        ];
    }

    /**
     * Lấy thông tin voucher đang được áp dụng từ session (nếu có và còn hợp lệ với giỏ hàng hiện tại).
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array{voucher: array<string, mixed>, discount: int, description: string}|null
     */
    public function applied(array $lines, ?int $userId = null, ?string $email = null, ?string $phone = null): ?array
    {
        $code = (string) $this->session->get(self::SESSION_KEY, '');
        if ($code === '') {
            return null;
        }

        $res = $this->evaluate($code, $lines, $userId, $email, $phone);
        if (!$res['valid']) {
            // Nếu sau khi giỏ hàng đổi mà mã không còn hợp lệ nữa => tự xóa khỏi session
            $this->remove();
            return null;
        }

        return [
            'voucher' => $res['voucher'],
            'discount' => $res['discount'],
            'description' => $res['description'],
        ];
    }

    /** Trả về mã voucher đang lưu trong session (nếu có) */
    public function appliedCode(): ?string
    {
        $code = (string) $this->session->get(self::SESSION_KEY, '');
        return $code !== '' ? $code : null;
    }

    /** Xóa mã voucher khỏi session */
    public function remove(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
