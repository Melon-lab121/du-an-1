<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Events;
use App\Core\Session;
use App\Models\Product;
use App\Models\ProductVariant;
use DomainException;

/**
 * Giỏ hàng lưu trong session dạng [khoá dòng => quantity].
 *   Sản phẩm thường:    "12"     (product_id)
 *   Sản phẩm biến thể:  "12:5"   (product_id:variant_id) — cùng áo nhưng khác size là 2 dòng khác nhau
 * Giá luôn lấy lại từ DB khi hiển thị/đặt hàng — không bao giờ tin giá từ client.
 *
 * Muốn lưu giỏ vào DB (đồng bộ nhiều thiết bị): giữ nguyên public API, đổi phần raw()/save().
 */
final class CartService
{
    private const KEY = 'cart';

    public function __construct(
        private Session $session,
        private Events $events,
        private Config $config,
    ) {
    }

    public static function key(int $productId, ?int $variantId = null): string
    {
        return $variantId === null ? (string) $productId : $productId . ':' . $variantId;
    }

    /** @return array{0: int, 1: ?int} [product_id, variant_id] */
    public static function parseKey(int|string $key): array
    {
        $parts = explode(':', (string) $key, 2);
        return [(int) $parts[0], isset($parts[1]) ? (int) $parts[1] : null];
    }

    /** @return array<int|string, int> */
    public function raw(): array
    {
        $cart = $this->session->get(self::KEY, []);
        return is_array($cart) ? array_map('intval', $cart) : [];
    }

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): void
    {
        [$product, $variant] = $this->findSellable($productId, $variantId);
        $stock = (int) ($variant ?? $product)['stock'];
        $key = self::key($productId, $variant === null ? null : (int) $variant['id']);

        $cart = $this->raw();
        $wanted = ($cart[$key] ?? 0) + max(1, $quantity);

        if ($stock <= 0) {
            throw new DomainException('Sản phẩm đã hết hàng.');
        }
        if ($wanted > $stock) {
            throw new DomainException('Chỉ còn ' . $stock . ' sản phẩm trong kho.');
        }

        $cart[$key] = min($wanted, $this->maxQty());
        $this->save($cart);
        $this->events->dispatch('cart.added', ['product' => $product, 'variant' => $variant, 'quantity' => $quantity]);
    }

    public function update(int|string $key, int $quantity): void
    {
        $cart = $this->raw();
        if (!isset($cart[$key])) {
            return;
        }
        if ($quantity <= 0) {
            $this->remove($key);
            return;
        }

        [$product, $variant] = $this->findSellable(...self::parseKey($key));
        $cart[$key] = min($quantity, (int) ($variant ?? $product)['stock'], $this->maxQty());
        if ($cart[$key] <= 0) {
            unset($cart[$key]);
        }
        $this->save($cart);
    }

    public function remove(int|string $key): void
    {
        $cart = $this->raw();
        unset($cart[$key]);
        $this->save($cart);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /**
     * Dòng giỏ hàng kèm thông tin sản phẩm/biến thể hiện tại. Dòng nào trỏ tới sản phẩm hoặc biến thể
     * vừa bị xoá (mềm), ẩn, hết hàng thì tự bỏ khỏi giỏ.
     *
     * @return array<int, array{key: string, product_id: int, variant_id: ?int, variant_label: ?string, product: array<string, mixed>, name: string, slug: string, image: ?string, price: int, quantity: int, total: int, stock: int}>
     */
    public function lines(): array
    {
        $cart = $this->raw();
        if ($cart === []) {
            return [];
        }

        $parsed = array_map([self::class, 'parseKey'], array_keys($cart));
        // Product::active() / ProductVariant::query() đã tự lọc deleted_at IS NULL
        $products = array_column(Product::active()->whereIn('id', array_unique(array_column($parsed, 0)))->get(), null, 'id');
        $variantIds = array_values(array_filter(array_column($parsed, 1)));
        $variants = $variantIds === [] ? [] : array_column(ProductVariant::query()->whereIn('id', $variantIds)->get(), null, 'id');

        $lines = [];
        $changed = false;
        foreach ($cart as $key => $qty) {
            [$productId, $variantId] = self::parseKey($key);
            $product = $products[$productId] ?? null;
            $variant = $variantId === null ? null : ($variants[$variantId] ?? null);

            $valid = $product !== null && ($variantId === null
                ? !(int) $product['has_variants']
                : $variant !== null && (int) $variant['product_id'] === $productId);
            $item = $variant ?? $product;
            if (!$valid || (int) $item['stock'] <= 0) {
                unset($cart[$key]);
                $changed = true;
                continue;
            }

            $qty = min($qty, (int) $item['stock']);
            $price = PriceCalculator::unitPrice($item);
            $lines[] = [
                'key' => (string) $key,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_label' => $variant['title'] ?? null,
                'product' => $product,
                'name' => (string) $product['name'],
                'slug' => (string) $product['slug'],
                'image' => $product['image'] ?: null,
                'price' => $price,
                'quantity' => $qty,
                'total' => $price * $qty,
                'stock' => (int) $item['stock'],
            ];
        }

        if ($changed) {
            $this->save($cart);
        }
        return $lines;
    }

    /**
     * @param array<int, array<string, mixed>>|null $lines
     * @return array{subtotal: int, discount: int, shipping_fee: int, total: int}
     */
    public function totals(?array $lines = null): array
    {
        $lines ??= $this->lines();
        $subtotal = array_sum(array_column($lines, 'total'));
        // Hook cho module giảm giá/coupon: $events->listen('cart.discount', fn ($d, $ctx) => ...)
        $discount = (int) $this->events->filter('cart.discount', 0, ['lines' => $lines, 'subtotal' => $subtotal]);

        return PriceCalculator::totals(
            $lines,
            (int) $this->config->get('shop.shipping_fee', 0),
            (int) $this->config->get('shop.free_shipping_threshold', 0),
            $discount,
        );
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>|null} [product, variant] */
    private function findSellable(int $productId, ?int $variantId = null): array
    {
        $product = Product::active()->where('id', $productId)->first();
        if ($product === null) {
            throw new DomainException('Sản phẩm không tồn tại hoặc đã ngừng bán.');
        }
        if (!(int) $product['has_variants']) {
            if ($variantId !== null) {
                throw new DomainException('Sản phẩm này không có phân loại.');
            }
            return [$product, null];
        }
        if ($variantId === null) {
            throw new DomainException('Vui lòng chọn phân loại hàng.');
        }
        $variant = ProductVariant::query()->where('id', $variantId)->where('product_id', $productId)->first();
        if ($variant === null) {
            throw new DomainException('Phân loại này không còn bán.');
        }
        return [$product, $variant];
    }

    /** @param array<int|string, int> $cart */
    private function save(array $cart): void
    {
        $this->session->set(self::KEY, $cart);
    }

    private function maxQty(): int
    {
        return (int) $this->config->get('shop.max_quantity_per_item', 99);
    }
}
