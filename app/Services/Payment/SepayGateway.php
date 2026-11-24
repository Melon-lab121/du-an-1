<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Closure;
use RuntimeException;

/**
 * Tích hợp SePay Payment Gateway (checkout v1).
 * Docs: https://developer.sepay.vn/en/cong-thanh-toan/bat-dau
 * Thuật toán ký chép đúng theo SDK chính thức (sepay/sepay-pg cho PHP, sepay-pg-node cho Node.js).
 *
 * Luồng:
 *   1. createCheckout()  -> trang của ta in form ẩn, trình duyệt POST sang /v1/checkout/init của SePay
 *   2. SePay gọi IPN URL (server-to-server, POST JSON + header X-Secret-Key) -> cập nhật DB (nguồn sự thật)
 *   3. SePay redirect khách về success_url / error_url / cancel_url -> chỉ để hiển thị, KHÔNG có chữ ký
 *   4. retrieveOrder() gọi API SePay để đối soát khi không nhận được IPN
 */
final class SepayGateway implements PaymentGatewayInterface
{
    public const CHECKOUT_URLS = [
        'sandbox' => 'https://pay-sandbox.sepay.vn/v1/checkout/init',
        'production' => 'https://pay.sepay.vn/v1/checkout/init',
    ];

    public const API_URLS = [
        'sandbox' => 'https://pgapi-sandbox.sepay.vn/v1',
        'production' => 'https://pgapi.sepay.vn/v1',
    ];

    /** Phương thức chọn trước (payment_method). Rỗng = để khách chọn trên trang SePay */
    public const PAYMENT_METHODS = [
        '' => 'Chọn trên trang SePay',
        'BANK_TRANSFER' => 'Chuyển khoản ngân hàng (quét VietQR)',
        'NAPAS_BANK_TRANSFER' => 'Quét QR Napas 24/7',
        'CARD' => 'Thẻ quốc tế / nội địa',
    ];

    /** Trạng thái đơn đã thu tiền. Đây là giá trị duy nhất tài liệu SePay công bố (ví dụ IPN ORDER_PAID). */
    public const PAID_ORDER_STATUS = 'CAPTURED';

    /**
     * Trường được ký, chép từ SignatureGenerator của SDK chính thức.
     * Chữ ký lấy các trường theo THỨ TỰ XUẤT HIỆN trong form, không phải thứ tự danh sách này.
     */
    private const SIGNED_FIELDS = [
        'merchant', 'env', 'operation', 'payment_method', 'order_amount', 'currency', 'order_invoice_number',
        'order_description', 'customer_id', 'agreement_id', 'agreement_name', 'agreement_type',
        'agreement_payment_frequency', 'agreement_amount_per_payment', 'success_url', 'error_url', 'cancel_url',
    ];

    /** @var Closure(string, string, array<string, string>, ?string): array{status: int, body: string} */
    private Closure $transport;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config, ?Closure $transport = null)
    {
        $this->transport = $transport ?? Closure::fromCallable([self::class, 'curlTransport']);
    }

    /** Bản sao dùng transport khác (test thay HTTP thật bằng hàm giả). */
    public function withTransport(Closure $transport): self
    {
        return new self($this->config, $transport);
    }

    public function code(): string
    {
        return 'sepay';
    }

    public function name(): string
    {
        return 'SePay';
    }

    public function description(): string
    {
        return 'Thanh toán qua SePay: quét VietQR chuyển khoản, QR Napas, thẻ.';
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return ($this->config['merchant_id'] ?? '') !== '' && ($this->config['secret_key'] ?? '') !== '';
    }

    public function isOnline(): bool
    {
        return true;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function env(): string
    {
        return ($this->config['env'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
    }

    public function checkoutUrl(): string
    {
        return self::CHECKOUT_URLS[$this->env()];
    }

    public function apiUrl(): string
    {
        return self::API_URLS[$this->env()];
    }

    public function createCheckout(array $payment): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('SePay chưa cấu hình SEPAY_MERCHANT_ID / SEPAY_SECRET_KEY trong .env');
        }

        $ref = rawurlencode($payment['txn_ref']);

        // Thứ tự giống prepareFormFields() của SDK PHP. Đổi thứ tự là chữ ký sai.
        $fields = [
            'merchant' => (string) $this->config['merchant_id'],
            'currency' => 'VND',
            'order_amount' => (string) (int) $payment['amount'], // số nguyên VND, KHÔNG nhân 100
            'operation' => 'PURCHASE',
            'order_description' => self::sanitizeDescription($payment['order_info']),
        ];
        if (!empty($payment['method'])) {
            $fields['payment_method'] = (string) $payment['method'];
        }
        $fields['order_invoice_number'] = $payment['txn_ref'];
        $fields['success_url'] = $this->config['success_url'] . '?ref=' . $ref;
        $fields['error_url'] = $this->config['error_url'] . '?ref=' . $ref;
        $fields['cancel_url'] = $this->config['cancel_url'] . '?ref=' . $ref;

        $fields['signature'] = $this->sign($fields);

        return ['action' => $this->checkoutUrl(), 'fields' => $fields];
    }

    /**
     * HMAC-SHA256 (base64) trên chuỗi "field=value" nối bằng dấu phẩy, giống SDK chính thức.
     *
     * @param array<string, string> $fields
     */
    public function sign(array $fields): string
    {
        $signed = [];
        foreach ($fields as $field => $value) {
            if (in_array($field, self::SIGNED_FIELDS, true)) {
                $signed[] = $field . '=' . $value;
            }
        }
        return base64_encode(hash_hmac('sha256', implode(',', $signed), (string) $this->config['secret_key'], true));
    }

    /** IPN hợp lệ khi header X-Secret-Key trùng khoá IPN ta đặt trên dashboard SePay. */
    public function verifyIpnKey(?string $given): bool
    {
        $expected = (string) ($this->config['ipn_secret'] ?? '');
        return $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }

    /**
     * Đối soát: hỏi SePay chi tiết đơn theo mã hoá đơn (GET /v1/order/detail/{invoice}, Basic Auth).
     *
     * @return array<string, mixed> JSON SePay trả về
     */
    public function retrieveOrder(string $invoiceNumber): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('SePay chưa cấu hình SEPAY_MERCHANT_ID / SEPAY_SECRET_KEY trong .env');
        }
        $url = $this->apiUrl() . '/order/detail/' . rawurlencode($invoiceNumber);
        $auth = base64_encode($this->config['merchant_id'] . ':' . $this->config['secret_key']);

        $response = ($this->transport)('GET', $url, [
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/json',
        ], null);

        $json = json_decode($response['body'], true);
        if ($response['status'] >= 400) {
            $detail = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? '') : '';
            throw new RuntimeException('Gọi API SePay thất bại: HTTP ' . $response['status'] . ($detail !== '' ? ' — ' . $detail : ''));
        }
        if (!is_array($json)) {
            throw new RuntimeException('Phản hồi API SePay không hợp lệ.');
        }
        return $json;
    }

    /**
     * Rút phần thông tin đơn từ phản hồi API. Tài liệu chưa công bố schema đầy đủ nên chấp nhận
     * cả dạng {"data": {...}} lẫn {"data": {"order": {...}}}.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function orderFromResponse(array $response): array
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        return is_array($data['order'] ?? null) ? $data['order'] : $data;
    }

    /**
     * SePay gửi số tiền dạng chuỗi ("50000.00"). Đổi sang số nguyên VND mà không qua float.
     * Trả null nếu có phần lẻ khác 0 hoặc không phải số.
     */
    public static function parseAmount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/^\s*(\d+)(?:\.0+)?\s*$/', $value, $m) !== 1) {
            return null;
        }
        return (int) $m[1];
    }

    /** Mô tả không dấu, không ký tự đặc biệt cho chắc chắn hiển thị đúng ở mọi ngân hàng. */
    public static function sanitizeDescription(string $text): string
    {
        $ascii = trim((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', str_ascii($text)));
        $ascii = (string) preg_replace('/\s+/', ' ', $ascii);
        return $ascii === '' ? 'Thanh toan don hang' : substr($ascii, 0, 255);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    private static function curlTransport(string $method, string $url, array $headers, ?string $body): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Cần bật extension curl trong php.ini để gọi API SePay.');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            throw new RuntimeException('Không kết nối được SePay: ' . $error);
        }
        return ['status' => $status, 'body' => (string) $result];
    }
}
