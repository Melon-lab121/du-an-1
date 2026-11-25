<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Payment;
use App\Services\OrderService;
use DomainException;
use Throwable;

/**
 * Điều phối thanh toán: tạo lượt thanh toán (bảng payments) và xử lý kết quả trả về từ cổng.
 * Mỗi lần khách bấm "Thanh toán" là 1 bản ghi payments với txn_ref duy nhất => cho phép thanh toán lại.
 */
final class PaymentService
{
    public function __construct(
        private Database $db,
        private PaymentManager $gateways,
        private OrderService $orders,
        private Logger $logger,
    ) {
    }

    /**
     * Bắt đầu thanh toán cho đơn. Trả về form cần gửi sang cổng (cổng online) hoặc null (COD...).
     *
     * @param array<string, mixed> $order
     * @return array{action: string, fields: array<string, string>}|null
     */
    public function start(array $order, ?string $method = null): ?array
    {
        $gateway = $this->gateways->get((string) $order['payment_method']);
        if (!$gateway->isOnline()) {
            return null;
        }
        if (!$gateway->isEnabled()) {
            throw new DomainException('Cổng thanh toán ' . $gateway->name() . ' đang tạm tắt.');
        }
        if ($order['payment_status'] === 'paid') {
            throw new DomainException('Đơn hàng đã được thanh toán.');
        }
        if ($order['status'] === 'cancelled') {
            throw new DomainException('Đơn hàng đã bị huỷ.');
        }

        $txnRef = $this->generateTxnRef();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('payments', [
            'order_id' => (int) $order['id'],
            'gateway' => $gateway->code(),
            'txn_ref' => $txnRef,
            'amount' => (int) $order['total'],
            'status' => Payment::STATUS_PENDING,
            'gateway_create_date' => date('YmdHis'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $gateway->createCheckout([
            'txn_ref' => $txnRef,
            'amount' => (int) $order['total'],
            'order_info' => 'Thanh toan don hang ' . $order['code'],
            'method' => $method,
        ]);
    }

    /**
     * Xử lý IPN của SePay (POST JSON). Idempotent: SePay gửi lặp vẫn an toàn.
     * SePay chỉ yêu cầu HTTP 200 khi đã nhận; các mã 401/404/422/500 là quy ước của Base để dễ debug.
     *
     * @param string|null $secretKey giá trị header X-Secret-Key
     * @param array<string, mixed> $body JSON SePay gửi
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handleSepayIpn(?string $secretKey, array $body): array
    {
        try {
            /** @var SepayGateway $sepay */
            $sepay = $this->gateways->get('sepay');

            if (!$sepay->verifyIpnKey($secretKey)) {                                    // 1. khoá IPN
                $this->logger->warning('SePay: sai khoá IPN', ['body' => $body], 'payment');
                return ['status' => 401, 'body' => ['error' => 'Unauthorized']];
            }

            $type = (string) ($body['notification_type'] ?? '');
            if ($type !== 'ORDER_PAID') {
                // TRANSACTION_VOID (huỷ giao dịch thẻ)...: chỉ ghi log để admin xử lý tay
                $this->logger->info('SePay: nhận ' . $type . ', không tự cập nhật', ['body' => $body], 'payment');
                return ['status' => 200, 'body' => ['success' => true]];
            }

            $order = is_array($body['order'] ?? null) ? $body['order'] : [];
            $payment = Payment::findByTxnRef('sepay', (string) ($order['order_invoice_number'] ?? ''));
            if ($payment === null) {                                                     // 2. tồn tại
                return ['status' => 404, 'body' => ['error' => 'Order not found']];
            }

            $paid = SepayGateway::parseAmount($order['order_amount'] ?? null);
            if ($paid !== (int) $payment['amount']) {                                    // 3. số tiền
                $this->logger->warning('SePay: sai số tiền', ['txn_ref' => $payment['txn_ref'], 'body' => $body], 'payment');
                return ['status' => 422, 'body' => ['error' => 'Invalid amount']];
            }

            $transaction = is_array($body['transaction'] ?? null) ? $body['transaction'] : [];
            $this->markSuccess($payment, [                                                // 4 + 5. chống trùng
                'transaction_no' => $transaction['transaction_id'] ?? null,
                'bank_code' => $transaction['payment_method'] ?? null,
                'response_code' => $type,
                'raw' => $body,
            ], 'IPN');

            return ['status' => 200, 'body' => ['success' => true]];
        } catch (Throwable $e) {
            $this->logger->error('SePay: lỗi xử lý IPN ' . $e->getMessage(), ['body' => $body], 'payment');
            return ['status' => 500, 'body' => ['error' => 'Server error']];
        }
    }

    /**
     * Đối soát 1 lượt thanh toán SePay qua API chi tiết đơn, cập nhật nếu SePay xác nhận đã thu tiền.
     * Dùng cho nút "Đối soát" của admin và cho success_url khi SEPAY_RECONCILE_ON_RETURN=true.
     *
     * @param array<string, mixed> $payment
     * @return array{updated: bool, paid: bool, message: string, response: array<string, mixed>}
     */
    public function reconcileSepay(array $payment): array
    {
        /** @var SepayGateway $sepay */
        $sepay = $this->gateways->get('sepay');
        $response = $sepay->retrieveOrder((string) $payment['txn_ref']);
        $order = SepayGateway::orderFromResponse($response);

        $status = (string) ($order['order_status'] ?? '');
        $amountOk = SepayGateway::parseAmount($order['order_amount'] ?? null) === (int) $payment['amount'];
        $paid = $status === SepayGateway::PAID_ORDER_STATUS && $amountOk;

        $updated = false;
        if ($paid) {
            $updated = $this->markSuccess($payment, [
                'transaction_no' => $order['id'] ?? null,
                'bank_code' => null,
                'response_code' => $status,
                'raw' => $response,
            ], 'đối soát');
        }

        $message = match (true) {
            $status === '' => 'SePay không trả về trạng thái đơn (kiểm tra log payment-*.log).',
            $status === SepayGateway::PAID_ORDER_STATUS && !$amountOk => 'SePay báo đã thu tiền nhưng số tiền không khớp, không cập nhật.',
            default => 'Trạng thái đơn trên SePay: ' . $status . ($updated ? ' — đã cập nhật đơn.' : '.'),
        };
        $this->logger->info('SePay: đối soát', ['txn_ref' => $payment['txn_ref'], 'response' => $response], 'payment');

        return ['updated' => $updated, 'paid' => $paid, 'message' => $message, 'response' => $response];
    }

    /**
     * Ghi nhận thanh toán thành công. UPDATE có điều kiện status = pending đảm bảo chỉ 1 request
     * (IPN hoặc đối soát) thắng, dù chúng đến cùng lúc.
     *
     * Cập nhật payments + cập nhật đơn hàng nằm chung 1 TRANSACTION: nếu cập nhật đơn lỗi thì payments
     * cũng rollback về 'pending', nên IPN gửi lại / nút "Đối soát" vẫn xử lý được (không bị kẹt "đã trả tiền
     * nhưng đơn vẫn chờ").
     *
     * @param array<string, mixed> $payment
     * @param array{transaction_no: mixed, bank_code: mixed, response_code: string, raw: array<string, mixed>} $data
     */
    private function markSuccess(array $payment, array $data, string $source): bool
    {
        $done = $this->db->transaction(function (Database $db) use ($payment, $data): bool {
            $now = date('Y-m-d H:i:s');
            $updated = $db->update('payments', [
                'status' => Payment::STATUS_SUCCESS,
                'transaction_no' => $data['transaction_no'] !== null ? (string) $data['transaction_no'] : null,
                'bank_code' => $data['bank_code'] !== null ? (string) $data['bank_code'] : null,
                'response_code' => $data['response_code'],
                'raw_data' => json_encode($data['raw'], JSON_UNESCAPED_UNICODE),
                'paid_at' => $now,
                'updated_at' => $now,
            ], 'id = ? AND status = ?', [(int) $payment['id'], Payment::STATUS_PENDING]);

            if ($updated === 0) {
                return false; // đã xử lý trước đó
            }

            if (!$this->orders->markPaid((int) $payment['order_id'])) {
                // Đơn đã được thanh toán bởi lượt khác => khách trả 2 lần, cần hoàn tiền thủ công
                $this->logger->warning('SePay: đơn đã thanh toán trước đó, cần hoàn tiền', ['txn_ref' => $payment['txn_ref']], 'payment');
            }
            return true;
        });

        if ($done) {
            $this->logger->info('SePay: đã xử lý (' . $source . ')', ['txn_ref' => $payment['txn_ref']], 'payment');
        }
        return $done;
    }

    /** Mã tham chiếu duy nhất gửi sang cổng (order_invoice_number, chỉ chữ + số). */
    private function generateTxnRef(): string
    {
        do {
            $ref = date('ymdHis') . random_int(1000, 9999);
        } while ($this->db->table('payments')->where('txn_ref', $ref)->exists());
        return $ref;
    }
}
