<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\VoucherService;

final class VoucherController extends Controller
{
    public function __construct(
        private VoucherService $vouchers,
        private CartService $cart,
        private Auth $auth,
        private Session $session,
    ) {
    }

    public function apply(Request $request): Response
    {
        $all = $request->all();
        // Nếu chọn option "-- Không áp dụng voucher --" (rỗng) từ select đặt hàng => gỡ mã hiện tại
        if (array_key_exists('voucher_code_input', $all) && trim((string) $all['voucher_code_input']) === '' && !isset($all['code'])) {
            return $this->remove($request);
        }

        $code = (string) ($request->input('code') ?: $request->input('voucher_code_input', ''));
        $lines = $this->resolveTargetLines($request);
        $user = $this->auth->user();

        $result = $this->vouchers->apply(
            $code,
            $lines,
            $this->auth->id(),
            $user['email'] ?? null,
            $user['phone'] ?? null
        );

        if ($request->wantsJson()) {
            $totals = $this->cart->totals($lines);
            $applied = $this->vouchers->applied($lines, $this->auth->id(), $user['email'] ?? null, $user['phone'] ?? null);
            return Response::json([
                'success' => $result['success'],
                'message' => $result['message'],
                'discount' => $result['discount'],
                'totals' => $totals,
                'applied' => $applied ? [
                    'code' => $applied['voucher']['code'],
                    'description' => $applied['description'],
                ] : null,
                'summary_html' => view('partials/order-summary', ['totals' => $totals]),
            ], $result['success'] ? 200 : 422);
        }

        // Giữ lại thông tin người nhận nếu khách đang điền dở trên trang đặt hàng
        $this->preserveCheckoutInputs($all);

        if ($result['success']) {
            $this->flash('success', $result['message']);
        } else {
            $this->flash('error', $result['message']);
        }

        return Response::redirect(back_url('/cart'));
    }

    public function remove(Request $request): Response
    {
        $this->vouchers->remove();

        if ($request->wantsJson()) {
            $lines = $this->resolveTargetLines($request);
            $totals = $this->cart->totals($lines);
            return Response::json([
                'success' => true,
                'message' => 'Đã gỡ mã giảm giá.',
                'totals' => $totals,
                'applied' => null,
                'summary_html' => view('partials/order-summary', ['totals' => $totals]),
            ]);
        }

        $this->preserveCheckoutInputs($request->all());
        $this->flash('success', 'Đã gỡ mã giảm giá.');
        return Response::redirect(back_url('/cart'));
    }

    /**
     * Lọc theo `selected_keys[]` nếu thao tác từ trang đặt hàng; mặc định trả về toàn bộ giỏ.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveTargetLines(Request $request): array
    {
        $allLines = $this->cart->lines();
        $selected = $request->input('selected_keys');
        if (!is_array($selected) && array_key_exists('voucher_code_input', $request->all())) {
            $sessionSelected = $this->session->get('checkout_selected_keys');
            if (is_array($sessionSelected)) {
                $selected = $sessionSelected;
            }
        }

        if (!is_array($selected) || $selected === []) {
            return $allLines;
        }

        $set = array_flip(array_map('strval', $selected));
        $filtered = array_values(array_filter(
            $allLines,
            static fn (array $line): bool => isset($set[(string) $line['key']]),
        ));

        return $filtered !== [] ? $filtered : $allLines;
    }

    /** @param array<string, mixed> $input */
    private function preserveCheckoutInputs(array $input): void
    {
        $checkoutKeys = ['name', 'phone', 'email', 'address', 'note', 'payment_method', 'sepay_method', 'selected_keys'];
        if (array_intersect($checkoutKeys, array_keys($input)) !== []) {
            $this->session->flash('_old', $input);
        }
    }
}
