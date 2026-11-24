<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
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

    public function destroy(int $id): Response
    {
        Category::findOrFail($id);
        Category::deleteById($id); // products.category_id => NULL
        $this->flash('success', 'Đã xoá danh mục.');
        return $this->redirect('/admin/categories');
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
