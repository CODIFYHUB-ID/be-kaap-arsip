<?php

namespace App\Services\RecycleBin;

use App\Models\Category;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Mitra;
use App\Models\Receipt;

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
            'letters' => Letter::onlyTrashed()->with(['mitra:id,name'])->get(),
            'receipts' => Receipt::onlyTrashed()->with(['mitra:id,name'])->get(),
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
            'letter' => Letter::onlyTrashed()->find($id),
            'receipt' => Receipt::onlyTrashed()->find($id),
            'category' => Category::onlyTrashed()->find($id),
            default => null,
        };

        if ($model) {
            if ($type === 'document' && $model instanceof Document) {
                // Restore associated letter
                Letter::onlyTrashed()->where('document_id', $id)->restore();
            }

            if ($type === 'letter' && $model instanceof Letter && $model->document_id) {
                // Restore associated document
                Document::onlyTrashed()->where('id', $model->document_id)->restore();
            }

            return (bool) $model->restore();
        }

        return false;
    }

    /**
     * Permanently delete a record (and storage file if Document or Receipt).
     */
    public function forceDeleteItem(string $type, int $id): bool
    {
        $model = match ($type) {
            'document' => Document::onlyTrashed()->find($id),
            'mitra' => Mitra::onlyTrashed()->find($id),
            'letter' => Letter::onlyTrashed()->find($id),
            'receipt' => Receipt::onlyTrashed()->find($id),
            'category' => Category::onlyTrashed()->find($id),
            default => null,
        };

        if ($model) {
            if ($type === 'document' && $model instanceof Document) {
                // Delete actual file in R2
                if ($model->file_key) {
                    app(\App\Services\Storage\R2StorageService::class)->deleteObject($model->file_key);
                }
                // Also force delete associated letter
                Letter::onlyTrashed()->where('document_id', $id)->forceDelete();
            }

            if ($type === 'receipt' && $model instanceof Receipt) {
                if ($model->file_key) {
                    app(\App\Services\Storage\R2StorageService::class)->deleteObject($model->file_key);
                }
            }

            if ($type === 'letter' && $model instanceof Letter && $model->document_id) {
                $doc = Document::onlyTrashed()->find($model->document_id);
                if ($doc) {
                    if ($doc->file_key) {
                        app(\App\Services\Storage\R2StorageService::class)->deleteObject($doc->file_key);
                    }
                    $doc->forceDelete();
                }
            }

            return (bool) $model->forceDelete();
        }

        return false;
    }
}
