<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Services\Auth;
use App\Services\Payment\PaymentService;
use DomainException;
use RuntimeException;

final class OrderController extends Controller
{
    public function __construct(private Auth $auth, private Session $session, private PaymentService $payments)
    {
    }

    public function index(Request $request): Response
    {
        $pager = Order::query()
            ->where('user_id', (int) $this->auth->id())
            ->latest()
            ->paginate(10, max(1, (int) $request->query('page', 1)));

        return $this->view('orders/index', ['pager' => $pager]);
    }

    public function show(string $code): Response
    {
        $order = $this->findAccessible($code);
        return $this->view('orders/show', [
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'payments' => Order::payments((int) $order['id']),
        ]);
    }

    /** Thanh toán lại đơn online chưa thanh toán / thanh toán lỗi. */
    public function pay(Request $request, string $code): Response
    {
        $order = $this->findAccessible($code);
        try {
            $checkout = $this->payments->start($order);
        } catch (DomainException | RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('order.show', ['code' => $code]);
        }

        if ($checkout === null) {
            $this->flash('error', 'Đơn hàng này không dùng thanh toán online.');
            return $this->redirect('order.show', ['code' => $code]);
        }
        return $this->view('payment/redirect', ['checkout' => $checkout]);
    }

    /** @return array<string, mixed> */
    private function findAccessible(string $code): array
    {
        $order = Order::findByCode($code) ?? throw new HttpException(404, 'Không tìm thấy đơn hàng');

        $isOwner = $order['user_id'] !== null && (int) $order['user_id'] === $this->auth->id();
        $isGuestOwner = in_array($code, (array) $this->session->get('guest_orders', []), true);
        if (!$isOwner && !$isGuestOwner && !$this->auth->isAdmin()) {
            throw new HttpException(404, 'Không tìm thấy đơn hàng'); // 404 thay vì 403 để không lộ mã đơn
        }
        return $order;
    }
}
