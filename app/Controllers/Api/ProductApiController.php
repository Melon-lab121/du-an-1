<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Services\PriceCalculator;
use App\Services\VariantService;

/**
 * API JSON công khai — nền tảng cho app mobile / frontend SPA (headless) sau này.
 */
final class ProductApiController extends Controller
{
    public function __construct(private VariantService $variants)
    {
    }

    public function index(Request $request): Response
    {
        $pager = Product::search([
            'q' => (string) $request->query('q', ''),
            'category_id' => (int) $request->query('category_id', 0) ?: null,
            'sort' => (string) $request->query('sort', 'newest'),
        ])->paginate(min(50, max(1, (int) $request->query('per_page', 12))), max(1, (int) $request->query('page', 1)));

        return $this->json([
            'data' => array_map([$this, 'transform'], $pager['data']),
            'meta' => array_diff_key($pager, ['data' => true]),
        ]);
    }

    public function show(string $slug): Response
    {
        $product = Product::findActiveBySlug($slug) ?? throw new HttpException(404, 'Sản phẩm không tồn tại');
        $data = $this->transform($product) + ['description' => $product['description']];
        if ((int) $product['has_variants']) {
            $data += $this->variants->forStorefront((int) $product['id']);
        }
        return $this->json(['data' => $data]);
    }

    /** @param array<string, mixed> $p @return array<string, mixed> */
    private function transform(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'name' => $p['name'],
            'slug' => $p['slug'],
            'sku' => $p['sku'],
            'price' => (int) $p['price'],
            'final_price' => PriceCalculator::unitPrice($p),
            'stock' => (int) $p['stock'],
            'has_variants' => (bool) $p['has_variants'],
            'image' => $p['image'] ? absolute_url($p['image']) : null,
            'url' => absolute_url('/product/' . $p['slug']),
            'short_description' => $p['short_description'],
        ];
    }
}
