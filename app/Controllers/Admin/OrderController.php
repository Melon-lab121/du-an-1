<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Auth;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private Auth $auth,
    ) {
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
        $failedAttempts = Order::failedAttempts($id, (string) $order['status']);
        return $this->view('admin/orders/show', [
            'order' => $order,
            'items' => Order::items($id),
            'payments' => Order::payments($id),
            'history' => Order::statusHistory($id),
            'nextStatuses' => Order::adminNextStatuses((string) $order['status'], $failedAttempts),
            'failedAttempts' => $failedAttempts,
        ]);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        $status = (string) $request->input('status');
        // "Hoàn thành" là việc của khách (xác nhận đã nhận hàng) hoặc hệ thống (tự động sau N ngày), admin không bấm được
        if (in_array($status, Order::CUSTOMER_ONLY_STATUSES, true)) {
            $this->flash('error', sprintf(
                'Trạng thái "%s" do khách hàng xác nhận đã nhận hàng (hoặc tự động sau %d ngày), admin không đổi được.',
                Order::statusLabel($status),
                Order::AUTO_COMPLETE_DAYS,
            ));
            return $this->redirect('/admin/orders/' . $id);
        }

        $note = trim((string) $request->input('note', ''));
        try {
            $this->orders->updateStatus(
                $id,
                $status,
                changedBy: $this->auth->id(),
                note: $note === '' ? null : mb_substr($note, 0, 255),
            );
            $this->flash('success', 'Đã cập nhật trạng thái đơn.');
        } catch (DomainException | InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/orders/' . $id);
    }

    /** Admin ghi nhận 1 lần lấy/giao hàng thất bại (trạng thái đơn giữ nguyên). */
    public function deliveryFailed(Request $request, int $id): Response
    {
        Order::findOrFail($id);
        $reason = trim((string) $request->input('note', ''));
        try {
            $attempt = $this->orders->recordDeliveryFailure(
                $id,
                $this->auth->id(),
                $reason === '' ? null : mb_substr($reason, 0, 200),
            );
            $this->flash('success', sprintf('Đã ghi nhận thất bại lần %d/%d.', $attempt, Order::MAX_DELIVERY_ATTEMPTS));
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/orders/' . $id);
    }

    /** Admin xác nhận đã hoàn tiền thủ công cho đơn đang ở "Cần hoàn tiền". */
    public function refunded(Request $request, int $id): Response
    {
        Order::findOrFail($id);
        $note = trim((string) $request->input('note', ''));
        try {
            $this->orders->markRefunded(
                $id,
                $this->auth->id(),
                $note === '' ? null : mb_substr($note, 0, 255),
            );
            $this->flash('success', 'Đã ghi nhận hoàn tiền cho khách.');
        } catch (DomainException $e) {
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
