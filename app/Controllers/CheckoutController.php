<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
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
use App\Services\VoucherService;
use DomainException;
use RuntimeException;

final class CheckoutController extends Controller
{
    private const SESSION_SELECTED_KEY = 'checkout_selected_keys';
    private const HIGH_VALUE_THRESHOLD = 10000000;
    private const HIGH_VALUE_MAX_QTY = 3;

    public function __construct(
        private CartService $cart,
        private OrderService $orders,
        private PaymentManager $gateways,
        private PaymentService $payments,
        private VoucherService $vouchers,
        private Auth $auth,
        private Session $session,
        private Logger $logger,
        private Database $db,
    ) {
    }

    public function index(Request $request): Response
    {
        $allLines = $this->cart->lines();
        if ($allLines === []) {
            $this->session->forget(self::SESSION_SELECTED_KEY);
            $this->flash('error', 'Giỏ hàng đang trống.');
            return $this->redirect('/cart');
        }

        $selectedKeys = $this->resolveSelectedKeys($request, $allLines);
        if ($selectedKeys === []) {
            $this->flash('error', 'Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
            return $this->redirect('/cart');
        }

        $this->session->set(self::SESSION_SELECTED_KEY, $selectedKeys);
        $lines = $this->filterLinesByKeys($allLines, $selectedKeys);
        $user = $this->auth->user();
        $userId = $this->auth->id();
        $email = $user['email'] ?? null;
        $phone = $user['phone'] ?? null;

        return $this->view('checkout/index', [
            'lines' => $lines,
            'selectedKeys' => $selectedKeys,
            'totals' => $this->cart->totals($lines),
            'gateways' => $this->gateways->enabled(),
            'sepayMethods' => SepayGateway::PAYMENT_METHODS,
            'user' => $user,
            'availableVouchers' => $this->vouchers->availableForCart($lines, $userId, $email, $phone),
            'checkoutVoucher' => $this->vouchers->applied($lines, $userId, $email, $phone),
        ]);
    }

    public function store(Request $request): Response
    {
        $allLines = $this->cart->lines();
        if ($allLines === []) {
            $this->session->forget(self::SESSION_SELECTED_KEY);
            $this->flash('error', 'Giỏ hàng đang trống.');
            return $this->redirect('/cart');
        }

        $selectedKeys = $this->resolveSelectedKeys($request, $allLines);
        if ($selectedKeys === []) {
            $this->flash('error', 'Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
            return $this->redirect('/cart');
        }

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

        // Lọc đúng các dòng được chọn và kiểm tra giới hạn 3 cái/đơn cho sản phẩm >= 10 triệu
        $selectedLines = $this->filterLinesByKeys($allLines, $selectedKeys);
        if ($selectedLines === []) {
            $this->flash('error', 'Các sản phẩm đã chọn không còn khả dụng.');
            return $this->redirect('/cart');
        }

        foreach ($selectedLines as $line) {
            $unitPrice = (int) ($line['price'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            if ($unitPrice >= self::HIGH_VALUE_THRESHOLD && $qty > self::HIGH_VALUE_MAX_QTY) {
                $this->flash(
                    'error',
                    sprintf(
                        'Sản phẩm "%s" có giá từ 10.000.000 ₫ trở lên, mỗi đơn chỉ được mua tối đa %d cái.',
                        (string) ($line['name'] ?? ''),
                        self::HIGH_VALUE_MAX_QTY,
                    ),
                );
                return $this->redirect('/cart');
            }
        }

        // Lưu thông tin liên hệ khách vãng lai để CSKH liên hệ tạo đơn sau
        if ($this->auth->id() === null) {
            $this->db->insert('guest_inquiries', [
                'phone' => trim((string) $data['phone']),
                'name' => trim((string) $data['name']),
                'address' => trim((string) $data['address']),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Đồng bộ voucher khách vừa chọn từ select ở trang đặt hàng (kể cả khi chưa kịp bấm nút Áp dụng)
        if (array_key_exists('voucher_code_input', $request->all())) {
            $selectedCode = strtoupper(trim((string) $request->input('voucher_code_input', '')));
            if ($selectedCode === '') {
                $this->vouchers->remove();
            } else {
                $vRes = $this->vouchers->apply(
                    $selectedCode,
                    $selectedLines,
                    $this->auth->id(),
                    $data['email'],
                    $data['phone'],
                );
                if (!$vRes['success']) {
                    $this->flash('error', $vRes['message']);
                    $this->session->flash('_old', $request->all());
                    return $this->redirect('/checkout');
                }
            }
        }

        $totals = $this->cart->totals($selectedLines);
        $rawCart = $this->cart->raw();
        $selectedRawItems = [];
        foreach ($selectedLines as $line) {
            $key = (string) $line['key'];
            if (isset($rawCart[$key])) {
                $selectedRawItems[$key] = (int) $rawCart[$key];
            }
        }

        try {
            $order = $this->orders->place(
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'note' => $data['note'] ?? null,
                    'totals' => $totals,
                ],
                $selectedRawItems,
                $data['payment_method'],
                $this->auth->id(),
            );
        } catch (DomainException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/cart');
        }

        // Chỉ xoá những món đã thanh toán khỏi giỏ; nếu thanh toán hết giỏ thì clear()
        $remainingKeys = array_diff(array_keys($rawCart), array_keys($selectedRawItems));
        if ($remainingKeys === []) {
            $this->cart->clear();
        } else {
            foreach (array_keys($selectedRawItems) as $paidKey) {
                $this->cart->remove($paidKey);
            }
        }
        $this->session->forget(self::SESSION_SELECTED_KEY);
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

    /**
     * Lấy danh sách key hợp lệ đang có trong giỏ hàng từ request (?selected=... hoặc selected_keys[]) hoặc session.
     * Nếu không truyền gì thì mặc định chọn toàn bộ giỏ hàng.
     *
     * @param array<int, array<string, mixed>> $allLines
     * @return string[]
     */
    private function resolveSelectedKeys(Request $request, array $allLines): array
    {
        $validKeys = array_map(static fn (array $l): string => (string) $l['key'], $allLines);
        $validSet = array_flip($validKeys);

        $rawSelection = null;

        $postKeys = $request->input('selected_keys');
        if (is_array($postKeys)) {
            $rawSelection = $postKeys;
        } elseif ($request->query('selected') !== null && (string) $request->query('selected') !== '') {
            $rawSelection = explode(',', (string) $request->query('selected'));
        } else {
            $sessionKeys = $this->session->get(self::SESSION_SELECTED_KEY);
            if (is_array($sessionKeys)) {
                $rawSelection = $sessionKeys;
            }
        }

        if ($rawSelection === null) {
            return $validKeys;
        }

        $result = [];
        foreach ($rawSelection as $item) {
            $k = trim((string) $item);
            if ($k !== '' && isset($validSet[$k])) {
                $result[$k] = true;
            }
        }

        return array_keys($result);
    }

    /**
     * @param array<int, array<string, mixed>> $allLines
     * @param string[] $selectedKeys
     * @return array<int, array<string, mixed>>
     */
    private function filterLinesByKeys(array $allLines, array $selectedKeys): array
    {
        $set = array_flip($selectedKeys);

        return array_values(array_filter(
            $allLines,
            static fn (array $line): bool => isset($set[(string) $line['key']]),
        ));
    }

    /** Khách vãng lai xem lại được đơn của mình trong phiên làm việc. */
    private function rememberGuestOrder(string $code): void
    {
        $codes = (array) $this->session->get('guest_orders', []);
        $codes[] = $code;
        $this->session->set('guest_orders', array_slice(array_unique($codes), -20));
    }
}