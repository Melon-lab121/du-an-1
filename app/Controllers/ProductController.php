<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Product;
use App\Services\VariantService;

final class ProductController extends Controller
{
    public function __construct(private Config $config, private VariantService $variants)
    {
    }

    public function index(Request $request, ?string $slug = null): Response
    {
        $categorySlug = $slug ?? (string) $request->query('category', '');
        $category = $categorySlug !== '' ? Category::findBy('slug', $categorySlug) : null;
        if ($categorySlug !== '' && $category === null) {
            throw new HttpException(404, 'Danh mục không tồn tại');
        }

        $filters = [
            'q' => (string) $request->query('q', ''),
            'category_id' => $category['id'] ?? null,
            'sort' => (string) $request->query('sort', 'newest'),
            'min_price' => (int) $request->query('min_price', 0) ?: null,
            'max_price' => (int) $request->query('max_price', 0) ?: null,
        ];

        $pager = Product::search($filters)->paginate(
            (int) $this->config->get('shop.per_page', 12),
            max(1, (int) $request->query('page', 1)),
        );

        return $this->view('products/index', [
            'pager' => $pager,
            'filters' => $filters,
            'category' => $category,
            'categories' => Category::withProductCount(),
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        return $this->index($request, $slug);
    }

    public function show(string $slug): Response
    {
        $product = Product::findActiveBySlug($slug) ?? throw new HttpException(404, 'Sản phẩm không tồn tại');

        $related = $product['category_id'] === null ? [] : Product::active()
            ->where('category_id', (int) $product['category_id'])
            ->where('id', '!=', (int) $product['id'])
            ->latest()->limit(4)->get();

        return $this->view('products/show', [
            'product' => $product,
            'category' => $product['category_id'] ? Category::find((int) $product['category_id']) : null,
            'related' => $related,
            'variants' => (int) $product['has_variants'] ? $this->variants->forStorefront((int) $product['id']) : null,
        ]);
    }
}
