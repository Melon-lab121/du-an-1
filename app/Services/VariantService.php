<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ProductVariant;
use DomainException;

final class VariantService
{
    public const MAX_OPTIONS = 3;
    public const MAX_VALUES = 20;
    public const MAX_VARIANTS = 100;

    public function __construct(private Database $db) {}

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
            $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $list)), fn(string $v): bool => $v !== '')));

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
        $combos = array_product(array_map(fn(array $o): int => count($o['values']), $spec));
        if ($spec !== [] && $combos > self::MAX_VARIANTS) {
            throw new DomainException("Sinh ra {$combos} biến thể, vượt giới hạn " . self::MAX_VARIANTS . '.');
        }
        return $spec;
    }

    /** @param array<int, array{name: string, values: array<int, array{value: string}>}> $options */
    public static function specToText(array $options): string
    {
        return implode("\n", array_map(
            fn(array $o): string => $o['name'] . ': ' . implode(', ', array_column($o['values'], 'value')),
            $options,
        ));
    }

    /** @param array<int, array<int, mixed>> $lists @return array<int, array<int, mixed>> */
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
             JOIN product_option_values v
               ON v.option_id = o.id AND v.deleted_at IS NULL
             JOIN product_variant_values pvv
               ON pvv.option_value_id = v.id
             JOIN product_variants pv
               ON pv.id = pvv.variant_id AND pv.deleted_at IS NULL
             WHERE o.product_id = ? AND o.deleted_at IS NULL
             GROUP BY o.id, o.name, v.id, v.value, o.position, v.position
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

    /** @param array<int, array{name: string, values: string[]}> $spec @return array{created: int, restored: int, trashed: int, kept: int} */
    public function sync(int $productId, array $spec): array
    {
        return $this->db->transaction(function (Database $db) use ($productId, $spec): array {
            $product = $db->table('products')->where('id', $productId)->whereNull('deleted_at')->first()
                ?? throw new DomainException('Sản phẩm không tồn tại.');

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
                $title = implode(' / ', array_map(fn(int $id): string => $labels[$id], $combo));
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
     * Thêm nhiều biến thể tùy chỉnh cùng lúc cho sản phẩm.
     * Nếu tên biến thể có dạng "Thuộc tính: Giá trị" (hoặc khớp thuộc tính hiện có),
     * hệ thống sẽ tự tạo/khôi phục product_options, product_option_values và nối product_variant_values.
     *
     * @param array<int, array{title: string, sku: ?string, price: int, sale_price: ?int, stock: int, image: ?string}> $items
     * @return int Số biến thể đã thêm
     */
    public function addVariants(int $productId, array $items): int
    {
        if ($items === []) {
            return 0;
        }

        return $this->db->transaction(function (Database $db) use ($productId, $items): int {
            $product = $db->table('products')->where('id', $productId)->whereNull('deleted_at')->first()
                ?? throw new DomainException('Sản phẩm không tồn tại.');

            $currentCount = (int) ($db->selectOne(
                'SELECT COUNT(*) AS cnt FROM product_variants WHERE product_id = ? AND deleted_at IS NULL',
                [$productId],
            )['cnt'] ?? 0);

            if ($currentCount + count($items) > self::MAX_VARIANTS) {
                throw new DomainException('Tổng số biến thể vượt quá giới hạn ' . self::MAX_VARIANTS . '.');
            }

            $now = date('Y-m-d H:i:s');
            $inserted = 0;

            foreach ($items as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    throw new DomainException('Tên biến thể không được để trống.');
                }

                $price = (int) ($item['price'] ?? 0);
                $salePrice = isset($item['sale_price']) && $item['sale_price'] !== null ? (int) $item['sale_price'] : null;
                if ($salePrice !== null && $salePrice >= $price) {
                    throw new DomainException('Giá khuyến mãi phải nhỏ hơn giá gốc (biến thể "' . $title . '").');
                }

                $sku = isset($item['sku']) && $item['sku'] !== '' ? trim((string) $item['sku']) : $this->generateSku($product, $title);
                if ($sku !== null && ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
                    throw new DomainException('SKU "' . $sku . '" đã được dùng.');
                }

                [$resolvedTitle, $valueIds] = $this->resolveOptionValueIds($db, $productId, $title);

                if ($valueIds !== []) {
                    $optionKey = ProductVariant::keyOf($valueIds);
                    $existing = ProductVariant::withTrashed()
                        ->where('product_id', $productId)
                        ->where('option_key', $optionKey)
                        ->first();

                    if ($existing !== null) {
                        $variantId = (int) $existing['id'];
                        $db->update('product_variants', [
                            'title' => $resolvedTitle,
                            'sku' => $sku,
                            'price' => $price,
                            'sale_price' => $salePrice,
                            'stock' => max(0, (int) ($item['stock'] ?? 0)),
                            'image' => $item['image'] ?? $existing['image'],
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ], 'id = ?', [$variantId]);
                    } else {
                        $variantId = $db->insert('product_variants', [
                            'product_id' => $productId,
                            'option_key' => $optionKey,
                            'title' => $resolvedTitle,
                            'sku' => $sku,
                            'price' => $price,
                            'sale_price' => $salePrice,
                            'stock' => max(0, (int) ($item['stock'] ?? 0)),
                            'image' => $item['image'] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $db->delete('product_variant_values', 'variant_id = ?', [$variantId]);
                    foreach ($valueIds as $valueId) {
                        $db->insert('product_variant_values', [
                            'variant_id' => $variantId,
                            'option_value_id' => $valueId,
                        ]);
                    }
                } else {
                    $optionKey = 'custom:' . md5(mb_strtolower($resolvedTitle) . '#' . uniqid('', true));
                    $db->insert('product_variants', [
                        'product_id' => $productId,
                        'option_key' => $optionKey,
                        'title' => $resolvedTitle,
                        'sku' => $sku,
                        'price' => $price,
                        'sale_price' => $salePrice,
                        'stock' => max(0, (int) ($item['stock'] ?? 0)),
                        'image' => $item['image'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $inserted++;
            }

            $this->refreshSummary($productId);
            return $inserted;
        });
    }

    private function resolveOptionValueIds(Database $db, int $productId, string $title): array
    {
        $existingOptions = $db->select(
            'SELECT id, name, position, deleted_at FROM product_options WHERE product_id = ? ORDER BY position ASC, id ASC',
            [$productId],
        );

        $byLowerName = [];
        foreach ($existingOptions as $opt) {
            $byLowerName[mb_strtolower((string) $opt['name'])] = $opt;
        }

        $assignments = [];

        if (str_contains($title, ':')) {
            // Tách theo dấu phẩy (khi mỗi phần có dạng "Thuộc tính: Giá trị"), dấu chấm phẩy hoặc xuống dòng
            $rawParts = preg_split('/\s*;\s*|\R/u', $title) ?: [];
            $tokens = [];
            foreach ($rawParts as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }
                // Nếu có nhiều cặp "tên: giá trị" cách nhau bởi dấu phẩy, tách theo ", " hoặc "," trước phần có ":"
                $commaSegments = preg_split('/,(?=\s*[^:,]+\s*:)/u', $chunk) ?: [$chunk];
                foreach ($commaSegments as $seg) {
                    $seg = trim($seg);
                    if ($seg !== '') {
                        $tokens[] = $seg;
                    }
                }
            }

            foreach ($tokens as $part) {
                if (!str_contains($part, ':')) {
                    return [$title, []];
                }
                [$optName, $valName] = array_map('trim', explode(':', $part, 2));
                if ($optName === '' || $valName === '' || mb_strlen($optName) > 60 || mb_strlen($valName) > 60) {
                    throw new DomainException('Tên thuộc tính hoặc giá trị biến thể không hợp lệ (tối đa 60 ký tự).');
                }
                $assignments[] = [$optName, $valName];
            }
        } elseif (count($byLowerName) === 1) {
            $singleOpt = reset($byLowerName);
            if ($singleOpt !== false && mb_strlen($title) <= 60) {
                $assignments[] = [(string) $singleOpt['name'], $title];
            }
        }

        if ($assignments === []) {
            return [$title, []];
        }

        if (count($assignments) > self::MAX_OPTIONS) {
            throw new DomainException('Tối đa ' . self::MAX_OPTIONS . ' thuộc tính cho mỗi biến thể.');
        }

        $valueIds = [];
        $displayParts = [];
        $nextPos = count($existingOptions);

        foreach ($assignments as [$optName, $valName]) {
            $lowerOpt = mb_strtolower($optName);
            $matchedOpt = $byLowerName[$lowerOpt] ?? null;

            if ($matchedOpt !== null) {
                $optionId = (int) $matchedOpt['id'];
                if ($matchedOpt['deleted_at'] !== null) {
                    $db->update('product_options', ['deleted_at' => null], 'id = ?', [$optionId]);
                }
            } else {
                $optionId = $this->upsert(
                    'product_options',
                    ['product_id' => $productId, 'name' => $optName],
                    ['position' => $nextPos++],
                );
                $byLowerName[$lowerOpt] = ['id' => $optionId, 'name' => $optName, 'deleted_at' => null];
            }

            $valCount = (int) ($db->selectOne(
                'SELECT COUNT(*) AS cnt FROM product_option_values WHERE option_id = ? AND deleted_at IS NULL',
                [$optionId],
            )['cnt'] ?? 0);

            $existingVal = $db->table('product_option_values')
                ->where('option_id', $optionId)
                ->where('value', $valName)
                ->first();

            if ($existingVal === null && $valCount >= self::MAX_VALUES) {
                throw new DomainException('Thuộc tính "' . $optName . '" vượt quá ' . self::MAX_VALUES . ' giá trị.');
            }

            $nextValPos = (int) ($db->selectOne(
                'SELECT COALESCE(MAX(position), -1) + 1 AS next_pos FROM product_option_values WHERE option_id = ?',
                [$optionId],
            )['next_pos'] ?? 0);

            $valueId = $this->upsert(
                'product_option_values',
                ['option_id' => $optionId, 'value' => $valName],
                ['position' => $nextValPos],
            );

            $valueIds[] = $valueId;
            $displayParts[] = $valName;
        }

        return [implode(' / ', $displayParts), $valueIds];
    }

    /** @param array<int, array{sku: ?string, price: int, sale_price: ?int, stock: int, image?: ?string}> $rows */
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

                $payload = [
                    'sku' => $sku,
                    'price' => $data['price'],
                    'sale_price' => $data['sale_price'],
                    'stock' => $data['stock'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if (array_key_exists('image', $data)) {
                    $payload['image'] = $data['image'];
                }

                $db->update('product_variants', $payload, 'id = ? AND product_id = ? AND deleted_at IS NULL', [(int) $variantId, $productId]);
            }
            $this->refreshSummary($productId);
        });
    }

    /** @param int[] $valueIds @return array<string, mixed>|null */
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

    /** @return array{options: array<int, array<string, mixed>>, variants: array<int, array<string, mixed>>} */
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

        $variants = array_map(fn(array $v): array => [
            'id' => (int) $v['id'],
            'title' => (string) $v['title'],
            'sku' => $v['sku'],
            'price' => (int) $v['price'],
            'final' => PriceCalculator::unitPrice($v),
            'stock' => (int) $v['stock'],
            'image' => $v['image'] ?? null,
            'values' => $valuesOf[(int) $v['id']] ?? [],
        ], ProductVariant::forProduct($productId));

        return ['options' => $this->options($productId), 'variants' => $variants];
    }

    /** @param array<string, mixed> $keys @param array<string, mixed> $values */
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

    /** @param array<int, mixed> $params @param int[] $keepIds */
    private function trashExcept(string $table, string $where, array $params, array $keepIds): void
    {
        $sql = "{$where} AND deleted_at IS NULL";
        if ($keepIds !== []) {
            $sql .= ' AND id NOT IN (' . implode(', ', array_fill(0, count($keepIds), '?')) . ')';
        }
        $this->db->update($table, ['deleted_at' => date('Y-m-d H:i:s')], $sql, [...$params, ...$keepIds]);
    }

    /** @param array<string, mixed> $product */
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

    /**
     * Lấy danh sách biến thể đã xoá mềm của sản phẩm để hiển thị trong admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trashedForProduct(int $productId): array
    {
        return $this->db->select(
            'SELECT id, product_id, option_key, title, sku, price, sale_price, stock, image, deleted_at
             FROM product_variants
             WHERE product_id = ? AND deleted_at IS NOT NULL
             ORDER BY deleted_at DESC, id DESC',
            [$productId],
        );
    }

    /**
     * Sửa nhanh một biến thể từ form sản phẩm.
     *
     * @param array{title?: string, sku?: ?string, price: int, sale_price: ?int, stock: int, image?: ?string} $data
     */
    public function quickUpdateVariant(int $productId, int $variantId, array $data): void
    {
        $this->db->transaction(function (Database $db) use ($productId, $variantId, $data): void {
            $existing = ProductVariant::withTrashed()
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->first() ?? throw new DomainException('Biến thể không tồn tại.');

            $sku = isset($data['sku']) && trim((string)$data['sku']) !== ''
                ? trim((string) $data['sku'])
                : null;

            if ($sku !== null && ProductVariant::withTrashed()->where('sku', $sku)->where('id', '!=', $variantId)->exists()) {
                throw new DomainException('SKU "' . $sku . '" đã được dùng cho biến thể khác.');
            }

            $price = (int)$data['price'];
            $salePrice = isset($data['sale_price']) && $data['sale_price'] !== null ? (int) $data['sale_price'] : null;
            if ($salePrice !== null && $salePrice >= $price) {
                throw new DomainException('Giá khuyến mãi phải nhỏ hơn giá gốc.');
            }

            $payload = [
                'sku' => $sku,
                'price' => $price,
                'sale_price' => $salePrice,
                'stock' => max(0, (int) $data['stock']),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if (isset($data['title']) && trim((string)$data['title']) !== '') {
                $payload['title'] = trim((string)$data['title']);
            }
            if (array_key_exists('image', $data)) {
                $payload['image'] = $data['image'];
            }

            $db->update('product_variants', $payload, 'id = ? AND product_id = ?', [$variantId, $productId]);
            $this->refreshSummary($productId);
        });
    }

    /** Xoá mềm một biến thể. */
    public function softDeleteVariant(int $productId, int $variantId): void
    {
        $this->db->transaction(function (Database $db) use ($productId, $variantId): void {
            $row = $db->selectOne(
                'SELECT id FROM product_variants WHERE id = ? AND product_id = ? AND deleted_at IS NULL',
                [$variantId, $productId],
            );
            if ($row === null) {
                throw new DomainException('Biến thể không tồn tại hoặc đã bị xoá.');
            }

            $db->update(
                'product_variants',
                ['deleted_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
                'id = ? AND product_id = ?',
                [$variantId, $productId],
            );
            $this->refreshSummary($productId);
        });
    }

    /** Khôi phục biến thể đã xoá mềm. */
    public function restoreVariant(int $productId, int $variantId): void
    {
        $this->db->transaction(function (Database $db) use ($productId, $variantId): void {
            $row = $db->selectOne(
                'SELECT id FROM product_variants WHERE id = ? AND product_id = ? AND deleted_at IS NOT NULL',
                [$variantId, $productId],
            );
            if ($row === null) {
                throw new DomainException('Không tìm thấy biến thể trong thùng rác.');
            }

            $db->update(
                'product_variants',
                ['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ? AND product_id = ?',
                [$variantId, $productId],
            );
            $this->refreshSummary($productId);
        });
    }

    /** Xoá vĩnh viễn biến thể khỏi database cùng các liên kết option value. */
    public function forceDeleteVariant(int $productId, int $variantId): void
    {
        $this->db->transaction(function (Database $db) use ($productId, $variantId): void {
            $row = $db->selectOne(
                'SELECT id FROM product_variants WHERE id = ? AND product_id = ?',
                [$variantId, $productId],
            );
            if ($row === null) {
                throw new DomainException('Biến thể không tồn tại.');
            }

            $linkedValueIds = array_map(
                static fn(array $r): int => (int) $r['option_value_id'],
                $db->select('SELECT option_value_id FROM product_variant_values WHERE variant_id = ?', [$variantId]),
            );

            $db->delete('product_variant_values', 'variant_id = ?', [$variantId]);

            // Nếu Database::delete() dùng query builder kiểu table()->where()->delete(), fallback rõ ràng:
            $deleted = $db->delete('product_variants', 'id = ? AND product_id = ?', [$variantId, $productId]);
            if ($deleted === 0 || $deleted === false) {
                $db->table('product_variants')->where('id', $variantId)->where('product_id', $productId)->delete();
            }

            foreach (array_unique($linkedValueIds) as $valId) {
                $stillUsed = (int) ($db->selectOne(
                    'SELECT COUNT(*) AS cnt FROM product_variant_values WHERE option_value_id = ?',
                    [$valId],
                )['cnt'] ?? 0);
                if ($stillUsed === 0) {
                    $db->delete('product_option_values', 'id = ?', [$valId]);
                }
            }

            $db->delete(
                'product_options',
                'product_id = ? AND id NOT IN (SELECT DISTINCT option_id FROM product_option_values WHERE option_id IS NOT NULL)',
                [$productId],
            );

            $this->refreshSummary($productId);
        });
    }
}
