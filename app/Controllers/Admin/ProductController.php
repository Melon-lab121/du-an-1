<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Uploader;
use App\Services\VariantService;
use DomainException;

final class ProductController extends Controller
{
    private const LABELS = [
        'name' => 'Tên sản phẩm', 'slug' => 'Slug', 'category_id' => 'Danh mục', 'sku' => 'SKU',
        'price' => 'Giá', 'sale_price' => 'Giá khuyến mãi', 'stock' => 'Tồn kho', 'status' => 'Trạng thái',
        'short_description' => 'Mô tả ngắn', 'description' => 'Mô tả',
    ];

    public function __construct(private Uploader $uploader, private VariantService $variants)
    {
    }

    public function index(Request $request): Response
    {
        $query = Product::query()->latest();
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->whereRaw('name LIKE ? OR sku LIKE ?', [$like, $like]);
        }

        return $this->view('admin/products/index', [
            'pager' => $query->paginate(15, max(1, (int) $request->query('page', 1))),
            'q' => $q,
            'categories' => array_column(Category::ordered(), 'name', 'id'),
            'trashCount' => Product::onlyTrashed()->count(),
        ]);
    }

    /** Thùng rác: sản phẩm đã xoá mềm, mới xoá lên đầu. */
    public function trash(Request $request): Response
    {
        $pager = Product::onlyTrashed()->orderBy('deleted_at', 'DESC')
            ->paginate(15, max(1, (int) $request->query('page', 1)));

        $ids = array_map(fn (array $p): int => (int) $p['id'], $pager['data']);
        $withOrders = $ids === [] ? [] : array_map('intval', $this->db()->table('order_items')
            ->whereIn('product_id', $ids)->pluck('product_id'));

        return $this->view('admin/products/trash', [
            'pager' => $pager,
            'withOrders' => array_flip($withOrders),
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin/products/form', [
            'product' => ['status' => 'active', 'stock' => 0, 'is_featured' => 0],
            'categories' => Category::ordered(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request);
        $data['slug'] = Product::uniqueSlug($data['slug'] ?: $data['name']);
        $data['image'] = $this->handleImage($request);

        Product::create($data);
        $this->flash('success', 'Đã thêm sản phẩm.');
        return $this->redirect('/admin/products');
    }

    public function edit(int $id): Response
    {
        $options = $this->variants->options($id);
        return $this->view('admin/products/form', [
            'product' => Product::findOrFail($id),
            'categories' => Category::ordered(),
            'optionText' => VariantService::specToText($options),
            'variants' => ProductVariant::forProduct($id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $product = Product::findOrFail($id);
        $data = $this->validated($request, $id, (bool) $product['has_variants']);
        $data['slug'] = Product::uniqueSlug($data['slug'] ?: $data['name'], $id);

        $image = $this->handleImage($request);
        if ($image !== null) {
            $this->uploader->delete($product['image']);
            $data['image'] = $image;
        }

        Product::updateById($id, $data);
        $this->flash('success', 'Đã cập nhật sản phẩm.');
        return $this->redirect('/admin/products');
    }

    /** Xoá MỀM: đưa vào thùng rác. Ảnh giữ nguyên để còn khôi phục. */
    public function destroy(int $id): Response
    {
        $product = Product::findOrFail($id);
        Product::deleteById($id);
        $this->flash('success', 'Đã chuyển "' . $product['name'] . '" vào thùng rác. Có thể khôi phục trong mục Thùng rác.');
        return $this->redirect('/admin/products');
    }

    public function restore(int $id): Response
    {
        $product = Product::onlyTrashed()->find($id) ?? throw new HttpException(404);
        Product::restore($id);
        $restored = Product::findOrFail($id);
        $note = $restored['sku'] === null && $product['sku'] !== null ? ' SKU cũ đã bị sản phẩm khác dùng, hãy đặt SKU mới.' : '';
        $this->flash('success', 'Đã khôi phục "' . $restored['name'] . '".' . $note);
        return $this->redirect('/admin/products/trash');
    }

    /**
     * Xoá VĨNH VIỄN. Chỉ cho phép khi sản phẩm chưa từng nằm trong đơn hàng nào:
     * nếu đã bán thì giữ lại trong thùng rác để báo cáo/đối soát vẫn trỏ được tới sản phẩm.
     */
    public function forceDestroy(int $id): Response
    {
        $product = Product::onlyTrashed()->find($id) ?? throw new HttpException(404);
        if (Product::hasOrders($id)) {
            $this->flash('error', 'Sản phẩm đã có trong đơn hàng nên không xoá vĩnh viễn được. Hãy để trong thùng rác.');
            return $this->redirect('/admin/products/trash');
        }
        Product::forceDeleteById($id); // biến thể, thuộc tính bị xoá theo (ON DELETE CASCADE)
        $this->uploader->delete($product['image']);
        $this->flash('success', 'Đã xoá vĩnh viễn "' . $product['name'] . '".');
        return $this->redirect('/admin/products/trash');
    }

    /** Lưu thuộc tính (Màu: Đen, Xám ...) và sinh lại tổ hợp biến thể. */
    public function saveOptions(Request $request, int $id): Response
    {
        Product::findOrFail($id);
        try {
            $spec = VariantService::parseSpec((string) $request->input('options_text', ''));
            $stats = $this->variants->sync($id, $spec);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['options_text' => $e->getMessage()], $request->all());
        }

        $this->flash('success', $spec === []
            ? 'Đã bỏ phân loại. Sản phẩm quay về dạng thường; hãy kiểm tra lại giá và tồn kho.'
            : sprintf('Đã cập nhật phân loại: %d mới, %d khôi phục, %d giữ nguyên, %d chuyển vào thùng rác.',
                $stats['created'], $stats['restored'], $stats['kept'], $stats['trashed']));
        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    /** Lưu SKU / giá / tồn kho của từng biến thể. */
    public function saveVariants(Request $request, int $id): Response
    {
        Product::findOrFail($id);
        $input = $request->input('variants', []);
        $rows = [];
        $errors = [];
        foreach (is_array($input) ? $input : [] as $variantId => $row) {
            $row = is_array($row) ? $row : [];
            $price = filter_var($row['price'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $stock = filter_var($row['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $sale = trim((string) ($row['sale_price'] ?? '')) === '' ? null
                : filter_var($row['sale_price'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($price === false || $stock === false || $sale === false || mb_strlen($sku) > 64) {
                $errors['variants'] = 'Giá, giá KM, tồn kho phải là số nguyên ≥ 0; SKU tối đa 64 ký tự.';
                continue;
            }
            $rows[(int) $variantId] = ['sku' => $sku === '' ? null : $sku, 'price' => $price, 'sale_price' => $sale, 'stock' => $stock];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors, $request->all());
        }

        try {
            $this->variants->updateVariants($id, $rows);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }
        $this->flash('success', 'Đã lưu giá và tồn kho các biến thể.');
        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    /**
     * Sản phẩm có biến thể: giá và tồn kho là cột tổng hợp, tính từ biến thể => không nhận từ form.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $id = null, bool $hasVariants = false): array
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:190',
            'slug' => 'nullable|slug|max:190',
            'category_id' => 'nullable|integer|exists:categories,id',
            'sku' => 'nullable|string|max:50|unique:products,sku' . ($id ? ',' . $id : ''),
            'price' => ($hasVariants ? 'nullable' : 'required') . '|integer|min:0',
            'sale_price' => 'nullable|integer|min:0',
            'stock' => ($hasVariants ? 'nullable' : 'required') . '|integer|min:0',
            'status' => 'required|in:active,draft',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
        ], self::LABELS);

        if ($hasVariants) {
            unset($data['price'], $data['sale_price'], $data['stock']);
        } else {
            if ($data['sale_price'] !== null && (int) $data['sale_price'] <= 0) {
                $data['sale_price'] = null;
            } elseif ($data['sale_price'] !== null && $data['sale_price'] >= $data['price']) {
                throw ValidationException::withMessages(['sale_price' => 'Giá khuyến mãi phải nhỏ hơn giá gốc.'], $request->all());
            }
        }

        $data['is_featured'] = $request->input('is_featured') ? 1 : 0;
        $data['slug'] ??= '';
        return $data;
    }

    private function db(): Database
    {
        return Product::db();
    }

    private function handleImage(Request $request): ?string
    {
        $file = $request->file('image');
        if ($file === null) {
            return null;
        }
        try {
            return $this->uploader->image($file, 'products');
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()], $request->all());
        }
    }
}
