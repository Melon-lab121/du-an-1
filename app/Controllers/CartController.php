<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PriceCalculator;
use App\Services\VariantService;
use App\Services\VoucherService;
use DomainException;

final class CartController extends Controller
{
    public function __construct(
        private CartService $cart,
        private VariantService $variants,
        private VoucherService $vouchers,
        private Auth $auth,
    ) {
    }

    public function index(): Response
    {
        $lines = $this->cart->lines();
        $user = $this->auth->user();
        $userId = $this->auth->id();
        $email = $user['email'] ?? null;
        $phone = $user['phone'] ?? null;

        return $this->view('cart/index', [
            'lines' => $lines,
            'totals' => $this->cart->totals($lines),
            'availableVouchers' => $this->vouchers->availableForCart($lines, $userId, $email, $phone),
            'appliedVoucher' => $this->vouchers->applied($lines, $userId, $email, $phone),
        ]);
    }

    public function add(Request $request): Response
    {
        $data = $this->validate($request, [
            'product_id' => 'required|integer|min:1',
            'variant_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:99',
        ]);

        $productId = (int) $data['product_id'];
        $quantity = (int) ($data['quantity'] ?? 1);
        $variantId = isset($data['variant_id']) && (int) $data['variant_id'] > 0
            ? (int) $data['variant_id']
            : null;

        // Form gửi các giá trị khách chọn: options[option_id] = value_id (chạy được cả khi tắt JavaScript)
        $options = $request->input('options');
        if ($variantId === null && is_array($options) && $options !== []) {
            $selectedValues = array_values(array_filter(
                array_map('intval', $options),
                static fn (int $id): bool => $id > 0,
            ));
            $variant = $this->variants->findByValues($productId, $selectedValues);
            if ($variant === null) {
                return $this->respond($request, false, 'Vui lòng chọn đủ phân loại (hoặc phân loại này đã ngừng bán).');
            }
            $variantId = (int) $variant['id'];
        }

        $buyNow = !empty($request->input('buy_now'));

        $unitPrice = $this->resolveUnitPrice($productId, $variantId);
        $key = CartService::key($productId, $variantId);
        $existingQty = (int) (($this->cart->raw()[$key] ?? 0));
        if ($unitPrice >= OrderService::HIGH_VALUE_THRESHOLD && ($existingQty + $quantity) > OrderService::HIGH_VALUE_MAX_QTY) {
            $msg = 'Sản phẩm có giá từ 10.000.000 ₫ trở lên chỉ được mua tối đa ' . OrderService::HIGH_VALUE_MAX_QTY . ' cái mỗi đơn.';
            return $this->respond($request, false, $msg);
        }

        try {
            $this->cart->add($productId, $quantity, $variantId);
        } catch (DomainException $e) {
            if ($buyNow && isset($this->cart->raw()[$key])) {
                return $this->respond($request, true, 'Chuyển sang thanh toán.');
            }

            return $this->respond($request, false, $e->getMessage());
        }

        return $this->respond($request, true, 'Đã thêm vào giỏ hàng.');
    }

    public function update(Request $request): Response
    {
        $quantities = $request->input('quantities', []);
        if (is_array($quantities)) {
            foreach ($quantities as $key => $qty) {
                $itemKey = trim((string) $key);
                if ($itemKey === '') {
                    continue;
                }
                $targetQty = (int) $qty;
                if ($targetQty > 0) {
                    [$pid, $vid] = CartService::parseKey($itemKey);
                    $unitPrice = $this->resolveUnitPrice($pid, $vid);
                    if ($unitPrice >= OrderService::HIGH_VALUE_THRESHOLD && $targetQty > OrderService::HIGH_VALUE_MAX_QTY) {
                        $targetQty = OrderService::HIGH_VALUE_MAX_QTY;
                    }
                }
                try {
                    $this->cart->update($itemKey, $targetQty);
                } catch (DomainException) {
                    $this->cart->remove($itemKey);
                }
            }
        }

        return $this->respond($request, true, 'Đã cập nhật giỏ hàng.');
    }

    public function remove(Request $request): Response
    {
        $key = (string) ($request->input('key') ?? $request->input('product_id') ?? '');
        if ($key !== '') {
            $this->cart->remove($key);
        }

        return $this->respond($request, true, 'Đã xoá sản phẩm khỏi giỏ.');
    }

    private function resolveUnitPrice(int $productId, ?int $variantId): int
    {
        if ($variantId !== null) {
            $variant = ProductVariant::query()
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->first();
            if ($variant !== null) {
                return (int) PriceCalculator::unitPrice($variant);
            }
        }

        $product = Product::query()->where('id', $productId)->first();
        return $product !== null ? (int) PriceCalculator::unitPrice($product) : 0;
    }

    /** AJAX => JSON, form thường => redirect kèm flash */
    private function respond(Request $request, bool $ok, string $message): Response
    {
        $buyNow = !empty($request->input('buy_now'));

        if ($request->wantsJson()) {
            $totals = $this->cart->totals();

            return $this->json([
                'ok' => $ok,
                'message' => $message,
                'count' => $this->cart->count(),
                'total' => $totals['total'],
                'total_formatted' => money($totals['total']),
                'redirect' => ($ok && $buyNow) ? url('/checkout') : null,
            ], $ok ? 200 : 422);
        }

        $this->flash($ok ? 'success' : 'error', $message);
        if ($ok && $buyNow) {
            return $this->redirect(url('/checkout'));
        }

        return $this->back();
    }
}