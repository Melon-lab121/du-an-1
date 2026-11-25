<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;

final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $editId = (int) $request->query('edit', 0);
        return $this->view('admin/categories/index', [
            'categories' => Category::withProductCount(),
            'editing' => $editId > 0 ? Category::find($editId) : null,
            'trashCount' => Category::onlyTrashed()->count(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request);
        Category::create($data);
        $this->flash('success', 'Đã thêm danh mục.');
        return $this->redirect('/admin/categories');
    }

    public function update(Request $request, int $id): Response
    {
        Category::findOrFail($id);
        Category::updateById($id, $this->validated($request, $id));
        $this->flash('success', 'Đã cập nhật danh mục.');
        return $this->redirect('/admin/categories');
    }

    /** Xoá mềm: danh mục biến khỏi web khách và vào thùng rác. Sản phẩm vẫn giữ liên kết. */
    public function destroy(int $id): Response
    {
        $category = Category::findOrFail($id);
        Category::deleteById($id);
        $this->flash('success', 'Đã chuyển "' . $category['name'] . '" vào thùng rác. Có thể khôi phục trong mục Thùng rác.');
        return $this->redirect('/admin/categories');
    }

    public function trash(Request $request): Response
    {
        return $this->view('admin/categories/trash', [
            'pager' => Category::onlyTrashed()->orderBy('deleted_at', 'DESC')
                ->paginate(15, max(1, (int) $request->query('page', 1))),
        ]);
    }

    public function restore(int $id): Response
    {
        $category = Category::onlyTrashed()->find($id) ?? throw new HttpException(404);
        Category::restore($id);
        $restored = Category::findOrFail($id);
        $note = $restored['slug'] !== self::originalSlug($category)
            ? ' Slug cũ đã có danh mục khác dùng, hệ thống đã đặt slug mới.'
            : '';
        $this->flash('success', 'Đã khôi phục "' . $restored['name'] . '".' . $note);
        return $this->redirect('/admin/categories/trash');
    }

    /** Xoá hẳn. Sản phẩm thuộc danh mục này chuyển về không danh mục (khoá ngoại SET NULL). */
    public function forceDestroy(int $id): Response
    {
        $category = Category::onlyTrashed()->find($id) ?? throw new HttpException(404);
        Category::forceDeleteById($id);
        $this->flash('success', 'Đã xoá vĩnh viễn "' . $category['name'] . '".');
        return $this->redirect('/admin/categories/trash');
    }

    /** @param array<string, mixed> $category */
    private static function originalSlug(array $category): string
    {
        $suffix = '--xoa-' . (int) $category['id'];
        $slug = (string) $category['slug'];
        return str_ends_with($slug, $suffix) ? substr($slug, 0, -strlen($suffix)) : $slug;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
            'slug' => 'nullable|slug|max:120|unique:categories,slug' . ($id ? ',' . $id : ''),
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
        ], ['name' => 'Tên danh mục', 'slug' => 'Slug', 'sort_order' => 'Thứ tự']);

        $data['slug'] = $data['slug'] ?: str_slug($data['name']);
        $data['sort_order'] ??= 0;
        return $data;
    }
}
