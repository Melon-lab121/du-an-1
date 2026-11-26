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
        'name' => 'Tên sản phẩm',
        'slug' => 'Slug',
        'category_id' => 'Danh mục',
        'sku' => 'SKU',
        'price' => 'Giá',
        'sale_price' => 'Giá khuyến mãi',
        'stock' => 'Tồn kho',
        'status' => 'Trạng thái',
        'short_description' => 'Mô tả ngắn',
        'description' => 'Mô tả',
    ];

    public function __construct(private Uploader $uploader, private VariantService $variants) {}

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

        $ids = array_map(fn(array $p): int => (int) $p['id'], $pager['data']);
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
        $data['image'] = $this->resolveProductImage($request, null);

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
            'trashedVariants' => $this->variants->trashedForProduct($id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $product = Product::findOrFail($id);
        $data = $this->validated($request, $id, (bool) $product['has_variants']);
        $data['slug'] = Product::uniqueSlug($data['slug'] ?: $data['name'], $id);

        $data['image'] = $this->resolveProductImage($request, $product['image'] ?? null);

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
            : sprintf(
                'Đã cập nhật phân loại: %d mới, %d khôi phục, %d giữ nguyên, %d chuyển vào thùng rác.',
                $stats['created'],
                $stats['restored'],
                $stats['kept'],
                $stats['trashed']
            ));
        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    /** Lưu SKU / giá / giá KM / tồn kho / ảnh (file hoặc URL) của từng biến thể. */
    public function saveVariants(Request $request, int $id): Response
    {
        Product::findOrFail($id);
        $input = $request->input('variants', []);
        $rows = [];
        $errors = [];

        $variantImagesFiles = $request->files()['variant_images'] ?? null;
        $nestedVariantFiles = $request->files()['variants'] ?? null;

        foreach (is_array($input) ? $input : [] as $variantId => $row) {
            $row = is_array($row) ? $row : [];
            $vid = (int) $variantId;
            if ($vid <= 0) {
                continue;
            }

            $price = filter_var($row['price'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $stock = filter_var($row['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $sale = trim((string) ($row['sale_price'] ?? '')) === ''
                ? null
                : filter_var($row['sale_price'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $sku = trim((string) ($row['sku'] ?? ''));
            $imageUrl = trim((string) ($row['image_url'] ?? ''));

            if ($price === false || $stock === false || $sale === false || mb_strlen($sku) > 64) {
                $errors['variants'] = 'Giá, giá KM, tồn kho phải là số nguyên ≥ 0; SKU tối đa 64 ký tự.';
                continue;
            }

            $data = [
                'sku' => $sku === '' ? null : $sku,
                'price' => (int) $price,
                'sale_price' => $sale === null ? null : (int) $sale,
                'stock' => (int) $stock,
            ];

            $uploadedPath = null;
            if (is_array($variantImagesFiles) && isset($variantImagesFiles['tmp_name'][$vid]) && (int) ($variantImagesFiles['error'][$vid] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                try {
                    $singleFile = [
                        'name' => $variantImagesFiles['name'][$vid] ?? 'variant.jpg',
                        'type' => $variantImagesFiles['type'][$vid] ?? '',
                        'tmp_name' => $variantImagesFiles['tmp_name'][$vid],
                        'error' => $variantImagesFiles['error'][$vid],
                        'size' => $variantImagesFiles['size'][$vid] ?? 0,
                    ];
                    $uploadedPath = $this->uploader->image($singleFile, 'variants');
                } catch (DomainException $e) {
                    $errors['variants'] = $e->getMessage();
                }
            } elseif (is_array($nestedVariantFiles) && isset($nestedVariantFiles['tmp_name'][$vid]['image']) && (int) ($nestedVariantFiles['error'][$vid]['image'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                try {
                    $singleFile = [
                        'name' => $nestedVariantFiles['name'][$vid]['image'] ?? 'variant.jpg',
                        'type' => $nestedVariantFiles['type'][$vid]['image'] ?? '',
                        'tmp_name' => $nestedVariantFiles['tmp_name'][$vid]['image'],
                        'error' => $nestedVariantFiles['error'][$vid]['image'],
                        'size' => $nestedVariantFiles['size'][$vid]['image'] ?? 0,
                    ];
                    $uploadedPath = $this->uploader->image($singleFile, 'variants');
                } catch (DomainException $e) {
                    $errors['variants'] = $e->getMessage();
                }
            }

            if ($uploadedPath !== null) {
                $data['image'] = $uploadedPath;
            } elseif ($imageUrl !== '') {
                if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) {
                    $errors['variants'] = 'Link ảnh biến thể phải bắt đầu bằng http:// hoặc https://.';
                    continue;
                }
                $data['image'] = $imageUrl;
            }

            $rows[$vid] = $data;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors, $request->all());
        }

        try {
            $this->variants->updateVariants($id, $rows);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }

        $this->flash('success', 'Đã lưu cấu hình giá, tồn kho và phân loại biến thể.');
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

    private function resolveProductImage(Request $request, ?string $currentImage): ?string
    {
        // 1. Ưu tiên file tải lên nếu có
        $uploaded = $this->handleImage($request);
        if ($uploaded !== null) {
            if ($currentImage && !preg_match('#^https?://#i', $currentImage)) {
                $this->uploader->delete($currentImage);
            }
            return $uploaded;
        }

        // 2. Kiểm tra link ảnh nhập tay
        $rawUrl = trim((string) $request->input('image_url', ''));
        if ($rawUrl !== '') {
            $isValidUrl = filter_var($rawUrl, FILTER_VALIDATE_URL) !== false
                && preg_match('#^https?://#i', $rawUrl) === 1;
            $isLocalPath = str_starts_with($rawUrl, '/');

            if (!$isValidUrl && !$isLocalPath) {
                throw ValidationException::withMessages([
                    'image_url' => 'Link ảnh không hợp lệ (chỉ hỗ trợ http://, https:// hoặc đường dẫn bắt đầu bằng /).',
                ], $request->all());
            }

            if ($currentImage && $currentImage !== $rawUrl && !preg_match('#^https?://#i', $currentImage)) {
                $this->uploader->delete($currentImage);
            }

            return $rawUrl;
        }

        // 3. Không tải file mới và để trống ô URL -> giữ ảnh hiện tại khi sửa
        return $currentImage;
    }

    /** Thêm nhiều biến thể mới cùng lúc cho sản phẩm (hỗ trợ ảnh file hoặc URL). */
    public function batchStoreVariants(Request $request, int $id): Response
    {
        Product::findOrFail($id);
        $rawItems = $request->input('new_variants', []);
        $errors = [];
        $items = [];

        $files = $request->files()['new_variants'] ?? null;

        foreach (is_array($rawItems) ? $rawItems : [] as $idx => $row) {
            $row = is_array($row) ? $row : [];
            $title = trim((string) ($row['title'] ?? ''));
            $sku = trim((string) ($row['sku'] ?? ''));
            $price = filter_var($row['price'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $saleRaw = trim((string) ($row['sale_price'] ?? ''));
            $sale = $saleRaw === '' ? null : filter_var($saleRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $stock = filter_var($row['stock'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $imageUrl = trim((string) ($row['image_url'] ?? ''));

            if ($title === '' && $sku === '' && $imageUrl === '') {
                continue;
            }
            if ($title === '' || mb_strlen($title) > 255) {
                $errors['variants'] = 'Tên biến thể bắt buộc và tối đa 255 ký tự.';
                continue;
            }
            if ($price === false || $stock === false || $sale === false || mb_strlen($sku) > 64) {
                $errors['variants'] = 'Giá, giá KM, tồn kho phải là số nguyên ≥ 0; SKU tối đa 64 ký tự.';
                continue;
            }

            $image = null;
            if (
                is_array($files)
                && isset($files['tmp_name'][$idx]['image'])
                && (int) ($files['error'][$idx]['image'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            ) {
                try {
                    $singleFile = [
                        'name' => $files['name'][$idx]['image'] ?? 'variant.jpg',
                        'type' => $files['type'][$idx]['image'] ?? '',
                        'tmp_name' => $files['tmp_name'][$idx]['image'],
                        'error' => $files['error'][$idx]['image'],
                        'size' => $files['size'][$idx]['image'] ?? 0,
                    ];
                    $image = $this->uploader->image($singleFile, 'variants');
                } catch (DomainException $e) {
                    $errors['variants'] = $e->getMessage();
                }
            } elseif ($imageUrl !== '') {
                if (!filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl)) {
                    $errors['variants'] = 'Link ảnh biến thể phải bắt đầu bằng http:// hoặc https://.';
                    continue;
                }
                $image = $imageUrl;
            }

            $items[] = [
                'title' => $title,
                'sku' => $sku === '' ? null : $sku,
                'price' => (int) $price,
                'sale_price' => $sale === null ? null : (int) $sale,
                'stock' => (int) $stock,
                'image' => $image,
            ];
        }

        if ($items === [] && $errors === []) {
            $errors['variants'] = 'Vui lòng nhập ít nhất một biến thể để thêm.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors, $request->all());
        }

        try {
            $count = $this->variants->addVariants($id, $items);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }

        $this->flash('success', "Đã thêm {$count} biến thể mới.");
        return $this->redirect('/admin/products/' . $id . '/edit#product-variants-manager');
    }

    /** Sửa nhanh một biến thể ngay trên trang sửa sản phẩm. */
    public function quickUpdateVariant(Request $request, int $id, int $variantId): Response
    {
        Product::findOrFail($id);

        $title = trim((string) $request->input('title', ''));
        $sku = trim((string) $request->input('sku', ''));
        $price = filter_var($request->input('price'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $stock = filter_var($request->input('stock'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $saleRaw = trim((string) $request->input('sale_price', ''));
        $sale = $saleRaw === ''
            ? null
            : filter_var($saleRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($price === false || $stock === false || $sale === false || mb_strlen($sku) > 64 || mb_strlen($title) > 120) {
            throw ValidationException::withMessages([
                'variants' => 'Dữ liệu biến thể không hợp lệ (giá, giá KM, tồn kho >= 0; SKU <= 64 ký tự).',
            ], $request->all());
        }

        $payload = [
            'title' => $title,
            'sku' => $sku === '' ? null : $sku,
            'price' => (int) $price,
            'sale_price' => $sale === null ? null : (int) $sale,
            'stock' => (int) $stock,
        ];

        $imageUrl = trim((string) $request->input('image_url', ''));
        if ($imageUrl !== '') {
            if (!preg_match('#^https?://#i', $imageUrl)) {
                throw ValidationException::withMessages([
                    'variants' => 'URL ảnh biến thể phải bắt đầu bằng http:// hoặc https://.',
                ], $request->all());
            }
            $payload['image'] = $imageUrl;
        }

        $file = $request->files()['image_file'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $uploadedPath = $this->uploader->image($file, 'variants');
                if ($uploadedPath !== null) {
                    $payload['image'] = $uploadedPath;
                }
            } catch (DomainException $e) {
                throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
            }
        }

        try {
            $this->variants->quickUpdateVariant($id, $variantId, $payload);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }

        $this->flash('success', 'Đã cập nhật nhanh biến thể.');
        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    /** Xoá mềm biến thể */
    public function softDeleteVariant(Request $request, int $id, int $variantId): Response
    {
        Product::findOrFail($id);

        try {
            $this->variants->softDeleteVariant($id, $variantId);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }

        $this->flash('success', 'Đã chuyển biến thể vào thùng rác (xoá mềm).');
        return $this->redirect('/admin/products/' . $id . '/edit');
    }

    /** Khôi phục biến thể đã xoá mềm -> chuyển hướng tới thùng rác biến thể. */
    public function restoreVariant(Request $request, int $id, int $variantId): Response
    {
        Product::findOrFail($id);

        try {
            $this->variants->restoreVariant($id, $variantId);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }

        $this->flash('success', 'Đã khôi phục biến thể.');
        return $this->redirect('/admin/products/' . $id . '/variants/trash');
    }

    /** Xoá vĩnh viễn biến thể -> chuyển hướng tới thùng rác biến thể. */
    public function forceDeleteVariant(Request $request, int $id, int $variantId): Response
    {
        Product::findOrFail($id);

        try {
            $this->variants->forceDeleteVariant($id, $variantId);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['variants' => $e->getMessage()], $request->all());
        }

        $this->flash('success', 'Đã xoá vĩnh viễn biến thể.');
        return $this->redirect('/admin/products/' . $id . '/variants/trash');
    }

    /** Trang thùng rác biến thể đã xoá mềm của sản phẩm. */
    public function variantsTrash(Request $request, int $id): Response
    {
        $product = Product::findOrFail($id);

        return $this->view('admin/products/variants-trash', [
            'product' => $product,
            'trashedVariants' => $this->variants->trashedForProduct($id),
        ]);
    }
}
