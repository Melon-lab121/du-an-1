<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private PaymentService $payments)
    {
    }

    public function index(Request $request): Response
    {
        $query = Order::query()->latest();
        $status = (string) $request->query('status', '');
        if ($status !== '' && isset(Order::STATUSES[$status])) {
            $query->where('status', $status);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->whereRaw('code LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?', [$like, $like, $like]);
        }

        return $this->view('admin/orders/index', [
            'pager' => $query->paginate(20, max(1, (int) $request->query('page', 1))),
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function show(int $id): Response
    {
        $order = Order::findOrFail($id);
        return $this->view('admin/orders/show', [
            'order' => $order,
            'items' => Order::items($id),
            'payments' => Order::payments($id),
        ]);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        try {
            $this->orders->updateStatus($id, (string) $request->input('status'));
            $this->flash('success', 'Đã cập nhật trạng thái đơn.');
        } catch (DomainException | InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/orders/' . $id);
    }

    /** Đối soát giao dịch SePay qua API chi tiết đơn (hữu ích khi IPN không tới được). */
    public function reconcile(Request $request, int $id, int $paymentId): Response
    {
        $payment = Payment::find($paymentId);
        if ($payment === null || (int) $payment['order_id'] !== $id || $payment['gateway'] !== 'sepay') {
            throw new HttpException(404);
        }

        try {
            $result = $this->payments->reconcileSepay($payment);
            $this->flash($result['updated'] ? 'success' : 'info', $result['message']);
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/orders/' . $id);
    }
}
