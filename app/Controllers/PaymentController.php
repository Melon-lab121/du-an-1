<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SepayGateway;
use RuntimeException;

final class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private PaymentManager $gateways,
        private Logger $logger,
    ) {
    }

    /**
     * IPN: SePay gọi server-to-server (POST JSON + header X-Secret-Key). Nơi cập nhật đơn chính thức.
     * Khai báo trên my.sepay.vn → Payment Gateway → Configuration → IPN: https://<domain-public>/payment/sepay/ipn
     */
    public function sepayIpn(Request $request): Response
    {
        $result = $this->payments->handleSepayIpn($request->header('X-Secret-Key'), $request->all());
        return $this->json($result['body'], $result['status']);
    }

    /**
     * success_url: SePay redirect trình duyệt về đây. KHÔNG có chữ ký => không tin URL,
     * trạng thái hiển thị luôn lấy từ DB (và từ API SePay nếu bật SEPAY_RECONCILE_ON_RETURN).
     */
    public function sepaySuccess(Request $request): Response
    {
        $payment = $this->findPayment($request);
        $note = null;

        if ($payment !== null && $payment['status'] === Payment::STATUS_PENDING
            && $this->sepay()->config('reconcile_on_return', false)) {
            // Dev/localhost: SePay không gọi được IPN vào máy => tự hỏi API SePay (server-to-server)
            try {
                $this->payments->reconcileSepay($payment);
            } catch (RuntimeException $e) {
                $this->logger->warning('SePay: đối soát tại success_url lỗi ' . $e->getMessage(), ['txn_ref' => $payment['txn_ref']], 'payment');
                $note = 'Chưa hỏi được SePay: ' . $e->getMessage();
            }
            $payment = Payment::find((int) $payment['id']);
        }

        return $this->result($payment, 'success', $note);
    }

    /** error_url: SePay báo thanh toán lỗi. Không đổi trạng thái: nếu tiền thực sự đã thu, IPN vẫn ghi nhận được. */
    public function sepayError(Request $request): Response
    {
        return $this->result($this->findPayment($request), 'error');
    }

    /** cancel_url: khách bấm huỷ trên trang SePay. Đơn vẫn chờ thanh toán, khách thanh toán lại được. */
    public function sepayCancel(Request $request): Response
    {
        return $this->result($this->findPayment($request), 'cancel');
    }

    /** @return array<string, mixed>|null */
    private function findPayment(Request $request): ?array
    {
        $ref = (string) $request->query('ref', '');
        return $ref === '' ? null : Payment::findByTxnRef('sepay', $ref);
    }

    /** @param array<string, mixed>|null $payment */
    private function result(?array $payment, string $redirect, ?string $note = null): Response
    {
        $order = $payment ? Order::find((int) $payment['order_id']) : null;
        $success = $payment !== null && $payment['status'] === Payment::STATUS_SUCCESS;

        $message = match (true) {
            $payment === null => 'Không tìm thấy giao dịch tương ứng.',
            $success => 'SePay đã xác nhận thanh toán.',
            $redirect === 'cancel' => 'Bạn đã huỷ thanh toán. Đơn hàng vẫn được giữ, bạn có thể thanh toán lại.',
            $redirect === 'error' => 'Thanh toán không thành công. Bạn có thể thử lại.',
            default => 'Đang chờ SePay xác nhận (IPN). Trạng thái đơn sẽ cập nhật sau ít phút.',
        };

        return $this->view('payment/result', [
            'success' => $success,
            'message' => $message,
            'note' => $note,
            'order' => $order,
            'payment' => $payment,
            'methods' => SepayGateway::PAYMENT_METHODS,
        ]);
    }

    private function sepay(): SepayGateway
    {
        /** @var SepayGateway $sepay */
        $sepay = $this->gateways->get('sepay');
        return $sepay;
    }
}
