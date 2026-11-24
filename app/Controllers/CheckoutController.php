<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SepayGateway;
use DomainException;
use RuntimeException;

final class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private OrderService $orders,
        private PaymentManager $gateways,
        private PaymentService $payments,
        private Auth $auth,
        private Session $session,
        private Logger $logger,
    ) {
    }

    public function index(): Response
    {
        $lines = $this->cart->lines();
        if ($lines === []) {
            $this->flash('error', 'Giỏ hàng đang trống.');
            return $this->redirect('/cart');
        }

        return $this->view('checkout/index', [
            'lines' => $lines,
            'totals' => $this->cart->totals($lines),
            'gateways' => $this->gateways->enabled(),
            'sepayMethods' => SepayGateway::PAYMENT_METHODS,
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        $enabled = implode(',', array_keys($this->gateways->enabled()));
        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'required|phone',
            'address' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
            'payment_method' => 'required|in:' . $enabled,
            'sepay_method' => 'nullable|in:' . implode(',', array_filter(array_keys(SepayGateway::PAYMENT_METHODS))),
        ], [
            'name' => 'Họ tên', 'email' => 'Email', 'phone' => 'Số điện thoại', 'address' => 'Địa chỉ',
            'note' => 'Ghi chú', 'payment_method' => 'Phương thức thanh toán', 'sepay_method' => 'Hình thức SePay',
        ]);

        try {
            $order = $this->orders->place(
                ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'address' => $data['address'], 'note' => $data['note'] ?? null],
                $this->cart->raw(),
                $data['payment_method'],
                $this->auth->id(),
            );
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/cart');
        }

        $this->cart->clear();
        $this->rememberGuestOrder((string) $order['code']);

        try {
            $checkout = $this->payments->start($order, $data['sepay_method'] ?? null);
        } catch (DomainException | RuntimeException $e) {
            $this->logger->error('Không tạo được thanh toán: ' . $e->getMessage(), ['order' => $order['code']], 'payment');
            $this->flash('error', 'Đặt hàng thành công nhưng chưa tạo được thanh toán: ' . $e->getMessage());
            return $this->redirect('order.show', ['code' => $order['code']]);
        }

        if ($checkout !== null) {
            // Form ẩn đã ký, tự POST sang trang thanh toán của cổng
            return $this->view('payment/redirect', ['checkout' => $checkout]);
        }

        $this->flash('success', 'Đặt hàng thành công! Mã đơn: ' . $order['code']);
        return $this->redirect('order.show', ['code' => $order['code']]);
    }

    /** Khách vãng lai xem lại được đơn của mình trong phiên làm việc. */
    private function rememberGuestOrder(string $code): void
    {
        $codes = (array) $this->session->get('guest_orders', []);
        $codes[] = $code;
        $this->session->set('guest_orders', array_slice(array_unique($codes), -20));
    }
}
