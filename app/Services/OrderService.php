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
use Throwable;

/**
 * Nghiệp vụ đơn hàng. Event phát ra (module có thể lắng nghe):
 *   order.created         ['order' => array, 'items' => array]
 *   order.paid            ['order' => array]
 *   order.payment_failed  ['order' => array]
 *   order.status_changed  ['order' => array, 'from' => string, 'to' => string]
 */
final class OrderService
{
    public const HIGH_VALUE_THRESHOLD = 10000000;
    public const HIGH_VALUE_MAX_QTY = 3;

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
     * @return array<string, mixed> đơn hàng vừa tạo
     */
    public function place(array $customer, array $items, string $paymentMethod, ?int $userId = null): array
    {
        $items = array_filter($items, static fn (int $qty): bool => $qty > 0);
        if ($items === []) {
            throw new DomainException('Giỏ hàng đang trống.');
        }

        // Sắp xếp thứ tự khoá trước khi khoá kho để chống deadlock
        ksort($items);

        [$order, $lines] = $this->db->transaction(function (Database $db) use ($customer, $items, $paymentMethod, $userId): array {
            $lines = [];

            foreach ($items as $key => $qty) {
                [$productId, $variantId] = CartService::parseKey($key);
                $product = Product::active()->where('id', $productId)->first();
                if ($product === null) {
                    throw new DomainException('Có sản phẩm trong giỏ đã ngừng bán, vui lòng kiểm tra lại giỏ hàng.');
                }

                if ((int) ($product['has_variants'] ?? 0) || $variantId !== null) {
                    $lines[] = $this->takeVariant($db, $product, $variantId, $qty);
                    continue;
                }

                $price = (int) PriceCalculator::unitPrice($product);
                if ($price >= self::HIGH_VALUE_THRESHOLD && $qty > self::HIGH_VALUE_MAX_QTY) {
                    throw new DomainException(sprintf(
                        'Sản phẩm "%s" có giá từ 10.000.000 ₫ trở lên chỉ được mua tối đa %d cái mỗi đơn.',
                        (string) $product['name'],
                        self::HIGH_VALUE_MAX_QTY,
                    ));
                }

                $affected = $db->query(
                    'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ? AND deleted_at IS NULL',
                    [$qty, $productId, $qty],
                )->rowCount();

                if ($affected === 0) {
                    throw new DomainException(sprintf(
                        'Sản phẩm "%s" không đủ hàng (còn %d).',
                        (string) $product['name'],
                        (int) ($product['stock'] ?? 0),
                    ));
                }

                $lines[] = [
                    'product_id' => (int) $product['id'],
                    'variant_id' => null,
                    'product_name' => (string) $product['name'],
                    'variant_label' => null,
                    'sku' => $product['sku'] ?? null,
                    'price' => $price,
                    'quantity' => $qty,
                    'total' => $price * $qty,
                ];
            }

            $subtotal = (int) array_sum(array_column($lines, 'total'));
            $discount = (int) $this->events->filter('order.discount', 0, [
                'lines' => $lines,
                'subtotal' => $subtotal,
                'customer' => $customer,
                'userId' => $userId,
            ]);
            $voucherCode = (string) $this->events->filter('order.voucher_code', '', [
                'customer' => $customer,
                'userId' => $userId,
            ]);

            $totals = PriceCalculator::totals(
                $lines,
                (int) $this->config->get('shop.shipping_fee', 0),
                (int) $this->config->get('shop.free_shipping_threshold', 0),
                $discount,
            );

            $now = date('Y-m-d H:i:s');
            $initialStatus = strtolower($paymentMethod) !== 'cod' ? 'confirmed' : 'pending';

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
                'status' => $initialStatus,
                'shipping_attempts' => 0,
                'refund_status' => 'none',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($lines as $line) {
                $db->insert('order_items', $line + ['order_id' => $orderId]);
            }

            return [$db->table('orders')->find($orderId), $lines];
        });

        try {
            $this->events->dispatch('order.created', ['order' => $order, 'items' => $lines]);
        } catch (Throwable) {
            // Không để lỗi từ listener làm hỏng đơn đã commit
        }

        return $order;
    }

    /** Đánh dấu đã thanh toán (idempotent). @return bool true nếu trạng thái thực sự thay đổi */
    public function markPaid(int $orderId): bool
    {
        $order = Order::find($orderId);
        if ($order && (string) ($order['status'] ?? '') === 'cancelled') {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $changed = $this->db->update(
            'orders',
            ['payment_status' => 'paid', 'paid_at' => $now, 'updated_at' => $now],
            'id = ? AND payment_status <> ? AND status <> ?',
            [$orderId, 'paid', 'cancelled'],
        ) > 0;

        if ($changed) {
            $this->db->update('orders', ['status' => 'confirmed'], 'id = ? AND status = ?', [$orderId, 'pending']);
            $this->events->dispatch('order.paid', ['order' => Order::find($orderId)]);
        }

        return $changed;
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
     * Đổi trạng thái đơn theo luồng (phía quản trị / hệ thống):
     * - Không cho tự chuyển sang 'completed' (chỉ khách xác nhận nhận hàng hoặc tự động sau 2 tuần không trả hàng).
     * - Không quay lại trạng thái trước đó.
     * - Đơn online bắt đầu từ 'confirmed'.
     * - Hủy đơn chỉ được phép trước khi giao tới đơn vị vận chuyển; hủy đơn sẽ hoàn lại tồn kho.
     * - Giao đơn vị vận chuyển tối đa 3 lần.
     */
    public function updateStatus(int $orderId, string $status, bool $carrierFailed = false): void
    {
        if (!array_key_exists($status, Order::STATUSES)) {
            throw new InvalidArgumentException('Trạng thái không hợp lệ.');
        }

        $this->db->transaction(function (Database $db) use ($orderId, $status, $carrierFailed): void {
            $order = $db->table('orders')->find($orderId) ?? throw new DomainException('Không tìm thấy đơn hàng.');
            $from = (string) $order['status'];
            $attempts = (int) ($order['shipping_attempts'] ?? 0);

            if ($status === 'completed') {
                throw new DomainException('Trạng thái Hoàn thành chỉ có khi khách xác nhận đã nhận hàng hoặc tự động sau 2 tuần không trả hàng.');
            }

            if (in_array($from, ['cancelled', 'completed'], true)) {
                throw new DomainException('Đơn hàng đã kết thúc, không thể thay đổi trạng thái.');
            }

            if ($status === 'pending') {
                throw new DomainException('Không thể chuyển về trạng thái Chờ xác nhận.');
            }

            if (Order::isOnlineMethod($order) && $from === 'pending' && $status !== 'confirmed' && $status !== 'cancelled') {
                throw new DomainException('Đơn thanh toán online bắt đầu từ trạng thái Đã xác nhận đơn hàng.');
            }

            $fromRank = Order::LINEAR_ORDER[$from] ?? 0;
            $toRank = Order::LINEAR_ORDER[$status] ?? 0;
            if ($fromRank > 0 && $toRank > 0 && $toRank < $fromRank) {
                throw new DomainException('Không thể quay lại trạng thái trước đó.');
            }

            if ($status === 'cancelled' && $fromRank >= Order::LINEAR_ORDER['shipping']) {
                throw new DomainException('Chỉ có thể hủy đơn trước khi đưa hàng tới đơn vị vận chuyển.');
            }

            $now = date('Y-m-d H:i:s');
            $updateData = ['updated_at' => $now];
            $targetStatus = $status;

            // Xử lý giao tới đơn vị vận chuyển (tối đa 3 lần)
            if ($status === 'shipping' || ($from === 'shipping' && $carrierFailed)) {
                if ($attempts >= Order::MAX_SHIPPING_ATTEMPTS) {
                    throw new DomainException('Đã đạt tối đa ' . Order::MAX_SHIPPING_ATTEMPTS . ' lần giao tới đơn vị vận chuyển.');
                }

                $nextAttempt = $attempts + 1;
                $updateData['shipping_attempts'] = $nextAttempt;
                $updateData['shipped_at'] = $order['shipped_at'] ?? $now;

                if ($carrierFailed && $nextAttempt >= Order::MAX_SHIPPING_ATTEMPTS) {
                    $targetStatus = 'delivery_failed';
                } else {
                    $targetStatus = 'shipping';
                }
            }

            $updateData['status'] = $targetStatus;

            if ($targetStatus === 'delivered') {
                $updateData['delivered_at'] = $now;
            }

            if (in_array($targetStatus, ['cancelled', 'delivery_failed', 'returned'], true)) {
                if ($targetStatus === 'cancelled') {
                    $updateData['cancelled_at'] = $now;
                }
                if (Order::isOnlineMethod($order) && (($order['payment_status'] ?? '') === 'paid')) {
                    $updateData['refund_status'] = 'pending_refund';
                }
            }

            if ($from === $targetStatus && ($updateData['shipping_attempts'] ?? null) === null) {
                return;
            }

            $db->update('orders', $updateData, 'id = ?', [$orderId]);

            if ($targetStatus === 'cancelled') {
                foreach (Order::items($orderId) as $item) {
                    if ($item['variant_id'] !== null) {
                        $db->query(
                            'UPDATE product_variants SET stock = stock + ? WHERE id = ?',
                            [(int) $item['quantity'], (int) $item['variant_id']],
                        );
                        if ($item['product_id'] !== null) {
                            $this->variants->refreshSummary((int) $item['product_id']);
                        }
                    } elseif ($item['product_id'] !== null) {
                        $db->query(
                            'UPDATE products SET stock = stock + ? WHERE id = ?',
                            [(int) $item['quantity'], (int) $item['product_id']],
                        );
                    }
                }
            }

            if ($from !== $targetStatus) {
                $this->events->dispatch('order.status_changed', [
                    'order' => Order::find($orderId),
                    'from' => $from,
                    'to' => $targetStatus,
                ]);
            }
        });
    }

    /**
     * Trừ kho 1 biến thể và trả dòng đơn hàng (lưu nhãn biến thể tại thời điểm mua).
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function takeVariant(Database $db, array $product, ?int $variantId, int $qty): array
    {
        if ($variantId === null || !(int) ($product['has_variants'] ?? 0)) {
            throw new DomainException(sprintf('Vui lòng chọn lại phân loại cho "%s".', (string) $product['name']));
        }

        $variant = ProductVariant::query()
            ->where('id', $variantId)
            ->where('product_id', (int) $product['id'])
            ->first();

        if ($variant === null) {
            throw new DomainException(sprintf('Phân loại của "%s" trong giỏ đã ngừng bán.', (string) $product['name']));
        }

        $price = (int) PriceCalculator::unitPrice($variant);
        if ($price >= self::HIGH_VALUE_THRESHOLD && $qty > self::HIGH_VALUE_MAX_QTY) {
            throw new DomainException(sprintf(
                '"%s – %s" có giá từ 10.000.000 ₫ trở lên chỉ được mua tối đa %d cái mỗi đơn.',
                (string) $product['name'],
                (string) $variant['title'],
                self::HIGH_VALUE_MAX_QTY,
            ));
        }

        $affected = $db->query(
            'UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ? AND deleted_at IS NULL',
            [$qty, $variantId, $qty],
        )->rowCount();

        if ($affected === 0) {
            throw new DomainException(sprintf(
                '"%s – %s" không đủ hàng (còn %d).',
                (string) $product['name'],
                (string) $variant['title'],
                (int) ($variant['stock'] ?? 0),
            ));
        }

        $this->variants->refreshSummary((int) $product['id']);

        return [
            'product_id' => (int) $product['id'],
            'variant_id' => $variantId,
            'product_name' => (string) $product['name'],
            'variant_label' => (string) $variant['title'],
            'sku' => $variant['sku'] ?? ($product['sku'] ?? null),
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