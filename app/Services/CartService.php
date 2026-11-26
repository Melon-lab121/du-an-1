<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Events;
use App\Core\Session;
use App\Models\Product;
use App\Models\ProductVariant;
use DomainException;
use Throwable;

/**
 * Giỏ hàng kết hợp Session (Guest) + Database (User đã đăng nhập):
 * - Khách vãng lai: lưu trong Session với thời hạn 10 phút (600s) + giữ chỗ tồn kho tạm thời.
 * - Đã đăng nhập: lưu trong bảng `carts` & `cart_details`.
 * - Ngay khi phát hiện user đã đăng nhập mà Session còn giỏ guest hợp lệ: tự động gộp vào DB và xoá giỏ tạm.
 */
final class CartService
{
    private const SESSION_KEY = 'guest_cart';
    private const SESSION_TOKEN_KEY = 'guest_cart_token';
    private const GUEST_TTL_SECONDS = 600; // 10 phút

    public function __construct(
        private Database $db,
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
        $productId = (int) $parts[0];
        $variantId = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : null;

        return [$productId, $variantId > 0 ? $variantId : null];
    }

    /**
     * Trả về mảng [key => quantity] của giỏ hiện tại.
     * Tự động xử lý hết hạn 10 phút cho guest và tự động gộp giỏ nếu user vừa đăng nhập.
     *
     * @return array<string, int>
     */
    public function raw(): array
    {
        $userId = $this->currentUserId();

        // 1. Nếu đã đăng nhập: kiểm tra xem có giỏ guest còn hạn trong session để gộp vào DB không
        if ($userId !== null && $userId > 0) {
            $guestItems = $this->readValidGuestItems();
            if ($guestItems !== []) {
                $this->mergeGuestToDb($userId, $guestItems);
            }
            return $this->readDbCart($userId);
        }

        // 2. Khách vãng lai: đọc giỏ session (tự xoá nếu quá 10 phút)
        return $this->readValidGuestItems();
    }

    public function add(int $productId, int $quantity = 1, ?int $variantId = null): void
    {
        [$product, $variant] = $this->findSellable($productId, $variantId);
        $resolvedVariantId = $variant === null ? null : (int) $variant['id'];
        $key = self::key($productId, $resolvedVariantId);

        $cart = $this->raw();
        $currentInMyCart = $cart[$key] ?? 0;
        $addQty = max(1, $quantity);
        $wanted = $currentInMyCart + $addQty;

        // Tính tồn kho khả dụng (trừ đi phần đang được các guest khác giữ chỗ trong 10 phút)
        $rawStock = (int) ($variant ?? $product)['stock'];
        $reservedByOthers = $this->getReservedByOthers($productId, $resolvedVariantId);
        $availableStock = max(0, $rawStock - $reservedByOthers);

        if ($availableStock <= 0) {
            throw new DomainException('Sản phẩm đã hết hàng hoặc đang được khách khác giữ chỗ.');
        }
        if ($wanted > $availableStock) {
            throw new DomainException('Chỉ còn ' . $availableStock . ' sản phẩm khả dụng trong kho.');
        }

        $cart[$key] = min($wanted, $this->maxQty());
        $this->save($cart);

        $this->events->dispatch('cart.added', [
            'product' => $product,
            'variant' => $variant,
            'quantity' => $addQty,
        ]);
    }

    public function update(int|string $key, int $quantity): void
    {
        $stringKey = (string) $key;
        $cart = $this->raw();
        if (!isset($cart[$stringKey])) {
            return;
        }
        if ($quantity <= 0) {
            $this->remove($stringKey);
            return;
        }

        [$productId, $variantId] = self::parseKey($stringKey);
        [$product, $variant] = $this->findSellable($productId, $variantId);

        $rawStock = (int) ($variant ?? $product)['stock'];
        $reservedByOthers = $this->getReservedByOthers($productId, $variantId);
        $availableStock = max(0, $rawStock - $reservedByOthers);

        $nextQty = min($quantity, $availableStock, $this->maxQty());
        if ($nextQty <= 0) {
            unset($cart[$stringKey]);
        } else {
            $cart[$stringKey] = $nextQty;
        }

        $this->save($cart);
    }

    public function remove(int|string $key): void
    {
        $stringKey = (string) $key;
        $cart = $this->raw();
        unset($cart[$stringKey]);
        $this->save($cart);
    }

    public function clear(): void
    {
        $userId = $this->currentUserId();
        if ($userId !== null && $userId > 0) {
            $cartId = $this->findUserCartId($userId, false);
            if ($cartId !== null) {
                $this->db->delete('cart_details', 'cart_id = ?', [$cartId]);
            }
        }
        $this->clearGuestSession();
    }

    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /**
     * Trả về thời gian còn lại (tính bằng giây) của giỏ hàng guest (0 nếu đã hết hạn hoặc đang dùng DB cart).
     */
    public function guestRemainingSeconds(): int
    {
        if ($this->currentUserId() !== null) {
            return 0;
        }
        $payload = $this->session->get(self::SESSION_KEY);
        if (!is_array($payload) || empty($payload['expires_at'])) {
            return 0;
        }
        return max(0, (int) $payload['expires_at'] - time());
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     product_id: int,
     *     variant_id: ?int,
     *     variant_label: ?string,
     *     product: array<string, mixed>,
     *     name: string,
     *     slug: string,
     *     image: ?string,
     *     price: int,
     *     quantity: int,
     *     total: int,
     *     stock: int
     * }>
     */
    public function lines(): array
    {
        $cart = $this->raw();
        if ($cart === []) {
            return [];
        }

        $parsed = array_map([self::class, 'parseKey'], array_keys($cart));
        $productIds = array_values(array_unique(array_filter(array_column($parsed, 0))));
        if ($productIds === []) {
            return [];
        }

        $products = array_column(
            Product::active()->whereIn('id', $productIds)->get(),
            null,
            'id',
        );

        $variantIds = array_values(array_unique(array_filter(array_column($parsed, 1))));
        $variants = $variantIds === []
            ? []
            : array_column(ProductVariant::query()->whereIn('id', $variantIds)->get(), null, 'id');

        $lines = [];
        $changed = false;

        foreach ($cart as $key => $qty) {
            [$productId, $variantId] = self::parseKey($key);
            $product = $products[$productId] ?? null;
            $variant = $variantId === null ? null : ($variants[$variantId] ?? null);

            $valid = $product !== null && ($variantId === null
                ? !(int) ($product['has_variants'] ?? 0)
                : ($variant !== null && (int) ($variant['product_id'] ?? 0) === $productId));

            $item = $variant ?? $product;
            if (!$valid || $item === null) {
                unset($cart[$key]);
                $changed = true;
                continue;
            }

            $rawStock = (int) ($item['stock'] ?? 0);
            $reservedByOthers = $this->getReservedByOthers($productId, $variantId);
            $availableStock = max(0, $rawStock - $reservedByOthers);

            if ($availableStock <= 0) {
                unset($cart[$key]);
                $changed = true;
                continue;
            }

            $safeQty = min((int) $qty, $availableStock, $this->maxQty());
            if ($safeQty !== (int) $qty) {
                $cart[$key] = $safeQty;
                $changed = true;
            }

            $price = (int) PriceCalculator::unitPrice($item);
            $image = !empty($variant['image'])
                ? (string) $variant['image']
                : (!empty($product['image']) ? (string) $product['image'] : null);

            $lines[] = [
                'key' => $key,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_label' => $variant['title'] ?? null,
                'product' => $product,
                'name' => (string) ($product['name'] ?? ''),
                'slug' => (string) ($product['slug'] ?? ''),
                'image' => $image,
                'price' => $price,
                'quantity' => $safeQty,
                'total' => $price * $safeQty,
                'stock' => $availableStock,
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
        $subtotal = (int) array_sum(array_column($lines, 'total'));
        $discount = (int) $this->events->filter('cart.discount', 0, [
            'lines' => $lines,
            'subtotal' => $subtotal,
        ]);

        return PriceCalculator::totals(
            $lines,
            (int) $this->config->get('shop.shipping_fee', 0),
            (int) $this->config->get('shop.free_shipping_threshold', 0),
            $discount,
        );
    }

    // =========================================================================
    // PRIVATE: XỬ LÝ SESSION (GUEST 10 PHÚT) & DATABASE (USER)
    // =========================================================================

    /** @param array<string, int> $cart */
    private function save(array $cart): void
    {
        $userId = $this->currentUserId();

        if ($userId !== null && $userId > 0) {
            $this->saveDbCart($userId, $cart);
            $this->clearGuestSession();
            return;
        }

        // Lưu cho guest vào Session kèm hạn 10 phút
        if ($cart === []) {
            $this->clearGuestSession();
            return;
        }

        $expiresAt = time() + self::GUEST_TTL_SECONDS;
        $this->session->set(self::SESSION_KEY, [
            'items' => $cart,
            'expires_at' => $expiresAt,
        ]);

        $this->syncGuestReservations($cart, $expiresAt);
    }

    /**
     * Đọc giỏ hàng của guest từ session. Nếu quá 10 phút thì tự động xoá.
     *
     * @return array<string, int>
     */
    private function readValidGuestItems(): array
    {
        $payload = $this->session->get(self::SESSION_KEY);
        if (!is_array($payload)) {
            return [];
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
            // Đã quá 10 phút -> huỷ giỏ tạm & nhả giữ chỗ
            $this->clearGuestSession();
            return [];
        }

        $items = $payload['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $k => $qty) {
            $q = (int) $qty;
            if ($q > 0) {
                $clean[(string) $k] = $q;
            }
        }

        return $clean;
    }

    private function clearGuestSession(): void
    {
        $token = $this->getGuestToken(false);
        $this->session->forget(self::SESSION_KEY);

        if ($token !== null) {
            try {
                $this->db->delete('cart_reservations', 'session_key = ?', [$token]);
            } catch (Throwable) {
                // Bỏ qua nếu chưa tạo bảng cart_reservations
            }
        }
    }

    /**
     * Gộp giỏ hàng tạm của guest vào DB của user đã đăng nhập, sau đó xoá giỏ tạm.
     *
     * @param array<string, int> $guestItems
     */
    private function mergeGuestToDb(int $userId, array $guestItems): void
    {
        $dbCart = $this->readDbCart($userId);

        foreach ($guestItems as $key => $guestQty) {
            [$productId, $variantId] = self::parseKey($key);
            try {
                [$product, $variant] = $this->findSellable($productId, $variantId);
                $stock = (int) ($variant ?? $product)['stock'];
                if ($stock <= 0) {
                    continue;
                }

                $existingQty = $dbCart[$key] ?? 0;
                $mergedQty = min($existingQty + $guestQty, $stock, $this->maxQty());
                if ($mergedQty > 0) {
                    $dbCart[$key] = $mergedQty;
                }
            } catch (DomainException) {
                // Sản phẩm/biến thể đã ngừng bán trong lúc khách chưa đăng nhập -> bỏ qua khi gộp
            }
        }

        $this->saveDbCart($userId, $dbCart);
        $this->clearGuestSession();
    }

    /** @return array<string, int> */
    private function readDbCart(int $userId): array
    {
        $cartId = $this->findUserCartId($userId, false);
        if ($cartId === null) {
            return [];
        }

        $rows = $this->db->select(
            'SELECT product_id, variant_id, quantity
             FROM cart_details
             WHERE cart_id = ?
             ORDER BY id ASC',
            [$cartId],
        );

        $cart = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $variantId = isset($row['variant_id']) && (int) $row['variant_id'] > 0
                ? (int) $row['variant_id']
                : null;
            $qty = (int) ($row['quantity'] ?? 0);

            if ($productId > 0 && $qty > 0) {
                $cart[self::key($productId, $variantId)] = $qty;
            }
        }

        return $cart;
    }

    /** @param array<string, int> $cart */
    private function saveDbCart(int $userId, array $cart): void
    {
        $cartId = $this->findUserCartId($userId, $cart !== []);
        if ($cartId === null) {
            return;
        }

        $this->db->transaction(function (Database $db) use ($cartId, $cart): void {
            $db->delete('cart_details', 'cart_id = ?', [$cartId]);

            foreach ($cart as $key => $qty) {
                $safeQty = (int) $qty;
                if ($safeQty <= 0) {
                    continue;
                }
                [$productId, $variantId] = self::parseKey($key);
                if ($productId <= 0) {
                    continue;
                }

                $db->insert('cart_details', [
                    'cart_id' => $cartId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $safeQty,
                ]);
            }
        });
    }

    private function findUserCartId(int $userId, bool $createIfMissing): ?int
    {
        $row = $this->db->selectOne(
            'SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1',
            [$userId],
        );

        if ($row && (int) ($row['id'] ?? 0) > 0) {
            return (int) $row['id'];
        }

        if (!$createIfMissing) {
            return null;
        }

        return (int) $this->db->insert('carts', ['user_id' => $userId]);
    }

    // =========================================================================
    // PRIVATE: GIỮ CHỖ 10 PHÚT CHO GUEST (SOFT-RESERVATION)
    // =========================================================================

    /** @param array<string, int> $cart */
    private function syncGuestReservations(array $cart, int $expiresAt): void
    {
        $token = $this->getGuestToken(true);
        if ($token === null) {
            return;
        }

        try {
            $nowStr = date('Y-m-d H:i:s');
            $expStr = date('Y-m-d H:i:s', $expiresAt);

            // Dọn các giữ chỗ đã hết hạn > 10 phút trên toàn hệ thống
            $this->db->delete('cart_reservations', 'expires_at <= ?', [$nowStr]);
            $this->db->delete('cart_reservations', 'session_key = ?', [$token]);

            foreach ($cart as $key => $qty) {
                if ($qty <= 0) {
                    continue;
                }
                [$productId, $variantId] = self::parseKey($key);
                $this->db->insert('cart_reservations', [
                    'session_key' => $token,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $qty,
                    'expires_at' => $expStr,
                ]);
            }
        } catch (Throwable) {
            // Bỏ qua nếu chưa tạo bảng cart_reservations
        }
    }

    /**
     * Tính tổng số lượng sản phẩm đang được các guest khác giữ chỗ trong 10 phút gần nhất.
     */
    private function getReservedByOthers(int $productId, ?int $variantId): int
    {
        try {
            $nowStr = date('Y-m-d H:i:s');
            $myToken = $this->getGuestToken(false) ?? '';

            if ($variantId === null) {
                $row = $this->db->selectOne(
                    'SELECT COALESCE(SUM(quantity), 0) AS total
                     FROM cart_reservations
                     WHERE product_id = ? AND variant_id IS NULL AND expires_at > ? AND session_key != ?',
                    [$productId, $nowStr, $myToken],
                );
            } else {
                $row = $this->db->selectOne(
                    'SELECT COALESCE(SUM(quantity), 0) AS total
                     FROM cart_reservations
                     WHERE product_id = ? AND variant_id = ? AND expires_at > ? AND session_key != ?',
                    [$productId, $variantId, $nowStr, $myToken],
                );
            }

            return (int) ($row['total'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function getGuestToken(bool $createIfMissing): ?string
    {
        $token = $this->session->get(self::SESSION_TOKEN_KEY);
        if (is_string($token) && $token !== '') {
            return $token;
        }
        if (!$createIfMissing) {
            return null;
        }

        $newToken = bin2hex(random_bytes(16));
        $this->session->set(self::SESSION_TOKEN_KEY, $newToken);
        return $newToken;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>|null} [product, variant] */
    private function findSellable(int $productId, ?int $variantId = null): array
    {
        $product = Product::active()->where('id', $productId)->first();
        if ($product === null) {
            throw new DomainException('Sản phẩm không tồn tại hoặc đã ngừng bán.');
        }

        if (!(int) ($product['has_variants'] ?? 0)) {
            if ($variantId !== null) {
                throw new DomainException('Sản phẩm này không có phân loại.');
            }
            return [$product, null];
        }

        if ($variantId === null) {
            throw new DomainException('Vui lòng chọn phân loại hàng.');
        }

        $variant = ProductVariant::query()
            ->where('id', $variantId)
            ->where('product_id', $productId)
            ->first();

        if ($variant === null) {
            throw new DomainException('Phân loại này không còn bán.');
        }

        return [$product, $variant];
    }

    private function currentUserId(): ?int
    {
        if (function_exists('auth')) {
            $auth = auth();
            if (is_object($auth) && method_exists($auth, 'id')) {
                $id = $auth->id();
                return $id !== null ? (int) $id : null;
            }
        }

        $sessionUserId = $this->session->get('user_id');
        return is_numeric($sessionUserId) && (int) $sessionUserId > 0 ? (int) $sessionUserId : null;
    }

    private function maxQty(): int
    {
        return (int) $this->config->get('shop.max_quantity_per_item', 99);
    }
}