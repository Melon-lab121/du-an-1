<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Services\Auth;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use DomainException;
use RuntimeException;

final class OrderController extends Controller
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private OrderService $orders,
        private PaymentService $payments,
        private Database $db,
    ) {}

    public function index(Request $request): Response
    {
        Order::autoCompleteExpired();

        $pager = Order::query()
            ->where('user_id', (int) $this->auth->id())
            ->latest()
            ->paginate(10, max(1, (int) $request->query('page', 1)));

        return $this->view('orders/index', ['pager' => $pager]);
    }

    public function show(string $code): Response
    {
        Order::autoCompleteExpired();

        $order = $this->findAccessible($code);
        return $this->view('orders/show', [
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'payments' => Order::payments((int) $order['id']),
            'returns' => Order::returns((int) $order['id']),
        ]);
    }

    /** Hủy đơn trước khi hàng đưa tới đơn vị vận chuyển. */
    public function cancel(string $code): Response
    {
        $order = $this->findAccessible($code);

        if (!Order::canCustomerCancel($order)) {
            $this->flash('error', 'Không thể hủy đơn sau khi hàng đã giao tới đơn vị vận chuyển hoặc đơn đã kết thúc.');
            return $this->redirect('order.show', ['code' => $code]);
        }

        try {
            $this->orders->updateStatus((int) $order['id'], 'cancelled');
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('order.show', ['code' => $code]);
        }

        $isPaidOnline = Order::isOnlineMethod($order) && (($order['payment_status'] ?? '') === 'paid');

        $this->flash('success', $isPaidOnline
            ? 'Đã hủy đơn hàng. Yêu cầu hoàn tiền đã được ghi nhận cho admin xử lý.'
            : 'Đã hủy đơn hàng.');

        return $this->redirect('order.show', ['code' => $code]);
    }

    /** Chỉ khách hàng mới được xác nhận đã nhận hàng -> chuyển sang trạng thái Hoàn thành. */
    public function confirmReceived(string $code): Response
    {
        $order = $this->findAccessible($code);

        if (!Order::canConfirmReceived($order)) {
            $this->flash('error', 'Chỉ có thể xác nhận đã nhận hàng khi đơn hàng đã giao thành công.');
            return $this->redirect('order.show', ['code' => $code]);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->update('orders', [
            'status' => 'completed',
            'completed_at' => $now,
            'updated_at' => $now,
        ], 'id = ?', [(int) $order['id']]);

        $this->flash('success', 'Đã xác nhận nhận hàng. Bạn có thể đánh giá sản phẩm hoặc yêu cầu trả lại hàng trong vòng 7 ngày.');
        return $this->redirect('order.show', ['code' => $code]);
    }

    /** Gửi yêu cầu trả lại hàng (trong vòng 7 ngày kể từ khi giao thành công). */
    public function submitReturn(Request $request, string $code): Response
    {
        $order = $this->findAccessible($code);

        if (!Order::canRequestReturn($order)) {
            $this->flash('error', 'Đã quá hạn 7 ngày kể từ khi giao thành công hoặc đơn hàng chưa đủ điều kiện trả hàng.');
            return $this->redirect('order.show', ['code' => $code]);
        }

        $reason = trim((string) ($request->input('reason') ?? ''));
        $description = trim((string) ($request->input('description') ?? ''));

        if (!array_key_exists($reason, Order::RETURN_REASONS) || $description === '') {
            $this->flash('error', 'Vui lòng chọn lý do và nhập mô tả (kèm số tài khoản nếu đã thanh toán).');
            return $this->redirect('/orders/' . urlencode($code) . '#return-box');
        }

        $file = $request->files()['proof_image'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Vui lòng tải lên ảnh minh chứng.');
            return $this->redirect('/orders/' . urlencode($code) . '#return-box');
        }

        try {
            $proofPath = $this->storeProofImage($file);
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/orders/' . urlencode($code) . '#return-box');
        }

        $now = date('Y-m-d H:i:s');
        $isPaidOnline = Order::isOnlineMethod($order) && (($order['payment_status'] ?? '') === 'paid');

        $this->db->insert('order_returns', [
            'order_id' => (int) $order['id'],
            'user_id' => $this->auth->id(),
            'reason' => $reason,
            'description' => $description,
            'proof_image' => $proofPath,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderUpdate = [
            'status' => 'returned',
            'updated_at' => $now,
        ];
        if ($isPaidOnline) {
            $orderUpdate['refund_status'] = 'pending_refund';
            $orderUpdate['refund_note'] = $description;
        }

        $this->db->update('orders', $orderUpdate, 'id = ?', [(int) $order['id']]);

        $this->flash('success', $isPaidOnline
            ? 'Đã gửi yêu cầu trả lại hàng kèm minh chứng và thông tin STK hoàn tiền.'
            : 'Đã gửi yêu cầu trả lại hàng kèm minh chứng.');

        return $this->redirect('order.show', ['code' => $code]);
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

    private function storeProofImage(array $file): string
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed, true) || !is_uploaded_file($tmpName)) {
            throw new DomainException('Ảnh minh chứng chỉ hỗ trợ định dạng JPG, JPEG, PNG, WEBP.');
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/returns';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'proof_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmpName, $dir . '/' . $filename)) {
            throw new DomainException('Không thể lưu ảnh minh chứng.');
        }

        return '/uploads/returns/' . $filename;
    }

    /** @return array<string, mixed> */
    private function findAccessible(string $code): array
    {
        $order = Order::findByCode($code) ?? throw new HttpException(404, 'Không tìm thấy đơn hàng');

        $isOwner = $order['user_id'] !== null && (int) $order['user_id'] === $this->auth->id();
        $isGuestOwner = in_array($code, (array) $this->session->get('guest_orders', []), true);
        if (!$isOwner && !$isGuestOwner && !$this->auth->isAdmin()) {
            throw new HttpException(404, 'Không tìm thấy đơn hàng');
        }
        return $order;
    }
}