<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\DiscountType;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\VoucherType;

final class VoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $vouchers = Voucher::query()->orderBy('id', 'DESC')->get();

        foreach ($vouchers as &$v) {
            $v['voucher_type_label'] = VoucherType::label((int) $v['voucher_type']);
            $v['discount_type_label'] = DiscountType::label((int) $v['discount_type']);
            $v['total_usage'] = Voucher::totalUsage((string) $v['code']);
        }
        unset($v);

        return $this->view('admin/vouchers/index', [
            'vouchers' => $vouchers,
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin/vouchers/form', [
            'editing' => null,
            'voucherTypes' => VoucherType::allTypes(),
            'discountTypes' => DiscountType::allTypes(),
            'categories' => Category::ordered(),
            'products' => Product::active()->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'selectedProductIds' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request);

        $vData = [
            'code' => $data['code'],
            'voucher_type' => $data['voucher_type'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'max_discount_amount' => $data['max_discount_amount'],
            'min_spending' => $data['min_spending'],
            'max_usage_per_user' => $data['max_usage_per_user'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'],
        ];

        $voucherId = Voucher::create($vData);

        if ($data['voucher_type'] === VoucherType::CATEGORY) {
            Voucher::syncCategories($voucherId, (array) ($request->input('category_ids') ?? []));
        } elseif ($data['voucher_type'] === VoucherType::PRODUCT) {
            Voucher::syncProducts($voucherId, (array) ($request->input('product_ids') ?? []));
        }

        $this->flash('success', 'Đã tạo mã voucher mới: ' . $data['code']);
        return $this->redirect('/admin/vouchers');
    }

    public function edit(int $id): Response
    {
        $voucher = Voucher::findOrFail($id);

        return $this->view('admin/vouchers/form', [
            'editing' => $voucher,
            'voucherTypes' => VoucherType::allTypes(),
            'discountTypes' => DiscountType::allTypes(),
            'categories' => Category::ordered(),
            'products' => Product::active()->orderBy('name')->get(),
            'selectedCategoryIds' => Voucher::categoryIds($id),
            'selectedProductIds' => Voucher::productIds($id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        Voucher::findOrFail($id);
        $data = $this->validated($request, $id);

        $vData = [
            'code' => $data['code'],
            'voucher_type' => $data['voucher_type'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'max_discount_amount' => $data['max_discount_amount'],
            'min_spending' => $data['min_spending'],
            'max_usage_per_user' => $data['max_usage_per_user'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'],
        ];

        Voucher::updateById($id, $vData);

        if ($data['voucher_type'] === VoucherType::CATEGORY) {
            Voucher::syncCategories($id, (array) ($request->input('category_ids') ?? []));
            Voucher::syncProducts($id, []);
        } elseif ($data['voucher_type'] === VoucherType::PRODUCT) {
            Voucher::syncProducts($id, (array) ($request->input('product_ids') ?? []));
            Voucher::syncCategories($id, []);
        } else {
            Voucher::syncCategories($id, []);
            Voucher::syncProducts($id, []);
        }

        $this->flash('success', 'Đã cập nhật mã voucher: ' . $data['code']);
        return $this->redirect('/admin/vouchers');
    }

    public function toggle(int $id): Response
    {
        $voucher = Voucher::findOrFail($id);
        $newStatus = (int) $voucher['is_active'] ? 0 : 1;

        Voucher::updateById($id, ['is_active' => $newStatus]);

        $this->flash('success', 'Đã ' . ($newStatus ? 'kích hoạt' : 'tạm ngưng') . ' mã voucher ' . $voucher['code'] . '.');
        return $this->redirect('/admin/vouchers');
    }

    public function destroy(int $id): Response
    {
        $voucher = Voucher::findOrFail($id);
        Voucher::deleteById($id);
        Voucher::syncCategories($id, []);
        Voucher::syncProducts($id, []);

        $this->flash('success', 'Đã xóa mã voucher: ' . $voucher['code']);
        return $this->redirect('/admin/vouchers');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        $uniqueRule = 'unique:vouchers,code' . ($id ? ',' . $id : '');

        $data = $this->validate($request, [
            'code' => 'required|string|max:50|' . $uniqueRule,
            'voucher_type' => 'required|integer',
            'discount_type' => 'required|integer',
            'discount_value' => 'required|integer|min:1',
            'max_discount_amount' => 'nullable|integer|min:0',
            'min_spending' => 'nullable|integer|min:0',
            'max_usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'required|string',
            'end_date' => 'required|string',
        ], [
            'code' => 'Mã voucher',
            'voucher_type' => 'Loại voucher',
            'discount_type' => 'Kiểu giảm giá',
            'discount_value' => 'Giá trị giảm',
            'min_spending' => 'Chi tiêu tối thiểu',
            'max_usage_per_user' => 'Lượt dùng tối đa mỗi khách',
            'start_date' => 'Ngày bắt đầu',
            'end_date' => 'Ngày kết thúc',
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['voucher_type'] = (int) $data['voucher_type'];
        $data['discount_type'] = (int) $data['discount_type'];
        $data['discount_value'] = (int) $data['discount_value'];
        $data['max_discount_amount'] = !empty($request->input('max_discount_amount')) ? (int) $request->input('max_discount_amount') : null;
        $data['min_spending'] = (int) ($data['min_spending'] ?? 0);
        $data['max_usage_per_user'] = (int) ($data['max_usage_per_user'] ?? 1);
        $data['is_active'] = $request->input('is_active') ? 1 : 0;

        // Chuẩn hoá định dạng datetime
        $data['start_date'] = str_contains($data['start_date'], ' ') ? $data['start_date'] : $data['start_date'] . ' 00:00:00';
        $data['end_date'] = str_contains($data['end_date'], ' ') ? $data['end_date'] : $data['end_date'] . ' 23:59:59';

        return $data;
    }
}
