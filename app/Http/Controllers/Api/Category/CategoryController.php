<?php

namespace App\Http\Controllers\Api\Category;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Category\CategoryService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(): JsonResponse
    {
        $categories = $this->categoryService->getAll();
        return $this->success($categories, 'Daftar kategori berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = $this->categoryService->create($validated);
        return $this->success($category, 'Kategori berhasil ditambahkan.', 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $updated = $this->categoryService->update($category, $validated);
        return $this->success($updated, 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);
        return $this->success(null, 'Kategori berhasil dihapus.');
    }
}
