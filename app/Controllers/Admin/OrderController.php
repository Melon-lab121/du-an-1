<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
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
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private Database $db,
    ) {}

    public function index(Request $request): Response
    {
        Order::autoCompleteExpired();

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

        $guestInquiries = $this->db->select(
            'SELECT id, phone, name, address, created_at FROM guest_inquiries ORDER BY id DESC LIMIT 50',
        );

        return $this->view('admin/orders/index', [
            'pager' => $query->paginate(20, max(1, (int) $request->query('page', 1))),
            'status' => $status,
            'q' => $q,
            'guestInquiries' => $guestInquiries,
        ]);
    }

    public function show(int $id): Response
    {
        Order::autoCompleteExpired();

        $order = Order::findOrFail($id);
        return $this->view('admin/orders/show', [
            'order' => $order,
            'items' => Order::items($id),
            'payments' => Order::payments($id),
            'returns' => Order::returns($id),
        ]);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        $order = Order::findOrFail($id);
        $nextStatus = trim((string) $request->input('status', ''));
        $carrierFailed = (string) $request->input('carrier_failed', '') === '1';

        try {
            $this->validateTransitionRules($order, $nextStatus, $carrierFailed);
            $this->orders->updateStatus($id, $nextStatus, $carrierFailed);

            $updatedOrder = Order::findOrFail($id);
            $newStatus = (string) $updatedOrder['status'];
            $attempts = (int) ($updatedOrder['shipping_attempts'] ?? 0);

            if ($carrierFailed && $newStatus === 'delivery_failed') {
                $this->flash(
                    'warning',
                    'Đã giao tới đơn vị vận chuyển thất bại đủ ' . Order::MAX_SHIPPING_ATTEMPTS . ' lần. Đơn chuyển sang Giao hàng thất bại.',
                );
            } elseif ($nextStatus === 'shipping' || ((string) $order['status'] === 'shipping' && $carrierFailed)) {
                $this->flash(
                    'success',
                    'Đã cập nhật trạng thái giao đơn vị vận chuyển (lần ' . $attempts . '/' . Order::MAX_SHIPPING_ATTEMPTS . ').',
                );
            } else {
                $this->flash('success', 'Đã cập nhật trạng thái đơn.');
            }
        } catch (DomainException | InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/orders/' . $id);
    }

    public function confirmRefund(Request $request, int $id): Response
    {
        $order = Order::findOrFail($id);
        $refundNote = trim((string) $request->input('refund_note', ''));
        $now = date('Y-m-d H:i:s');

        $this->db->update('orders', [
            'payment_status' => 'refunded',
            'refund_status' => 'refunded',
            'refund_note' => $refundNote !== '' ? $refundNote : ($order['refund_note'] ?? null),
            'refunded_at' => $now,
            'updated_at' => $now,
        ], 'id = ?', [$id]);

        $this->db->update('order_returns', [
            'status' => 'refunded',
            'updated_at' => $now,
        ], 'order_id = ? AND status IN ("pending", "approved")', [$id]);

        $this->flash('success', 'Đã xác nhận hoàn tiền cho đơn hàng.');
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

    private function validateTransitionRules(array $order, string $nextStatus, bool $carrierFailed): void
    {
        if (!array_key_exists($nextStatus, Order::STATUSES)) {
            throw new InvalidArgumentException('Trạng thái đơn hàng không hợp lệ.');
        }

        if ($nextStatus === 'completed') {
            throw new DomainException('Trạng thái Hoàn thành chỉ có khi khách xác nhận đã nhận hàng hoặc tự động sau 2 tuần không trả hàng.');
        }

        $currentStatus = (string) ($order['status'] ?? 'pending');
        $attempts = (int) ($order['shipping_attempts'] ?? 0);

        if ($currentStatus === 'cancelled' || $currentStatus === 'completed') {
            throw new DomainException('Đơn hàng đã kết thúc, không thể thay đổi trạng thái.');
        }

        // Đơn thanh toán online bắt đầu từ 'confirmed' (không nhận 'pending')
        if (Order::isOnlineMethod($order) && $nextStatus === 'pending') {
            throw new DomainException('Đơn thanh toán online bắt đầu từ trạng thái Đã xác nhận đơn hàng.');
        }

        // Không được quay lại trạng thái trước đó theo thứ tự tiến độ
        $currentRank = Order::LINEAR_ORDER[$currentStatus] ?? 0;
        $nextRank = Order::LINEAR_ORDER[$nextStatus] ?? 0;
        if ($nextRank > 0 && $currentRank > 0 && $nextRank < $currentRank) {
            throw new DomainException('Không thể quay lại trạng thái trước đó.');
        }
        if ($nextStatus === 'pending' && $currentStatus !== 'pending') {
            throw new DomainException('Không thể chuyển về trạng thái Chờ xác nhận.');
        }

        // Chỉ cho phép hủy trước khi giao tới đơn vị vận chuyển
        if ($nextStatus === 'cancelled' && $currentRank >= Order::LINEAR_ORDER['shipping']) {
            throw new DomainException('Chỉ có thể hủy đơn trước khi đưa hàng tới đơn vị vận chuyển.');
        }

        // Tối đa 3 lần giao tới đơn vị vận chuyển
        if (($nextStatus === 'shipping' || ($currentStatus === 'shipping' && $carrierFailed)) && $attempts >= Order::MAX_SHIPPING_ATTEMPTS) {
            throw new DomainException('Đã đạt tối đa ' . Order::MAX_SHIPPING_ATTEMPTS . ' lần giao tới đơn vị vận chuyển.');
        }
    }
}