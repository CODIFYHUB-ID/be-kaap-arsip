<?php

namespace App\Services\Category;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\Category;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function getAll(): Collection
    {
        return Category::withCount('documents')
            ->withSum('documents as total_size', 'file_size')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Category
    {
        $category = Category::create($data);

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::CREATE,
            module: ActivityModule::CATEGORY,
            description: "Menambahkan kategori berkas baru: {$category->name} ({$category->code})",
            resourceType: 'Category',
            resourceId: $category->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::UPDATE,
            module: ActivityModule::CATEGORY,
            description: "Memperbarui kategori berkas: {$category->name} ({$category->code})",
            resourceType: 'Category',
            resourceId: $category->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $category;
    }

    public function delete(Category $category): bool
    {
        $name = $category->name;
        $code = $category->code;
        $id = $category->id;
        $deleted = $category->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: auth()->id(),
                action: ActivityAction::DELETE,
                module: ActivityModule::CATEGORY,
                description: "Menghapus kategori berkas: {$name} ({$code})",
                resourceType: 'Category',
                resourceId: $id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent()
            );
        }

        return $deleted;
    }
}
