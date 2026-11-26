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

                $price = (int) PriceCalculator::unitPrice($product);
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

    /** Đổi trạng thái đơn; huỷ đơn sẽ hoàn lại tồn kho. */
    public function updateStatus(int $orderId, string $status): void
    {
        if (!array_key_exists($status, Order::STATUSES)) {
            throw new InvalidArgumentException('Trạng thái không hợp lệ.');
        }

        $this->db->transaction(function (Database $db) use ($orderId, $status): void {
            $order = $db->table('orders')->find($orderId) ?? throw new DomainException('Không tìm thấy đơn hàng.');
            $from = (string) $order['status'];
            if ($from === $status) {
                return;
            }
            if ($from === 'cancelled') {
                throw new DomainException('Đơn đã huỷ, không thể đổi trạng thái.');
            }
            if ($from === 'completed' && $status !== 'cancelled') {
                throw new DomainException('Đơn đã hoàn thành, không thể chuyển ngược trạng thái.');
            }

            $now = date('Y-m-d H:i:s');
            $updateData = ['status' => $status, 'updated_at' => $now];

            if ($status === 'completed' && ($order['payment_method'] ?? '') === 'cod' && ($order['payment_status'] ?? '') !== 'paid') {
                $updateData['payment_status'] = 'paid';
                $updateData['paid_at'] = $now;
            }
            if ($status === 'cancelled' && ($order['payment_status'] ?? '') === 'paid') {
                $updateData['payment_status'] = 'refunded';
            }

            $db->update('orders', $updateData, 'id = ?', [$orderId]);

            if ($status === 'cancelled') {
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

            $this->events->dispatch('order.status_changed', [
                'order' => Order::find($orderId),
                'from' => $from,
                'to' => $status,
            ]);
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

        $price = (int) PriceCalculator::unitPrice($variant);
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