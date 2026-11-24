<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CartService;
use App\Services\VariantService;
use DomainException;

final class CartController extends Controller
{
    public function __construct(private CartService $cart, private VariantService $variants)
    {
    }

    public function index(): Response
    {
        $lines = $this->cart->lines();
        return $this->view('cart/index', [
            'lines' => $lines,
            'totals' => $this->cart->totals($lines),
        ]);
    }

    public function add(Request $request): Response
    {
        $data = $this->validate($request, [
            'product_id' => 'required|integer|min:1',
            'variant_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:99',
        ]);

        // Form gửi các giá trị khách chọn: options[option_id] = value_id (chạy được cả khi tắt JavaScript)
        $variantId = $data['variant_id'] ?? null;
        $options = $request->input('options');
        if ($variantId === null && is_array($options) && $options !== []) {
            $variant = $this->variants->findByValues($data['product_id'], array_map('intval', array_values($options)));
            if ($variant === null) {
                return $this->respond($request, false, 'Vui lòng chọn đủ phân loại (hoặc phân loại này đã ngừng bán).');
            }
            $variantId = (int) $variant['id'];
        }

        $buyNow = !empty($request->input('buy_now'));

        try {
            $this->cart->add($data['product_id'], $data['quantity'] ?? 1, $variantId);
        } catch (DomainException $e) {
            $key = CartService::key($data['product_id'], $variantId);
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
                $key = (string) $key; // "12" hoặc "12:5" (sản phẩm:biến thể)
                try {
                    $this->cart->update($key, (int) $qty);
                } catch (DomainException) {
                    $this->cart->remove($key);
                }
            }
        }
        return $this->respond($request, true, 'Đã cập nhật giỏ hàng.');
    }

    public function remove(Request $request): Response
    {
        $this->cart->remove((string) ($request->input('key') ?? $request->input('product_id')));
        return $this->respond($request, true, 'Đã xoá sản phẩm khỏi giỏ.');
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
