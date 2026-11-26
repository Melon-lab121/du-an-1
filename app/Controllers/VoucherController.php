<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\VoucherService;

final class VoucherController extends Controller
{
    public function __construct(
        private VoucherService $vouchers,
        private CartService $cart,
        private Auth $auth,
    ) {
    }

    public function apply(Request $request): Response
    {
        $code = (string) ($request->input('code') ?: $request->input('voucher_code_input', ''));
        $lines = $this->cart->lines();
        $user = $this->auth->user();

        $result = $this->vouchers->apply(
            $code,
            $lines,
            $this->auth->id(),
            $user['email'] ?? null,
            $user['phone'] ?? null
        );

        if ($request->wantsJson()) {
            return Response::json([
                'success' => $result['success'],
                'message' => $result['message'],
                'discount' => $result['discount'],
                'totals' => $this->cart->totals($lines),
            ], $result['success'] ? 200 : 422);
        }

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
            return Response::json([
                'success' => true,
                'message' => 'Đã gỡ mã giảm giá.',
                'totals' => $this->cart->totals(),
            ]);
        }

        $this->flash('success', 'Đã gỡ mã giảm giá.');
        return Response::redirect(back_url('/cart'));
    }
}
