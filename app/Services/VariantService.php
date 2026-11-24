<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ProductVariant;
use DomainException;

/**
 * Biến thể sản phẩm động: admin tự đặt thuộc tính (Màu, Size, Dung lượng...) cho TỪNG sản phẩm,
 * hệ thống sinh mọi tổ hợp (tích Descartes), mỗi tổ hợp là 1 biến thể có SKU, giá, tồn kho riêng.
 *
 * Bảng: product_options (thuộc tính) → product_option_values (giá trị)
 *       product_variants (tổ hợp)     ↔ product_variant_values (biến thể gồm giá trị nào)
 * Cả 3 bảng options / values / variants đều XOÁ MỀM: bỏ "XL" rồi thêm lại thì "XL" giữ id cũ,
 * nên tổ hợp "Xám / XL" khớp đúng biến thể cũ và được khôi phục (kèm giá, tồn kho, SKU).
 *
 * products.price / sale_price / stock của sản phẩm có biến thể là CỘT TỔNG HỢP (giá thấp nhất, tổng tồn kho)
 * để trang danh sách không phải JOIN. Luôn cập nhật bằng refreshSummary() trong cùng transaction.
 */
final class VariantService
{
    public const MAX_OPTIONS = 3;
    public const MAX_VALUES = 20;
    public const MAX_VARIANTS = 100;

    public function __construct(private Database $db)
    {
    }

    /**
     * Đọc ô nhập của admin, mỗi dòng 1 thuộc tính:  "Màu: Đen, Xám"  /  "Size: M, L, XL"
     *
     * @return array<int, array{name: string, values: string[]}>
     */
    public static function parseSpec(string $text): array
    {
        $spec = [];
        $names = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $no => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!str_contains($line, ':')) {
                throw new DomainException('Dòng ' . ($no + 1) . ' thiếu dấu ":" (đúng mẫu: Màu: Đen, Trắng).');
            }
            [$name, $list] = array_map('trim', explode(':', $line, 2));
            $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $list)), fn (string $v): bool => $v !== '')));

            if ($name === '' || mb_strlen($name) > 60) {
                throw new DomainException('Dòng ' . ($no + 1) . ': tên thuộc tính trống hoặc dài quá 60 ký tự.');
            }
            if (isset($names[mb_strtolower($name)])) {
                throw new DomainException('Thuộc tính "' . $name . '" bị lặp.');
            }
            if ($values === []) {
                throw new DomainException('Thuộc tính "' . $name . '" chưa có giá trị nào.');
            }
            if (count($values) > self::MAX_VALUES) {
                throw new DomainException('Thuộc tính "' . $name . '" có quá ' . self::MAX_VALUES . ' giá trị.');
            }
            foreach ($values as $v) {
                if (mb_strlen($v) > 60) {
                    throw new DomainException('Giá trị "' . $v . '" dài quá 60 ký tự.');
                }
            }
            $names[mb_strtolower($name)] = true;
            $spec[] = ['name' => $name, 'values' => $values];
        }

        if (count($spec) > self::MAX_OPTIONS) {
            throw new DomainException('Tối đa ' . self::MAX_OPTIONS . ' thuộc tính.');
        }
        $combos = array_product(array_map(fn (array $o): int => count($o['values']), $spec));
        if ($spec !== [] && $combos > self::MAX_VARIANTS) {
            throw new DomainException("Sinh ra {$combos} biến thể, vượt giới hạn " . self::MAX_VARIANTS . '.');
        }
        return $spec;
    }

    /** @param array<int, array{name: string, values: array<int, array{value: string}>}> $options */
    public static function specToText(array $options): string
    {
        return implode("\n", array_map(
            fn (array $o): string => $o['name'] . ': ' . implode(', ', array_column($o['values'], 'value')),
            $options,
        ));
    }

    /**
     * Tích Descartes: [[Đen, Xám], [M, L]] → [[Đen, M], [Đen, L], [Xám, M], [Xám, L]]
     *
     * @param array<int, array<int, mixed>> $lists
     * @return array<int, array<int, mixed>>
     */
    public static function cartesian(array $lists): array
    {
        $result = [[]];
        foreach ($lists as $list) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($list as $item) {
                    $next[] = [...$prefix, $item];
                }
            }
            $result = $next;
        }
        return $lists === [] ? [] : $result;
    }

    /** @return array<int, array{id: int, name: string, values: array<int, array{id: int, value: string}>}> */
    public function options(int $productId): array
    {
        $rows = $this->db->select(
            'SELECT o.id AS option_id, o.name, v.id AS value_id, v.value
             FROM product_options o
             JOIN product_option_values v ON v.option_id = o.id
             WHERE o.product_id = ? AND o.deleted_at IS NULL AND v.deleted_at IS NULL
             ORDER BY o.position, o.id, v.position, v.id',
            [$productId],
        );
        $options = [];
        foreach ($rows as $r) {
            $id = (int) $r['option_id'];
            $options[$id] ??= ['id' => $id, 'name' => (string) $r['name'], 'values' => []];
            $options[$id]['values'][] = ['id' => (int) $r['value_id'], 'value' => (string) $r['value']];
        }
        return array_values($options);
    }

    /**
     * Đồng bộ thuộc tính theo spec mới rồi sinh lại tổ hợp:
     *   tổ hợp đang có       → giữ nguyên (giá, tồn kho, SKU)
     *   tổ hợp từng bị xoá   → KHÔI PHỤC dòng cũ (không tạo trùng)
     *   tổ hợp mới           → tạo, giá lấy theo sản phẩm, tồn kho 0
     *   tổ hợp không còn     → XOÁ MỀM (đơn hàng cũ vẫn trỏ tới được)
     *
     * @param array<int, array{name: string, values: string[]}> $spec
     * @return array{created: int, restored: int, trashed: int, kept: int}
     */
    public function sync(int $productId, array $spec): array
    {
        return $this->db->transaction(function (Database $db) use ($productId, $spec): array {
            $product = $db->table('products')->where('id', $productId)->whereNull('deleted_at')->first()
                ?? throw new DomainException('Sản phẩm không tồn tại.');

            // 1. Thuộc tính + giá trị: trùng tên thì dùng lại id cũ (kể cả đang xoá mềm), cái không còn thì xoá mềm
            $keepOptionIds = [];
            $valueIdsPerOption = [];
            $labels = [];
            foreach (array_values($spec) as $pos => $option) {
                $optionId = $this->upsert('product_options', ['product_id' => $productId, 'name' => $option['name']], ['position' => $pos]);
                $keepOptionIds[] = $optionId;
                $ids = [];
                foreach (array_values($option['values']) as $vpos => $value) {
                    $valueId = $this->upsert('product_option_values', ['option_id' => $optionId, 'value' => $value], ['position' => $vpos]);
                    $ids[] = $valueId;
                    $labels[$valueId] = $value;
                }
                $this->trashExcept('product_option_values', 'option_id = ?', [$optionId], $ids);
                $valueIdsPerOption[] = $ids;
            }
            $this->trashExcept('product_options', 'product_id = ?', [$productId], $keepOptionIds);

            // 2. Tổ hợp
            $existing = [];
            foreach (ProductVariant::withTrashed()->where('product_id', $productId)->get() as $v) {
                $existing[(string) $v['option_key']] = $v;
            }
            $stats = ['created' => 0, 'restored' => 0, 'trashed' => 0, 'kept' => 0];
            $wanted = [];
            $now = date('Y-m-d H:i:s');

            foreach (self::cartesian($valueIdsPerOption) as $combo) {
                $key = ProductVariant::keyOf($combo);
                $wanted[$key] = true;
                $title = implode(' / ', array_map(fn (int $id): string => $labels[$id], $combo));
                $row = $existing[$key] ?? null;

                if ($row === null) {
                    $variantId = $db->insert('product_variants', [
                        'product_id' => $productId,
                        'option_key' => $key,
                        'title' => $title,
                        'sku' => $this->generateSku($product, $title),
                        'price' => (int) $product['price'],
                        'sale_price' => $product['sale_price'],
                        'stock' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $stats['created']++;
                } else {
                    $variantId = (int) $row['id'];
                    $db->update('product_variants', ['title' => $title, 'deleted_at' => null, 'updated_at' => $now], 'id = ?', [$variantId]);
                    $stats[ProductVariant::isTrashed($row) ? 'restored' : 'kept']++;
                }

                // Bảng nối không có dữ liệu riêng nên ghi lại cho chắc là đủ
                $db->delete('product_variant_values', 'variant_id = ?', [$variantId]);
                foreach ($combo as $valueId) {
                    $db->insert('product_variant_values', ['variant_id' => $variantId, 'option_value_id' => $valueId]);
                }
            }

            foreach ($existing as $key => $row) {
                if (!isset($wanted[$key]) && !ProductVariant::isTrashed($row)) {
                    ProductVariant::deleteById((int) $row['id']);
                    $stats['trashed']++;
                }
            }

            $this->refreshSummary($productId);
            return $stats;
        });
    }

    /**
     * Cập nhật SKU / giá / tồn kho từng biến thể (dữ liệu đã validate ở controller).
     *
     * @param array<int, array{sku: ?string, price: int, sale_price: ?int, stock: int}> $rows [variant_id => ...]
     */
    public function updateVariants(int $productId, array $rows): void
    {
        $this->db->transaction(function (Database $db) use ($productId, $rows): void {
            foreach ($rows as $variantId => $data) {
                $sku = $data['sku'] !== null && $data['sku'] !== '' ? $data['sku'] : null;
                if ($sku !== null && ProductVariant::withTrashed()->where('sku', $sku)->where('id', '!=', (int) $variantId)->exists()) {
                    throw new DomainException('SKU "' . $sku . '" đã được dùng cho biến thể khác.');
                }
                if ($data['sale_price'] !== null && $data['sale_price'] >= $data['price']) {
                    throw new DomainException('Giá khuyến mãi phải nhỏ hơn giá gốc (biến thể #' . (int) $variantId . ').');
                }
                // product_id trong WHERE: chặn sửa biến thể của sản phẩm khác qua form giả mạo
                $db->update('product_variants', [
                    'sku' => $sku,
                    'price' => $data['price'],
                    'sale_price' => $data['sale_price'],
                    'stock' => $data['stock'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ? AND product_id = ? AND deleted_at IS NULL', [(int) $variantId, $productId]);
            }
            $this->refreshSummary($productId);
        });
    }

    /**
     * Tìm biến thể đang bán ứng với tập giá trị khách chọn (mỗi thuộc tính 1 giá trị).
     * Dùng option_key + UNIQUE index: 1 lần tra, không cần JOIN.
     *
     * @param int[] $valueIds
     * @return array<string, mixed>|null
     */
    public function findByValues(int $productId, array $valueIds): ?array
    {
        $valueIds = array_values(array_unique(array_map('intval', $valueIds)));
        if ($valueIds === [] || count($valueIds) !== count($this->options($productId))) {
            return null;
        }
        return ProductVariant::query()
            ->where('product_id', $productId)
            ->where('option_key', ProductVariant::keyOf($valueIds))
            ->first();
    }

    /**
     * Cập nhật cột tổng hợp trên products từ các biến thể đang bán (bằng SQL tổng hợp).
     * Không có biến thể => sản phẩm quay về dạng thường (has_variants = 0).
     */
    public function refreshSummary(int $productId): void
    {
        $agg = $this->db->selectOne(
            'SELECT COUNT(*) AS n,
                    COALESCE(SUM(stock), 0) AS stock,
                    MIN(price) AS min_price,
                    MIN(CASE WHEN sale_price > 0 AND sale_price < price THEN sale_price ELSE price END) AS min_final
             FROM product_variants
             WHERE product_id = ? AND deleted_at IS NULL',
            [$productId],
        );

        if ((int) ($agg['n'] ?? 0) === 0) {
            $this->db->update('products', ['has_variants' => 0], 'id = ?', [$productId]);
            return;
        }
        $minPrice = (int) $agg['min_price'];
        $minFinal = (int) $agg['min_final'];
        $this->db->update('products', [
            'has_variants' => 1,
            'stock' => (int) $agg['stock'],
            'price' => $minPrice,
            'sale_price' => $minFinal < $minPrice ? $minFinal : null,
        ], 'id = ?', [$productId]);
    }

    /**
     * Dữ liệu cho trang sản phẩm: thuộc tính + biến thể đang bán kèm danh sách value_id.
     *
     * @return array{options: array<int, array<string, mixed>>, variants: array<int, array<string, mixed>>}
     */
    public function forStorefront(int $productId): array
    {
        $links = $this->db->select(
            'SELECT pvv.variant_id, pvv.option_value_id
             FROM product_variant_values pvv
             JOIN product_variants v ON v.id = pvv.variant_id
             WHERE v.product_id = ? AND v.deleted_at IS NULL',
            [$productId],
        );
        $valuesOf = [];
        foreach ($links as $l) {
            $valuesOf[(int) $l['variant_id']][] = (int) $l['option_value_id'];
        }

        $variants = array_map(fn (array $v): array => [
            'id' => (int) $v['id'],
            'title' => (string) $v['title'],
            'sku' => $v['sku'],
            'price' => (int) $v['price'],
            'final' => PriceCalculator::unitPrice($v),
            'stock' => (int) $v['stock'],
            'values' => $valuesOf[(int) $v['id']] ?? [],
        ], ProductVariant::forProduct($productId));

        return ['options' => $this->options($productId), 'variants' => $variants];
    }

    /**
     * Tìm theo khoá tự nhiên (KỂ CẢ dòng đã xoá mềm), có thì cập nhật + khôi phục, chưa có thì thêm. Trả id.
     *
     * @param array<string, mixed> $keys
     * @param array<string, mixed> $values
     */
    private function upsert(string $table, array $keys, array $values): int
    {
        $query = $this->db->table($table);
        foreach ($keys as $column => $value) {
            $query->where($column, $value);
        }
        $id = $query->value('id');
        if ($id !== null) {
            $this->db->update($table, $values + ['deleted_at' => null], 'id = ?', [(int) $id]);
            return (int) $id;
        }
        return $this->db->insert($table, $keys + $values);
    }

    /**
     * Xoá mềm các dòng thuộc phạm vi $where trừ những id cần giữ.
     *
     * @param array<int, mixed> $params
     * @param int[] $keepIds
     */
    private function trashExcept(string $table, string $where, array $params, array $keepIds): void
    {
        $sql = "{$where} AND deleted_at IS NULL";
        if ($keepIds !== []) {
            $sql .= ' AND id NOT IN (' . implode(', ', array_fill(0, count($keepIds), '?')) . ')';
        }
        $this->db->update($table, ['deleted_at' => date('Y-m-d H:i:s')], $sql, [...$params, ...$keepIds]);
    }

    /** SKU biến thể = SKU sản phẩm + giá trị, vd "SP0013-DEN-XL". Không có SKU gốc thì để trống. @param array<string, mixed> $product */
    private function generateSku(array $product, string $title): ?string
    {
        if (empty($product['sku'])) {
            return null;
        }
        $base = substr($product['sku'] . '-' . strtoupper(str_slug($title)), 0, 60);
        $sku = $base;
        $i = 2;
        while (ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
            $sku = substr($base, 0, 58) . '-' . $i++;
        }
        return $sku;
    }
}
