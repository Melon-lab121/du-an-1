<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Events;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use DomainException;
use InvalidArgumentException;

/**
 * Nghiệp vụ đơn hàng. Event phát ra (module có thể lắng nghe):
 *   order.created         ['order' => array, 'items' => array]
 *   order.paid            ['order' => array]
 *   order.payment_failed  ['order' => array]
 *   order.status_changed  ['order' => array, 'from' => string, 'to' => string]
 */
final class OrderService
{
    public function __construct(
        private Database $db,
        private Events $events,
        private Config $config,
        private VariantService $variants,
    ) {
    }

    /**
     * Tạo đơn: khoá tồn kho bằng UPDATE có điều kiện, tính tiền từ giá trong DB, tất cả trong 1 transaction.
     *
     * @param array{name: string, email: string, phone: string, address: string, note?: ?string} $customer
     * @param array<int|string, int> $items [khoá dòng giỏ => quantity], khoá = "product_id" hoặc "product_id:variant_id"
     *                                      (xem CartService::key())
     * @return array<string, mixed> đơn hàng vừa tạo
     */
    public function place(array $customer, array $items, string $paymentMethod, ?int $userId = null): array
    {
        $items = array_filter($items, fn (int $qty): bool => $qty > 0);
        if ($items === []) {
            throw new DomainException('Giỏ hàng đang trống.');
        }

        [$order, $lines] = $this->db->transaction(function (Database $db) use ($customer, $items, $paymentMethod, $userId): array {
            $lines = [];
            foreach ($items as $key => $qty) {
                [$productId, $variantId] = CartService::parseKey($key);
                // Product::active() đã lọc deleted_at IS NULL: sản phẩm vừa bị xoá mềm không đặt được
                $product = Product::active()->where('id', $productId)->first();
                if ($product === null) {
                    throw new DomainException('Có sản phẩm trong giỏ đã ngừng bán, vui lòng kiểm tra lại giỏ hàng.');
                }

                if ((int) $product['has_variants'] || $variantId !== null) {
                    $lines[] = $this->takeVariant($db, $product, $variantId, $qty);
                    continue;
                }

                // Trừ kho nguyên tử: chỉ thành công khi còn đủ hàng (an toàn khi nhiều người mua cùng lúc)
                $affected = $db->query(
                    'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ? AND deleted_at IS NULL',
                    [$qty, $productId, $qty],
                )->rowCount();
                if ($affected === 0) {
                    throw new DomainException(sprintf('Sản phẩm "%s" không đủ hàng (còn %d).', $product['name'], (int) $product['stock']));
                }

                $price = PriceCalculator::unitPrice($product);
                $lines[] = [
                    'product_id' => (int) $product['id'],
                    'variant_id' => null,
                    'product_name' => (string) $product['name'],
                    'variant_label' => null,
                    'sku' => $product['sku'],
                    'price' => $price,
                    'quantity' => $qty,
                    'total' => $price * $qty,
                ];
            }

            $subtotal = array_sum(array_column($lines, 'total'));
            $discount = (int) $this->events->filter('order.discount', 0, ['lines' => $lines, 'subtotal' => $subtotal, 'customer' => $customer, 'userId' => $userId]);
            $voucherCode = (string) $this->events->filter('order.voucher_code', '', ['customer' => $customer, 'userId' => $userId]);
            $totals = PriceCalculator::totals(
                $lines,
                (int) $this->config->get('shop.shipping_fee', 0),
                (int) $this->config->get('shop.free_shipping_threshold', 0),
                $discount,
            );

            $now = date('Y-m-d H:i:s');
            $orderId = $db->insert('orders', [
                'code' => $this->generateCode(),
                'user_id' => $userId,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'shipping_address' => $customer['address'],
                'note' => $customer['note'] ?? null,
                'subtotal' => $totals['subtotal'],
                'shipping_fee' => $totals['shipping_fee'],
                'discount' => $totals['discount'],
                'voucher_code' => $voucherCode !== '' ? $voucherCode : null,
                'total' => $totals['total'],
                'payment_method' => $paymentMethod,
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                $db->insert('order_items', $line + ['order_id' => $orderId]);
            }

            $this->recordStatus($db, $orderId, null, 'pending', null, 'Khách đặt hàng');

            return [$db->table('orders')->find($orderId), $lines];
        });

        $this->events->dispatch('order.created', ['order' => $order, 'items' => $lines]);
        return $order;
    }

    /**
     * Đánh dấu đã thanh toán (idempotent). @return bool true nếu trạng thái thực sự thay đổi
     *
     * Toàn bộ chạy trong 1 transaction (dùng chung với transaction của PaymentService nếu có):
     * lỗi giữa chừng thì rollback hết, IPN gửi lại sẽ xử lý được.
     * Nếu đơn đã bị huỷ trước khi tiền về: không sống lại đơn, chỉ đánh dấu 'refund_needed' để admin hoàn tiền.
     */
    public function markPaid(int $orderId): bool
    {
        $result = $this->db->transaction(function (Database $db) use ($orderId): ?array {
            $order = $db->table('orders')->find($orderId);
            if ($order === null || !in_array($order['payment_status'], ['unpaid', 'failed'], true)) {
                return null; // đã thanh toán / đang chờ hoàn tiền / không có đơn
            }

            $cancelled = $order['status'] === 'cancelled';
            $now = date('Y-m-d H:i:s');
            $updated = $db->update(
                'orders',
                [
                    'payment_status' => $cancelled ? 'refund_needed' : 'paid',
                    'paid_at' => $now,
                    'updated_at' => $now,
                ],
                'id = ? AND payment_status = ?',
                [$orderId, $order['payment_status']],
            );
            if ($updated === 0) {
                return null; // request khác đã xử lý trước
            }

            $confirmed = false;
            if ($cancelled) {
                $this->recordStatus($db, $orderId, 'cancelled', 'cancelled', null, 'Nhận thanh toán sau khi đơn đã huỷ: cần hoàn tiền cho khách');
            } elseif ($order['status'] === 'pending') {
                // Đơn online đã thanh toán => tự xác nhận
                $db->update('orders', ['status' => 'confirmed'], 'id = ? AND status = ?', [$orderId, 'pending']);
                $this->recordStatus($db, $orderId, 'pending', 'confirmed', null, 'Tự động xác nhận sau khi thanh toán thành công');
                $confirmed = true;
            }

            return ['cancelled' => $cancelled, 'confirmed' => $confirmed];
        });

        if ($result === null) {
            return false;
        }

        if ($result['confirmed']) {
            $this->events->dispatch('order.status_changed', ['order' => Order::find($orderId), 'from' => 'pending', 'to' => 'confirmed']);
        }
        if (!$result['cancelled']) {
            $this->events->dispatch('order.paid', ['order' => Order::find($orderId)]);
        }
        return true;
    }

    public function markPaymentFailed(int $orderId): void
    {
        $changed = $this->db->update(
            'orders',
            ['payment_status' => 'failed', 'updated_at' => date('Y-m-d H:i:s')],
            'id = ? AND payment_status = ?',
            [$orderId, 'unpaid'],
        ) > 0;

        if ($changed) {
            $this->events->dispatch('order.payment_failed', ['order' => Order::find($orderId)]);
        }
    }

    /**
     * Đổi trạng thái đơn theo đúng quy trình (xem Order::TRANSITIONS), ghi lịch sử.
     * Huỷ đơn sẽ hoàn lại tồn kho; đơn đã thanh toán mà bị huỷ thì chuyển sang 'refund_needed'.
     *
     * @param int|null $changedBy id người dùng (admin) thực hiện; null = hệ thống
     */
    public function updateStatus(int $orderId, string $status, ?int $changedBy = null, ?string $note = null): void
    {
        if (!array_key_exists($status, Order::STATUSES)) {
            throw new InvalidArgumentException('Trạng thái không hợp lệ.');
        }

        $this->db->transaction(function (Database $db) use ($orderId, $status, $changedBy, $note): void {
            $order = $db->table('orders')->find($orderId) ?? throw new DomainException('Không tìm thấy đơn hàng.');
            $from = (string) $order['status'];
            if ($from === $status) {
                return;
            }
            if ($from === 'cancelled') {
                throw new DomainException('Đơn đã huỷ, không thể đổi trạng thái.');
            }
            $attempts = Order::failedAttempts($orderId, $from);
            if (!Order::canTransition($from, $status, $attempts)) {
                if ($status === 'cancelled' && in_array($from, Order::DELIVERY_STAGES, true)) {
                    throw new DomainException(sprintf(
                        'Đơn đang vận chuyển chỉ được huỷ sau khi lấy/giao hàng thất bại đủ %d lần (hiện có %d lần).',
                        Order::MAX_DELIVERY_ATTEMPTS,
                        $attempts,
                    ));
                }
                throw new DomainException(sprintf(
                    'Không thể chuyển đơn từ "%s" sang "%s".',
                    Order::statusLabel($from),
                    Order::statusLabel($status),
                ));
            }

            $changes = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
            if ($status === 'cancelled' && $order['payment_status'] === 'paid') {
                $changes['payment_status'] = 'refund_needed';
            }
            // COD: giao thành công ("Đã giao") thì coi như đã thu tiền mặt (để doanh thu tính đủ đơn COD)
            $codCollected = $status === 'delivered'
                && $order['payment_method'] === 'cod'
                && $order['payment_status'] === 'unpaid';
            if ($codCollected) {
                $changes['payment_status'] = 'paid';
                $changes['paid_at'] = $changes['updated_at'];
            }

            // Điều kiện status = $from: nếu admin khác vừa đổi trước thì không ghi đè
            $updated = $db->update('orders', $changes, 'id = ? AND status = ?', [$orderId, $from]);
            if ($updated === 0) {
                throw new DomainException('Đơn vừa được cập nhật bởi người khác, vui lòng tải lại trang.');
            }
            $this->recordStatus($db, $orderId, $from, $status, $changedBy, $note);

            if ($status === 'cancelled') {
                // Hoàn kho cả khi sản phẩm/biến thể đang nằm thùng rác (khôi phục thì số tồn vẫn đúng)
                foreach (Order::items($orderId) as $item) {
                    if ($item['variant_id'] !== null) {
                        $db->query('UPDATE product_variants SET stock = stock + ? WHERE id = ?', [(int) $item['quantity'], (int) $item['variant_id']]);
                        if ($item['product_id'] !== null) {
                            $this->variants->refreshSummary((int) $item['product_id']);
                        }
                    } elseif ($item['product_id'] !== null) {
                        $db->query('UPDATE products SET stock = stock + ? WHERE id = ?', [(int) $item['quantity'], (int) $item['product_id']]);
                    }
                }
            }

            $this->events->dispatch('order.status_changed', ['order' => Order::find($orderId), 'from' => $from, 'to' => $status]);
            if ($codCollected) {
                $this->events->dispatch('order.paid', ['order' => Order::find($orderId)]);
            }
        });
    }

    /**
     * Ghi nhận 1 lần lấy hàng (đơn ở 'awaiting_pickup') hoặc giao hàng (đơn ở 'shipping') thất bại.
     * Trạng thái đơn giữ nguyên; mỗi lần là 1 dòng lịch sử (from = to). Thất bại đủ Order::MAX_DELIVERY_ATTEMPTS
     * lần thì mới được huỷ đơn (xem Order::nextStatuses).
     *
     * @param int|null $changedBy id người dùng (admin) thực hiện
     * @return int số thứ tự lần thất bại vừa ghi (1, 2, 3)
     */
    public function recordDeliveryFailure(int $orderId, ?int $changedBy = null, ?string $reason = null): int
    {
        return $this->db->transaction(function (Database $db) use ($orderId, $changedBy, $reason): int {
            $order = $db->table('orders')->find($orderId) ?? throw new DomainException('Không tìm thấy đơn hàng.');
            $status = (string) $order['status'];
            if (!in_array($status, Order::DELIVERY_STAGES, true)) {
                throw new DomainException('Chỉ ghi nhận thất bại khi đơn đang chờ lấy hàng hoặc đang giao.');
            }

            $attempt = Order::failedAttempts($orderId, $status) + 1;
            if ($attempt > Order::MAX_DELIVERY_ATTEMPTS) {
                throw new DomainException(sprintf('Đã ghi nhận đủ %d lần thất bại, hãy huỷ đơn hoặc tiếp tục giao.', Order::MAX_DELIVERY_ATTEMPTS));
            }

            $note = sprintf('%s thất bại lần %d/%d', $status === 'awaiting_pickup' ? 'Lấy hàng' : 'Giao hàng', $attempt, Order::MAX_DELIVERY_ATTEMPTS);
            if ($reason !== null && $reason !== '') {
                $note .= ': ' . $reason;
            }
            $this->recordStatus($db, $orderId, $status, $status, $changedBy, mb_substr($note, 0, 255));
            return $attempt;
        });
    }

    /**
     * Tự hoàn thành các đơn 'delivered' đã quá $days ngày (tính từ lần chuyển sang 'delivered' gần nhất trong lịch sử)
     * mà khách chưa xác nhận đã nhận hàng. Chạy bằng `php cli.php orders:autocomplete`, đặt lịch chạy mỗi ngày.
     *
     * @param int|null $now mốc thời gian (Unix) để so sánh; mặc định là bây giờ (dùng cho test)
     * @return int số đơn đã được hoàn thành
     */
    public function autoCompleteDelivered(int $days = 7, ?int $now = null): int
    {
        $cutoff = date('Y-m-d H:i:s', ($now ?? time()) - $days * 86400);
        $rows = $this->db->select(
            "SELECT o.id FROM orders o
             WHERE o.status = 'delivered'
               AND (SELECT MAX(h.created_at) FROM order_status_histories h WHERE h.order_id = o.id AND h.to_status = 'delivered') <= ?
             ORDER BY o.id",
            [$cutoff],
        );

        $done = 0;
        foreach ($rows as $row) {
            try {
                $this->updateStatus(
                    (int) $row['id'],
                    'completed',
                    null,
                    sprintf('Tự động hoàn thành sau %d ngày khách không xác nhận đã nhận hàng', $days),
                );
                $done++;
            } catch (DomainException) {
                // Đơn vừa được người khác đổi trạng thái: bỏ qua, lần chạy sau sẽ xét lại
            }
        }
        return $done;
    }

    /**
     * Admin xác nhận ĐÃ HOÀN TIỀN thủ công cho đơn đang ở 'refund_needed' (đơn đã huỷ mà khách đã trả tiền).
     * Chỉ đổi payment_status sang 'refunded' và ghi lịch sử; trạng thái đơn giữ nguyên ('cancelled').
     *
     * @param int|null $changedBy id người dùng (admin) thực hiện
     */
    public function markRefunded(int $orderId, ?int $changedBy = null, ?string $note = null): void
    {
        $this->db->transaction(function (Database $db) use ($orderId, $changedBy, $note): void {
            $order = $db->table('orders')->find($orderId) ?? throw new DomainException('Không tìm thấy đơn hàng.');

            $updated = $db->update(
                'orders',
                ['payment_status' => 'refunded', 'updated_at' => date('Y-m-d H:i:s')],
                'id = ? AND payment_status = ?',
                [$orderId, 'refund_needed'],
            );
            if ($updated === 0) {
                throw new DomainException('Đơn này không ở trạng thái "Cần hoàn tiền".');
            }

            $status = (string) $order['status'];
            $this->recordStatus($db, $orderId, $status, $status, $changedBy, $note ?: 'Đã hoàn tiền cho khách');
        });
    }

    /** Ghi 1 dòng lịch sử đổi trạng thái. $changedBy null = hệ thống tự đổi. */
    private function recordStatus(Database $db, int $orderId, ?string $from, string $to, ?int $changedBy = null, ?string $note = null): void
    {
        $db->insert('order_status_histories', [
            'order_id' => $orderId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'changed_by' => $changedBy,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Trừ kho 1 biến thể và trả dòng đơn hàng (lưu nhãn biến thể tại thời điểm mua).
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function takeVariant(Database $db, array $product, ?int $variantId, int $qty): array
    {
        if ($variantId === null || !(int) $product['has_variants']) {
            throw new DomainException(sprintf('Vui lòng chọn lại phân loại cho "%s".', $product['name']));
        }
        $variant = ProductVariant::query()->where('id', $variantId)->where('product_id', (int) $product['id'])->first();
        if ($variant === null) {
            throw new DomainException(sprintf('Phân loại của "%s" trong giỏ đã ngừng bán.', $product['name']));
        }

        $affected = $db->query(
            'UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ? AND deleted_at IS NULL',
            [$qty, $variantId, $qty],
        )->rowCount();
        if ($affected === 0) {
            throw new DomainException(sprintf('"%s – %s" không đủ hàng (còn %d).', $product['name'], $variant['title'], (int) $variant['stock']));
        }
        $this->variants->refreshSummary((int) $product['id']); // products.stock = tổng tồn các biến thể

        $price = PriceCalculator::unitPrice($variant);
        return [
            'product_id' => (int) $product['id'],
            'variant_id' => $variantId,
            'product_name' => (string) $product['name'],
            'variant_label' => (string) $variant['title'],
            'sku' => $variant['sku'] ?? $product['sku'],
            'price' => $price,
            'quantity' => $qty,
            'total' => $price * $qty,
        ];
    }

    private function generateCode(): string
    {
        do {
            $code = 'DH' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->db->table('orders')->where('code', $code)->exists());
        return $code;
    }
}
