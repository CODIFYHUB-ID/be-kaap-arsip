<?php

namespace App\Services\RecycleBin;

use App\Models\Category;
use App\Models\Document;
use App\Models\Mitra;

class RecycleBinService
{
    /**
     * Get soft deleted records across models.
     */
    public function getDeletedItems(): array
    {
        return [
            'documents' => Document::onlyTrashed()->with(['mitra:id,name', 'category:id,name'])->get(),
            'mitras' => Mitra::onlyTrashed()->get(),
            'categories' => Category::onlyTrashed()->get(),
        ];
    }

    /**
     * Restore a soft-deleted item by type and ID.
     */
    public function restoreItem(string $type, int $id): bool
    {
        $model = match ($type) {
            'document' => Document::onlyTrashed()->find($id),
            'mitra' => Mitra::onlyTrashed()->find($id),
            'category' => Category::onlyTrashed()->find($id),
            default => null,
        };

        if ($model) {
            return (bool) $model->restore();
        }

        return false;
    }

    /**
     * Permanently delete a record (and storage file if Document).
     */
    public function forceDeleteItem(string $type, int $id): bool
    {
        $model = match ($type) {
            'document' => Document::onlyTrashed()->find($id),
            'mitra' => Mitra::onlyTrashed()->find($id),
            'category' => Category::onlyTrashed()->find($id),
            default => null,
        };

        if ($model) {
            if ($type === 'document' && $model instanceof Document) {
                // Delete actual file in R2
                if ($model->file_key) {
                    app(\App\Services\Storage\R2StorageService::class)->deleteObject($model->file_key);
                }
            }

            return (bool) $model->forceDelete();
        }

        return false;
    }
}
