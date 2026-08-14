<?php

namespace App\Services\Document;

use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DocumentQueryService
{
    /**
     * Query documents with efficient database filtering and pagination.
     */
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Document::with(['mitra:id,name,code', 'category:id,name', 'uploader:id,name']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['mitra_id'])) {
            $query->where('mitra_id', $filters['mitra_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }

        if (! empty($filters['tahun_berkas']) && $filters['tahun_berkas'] !== 'all') {
            $year = $filters['tahun_berkas'];
            $query->where(function ($q) use ($year) {
                $q->where('tahun_berkas', $year)
                  ->orWhereYear('tanggal_dokumen', $year)
                  ->orWhere(function ($sub) use ($year) {
                      $sub->whereNull('tahun_berkas')
                          ->whereNull('tanggal_dokumen')
                          ->whereYear('created_at', $year);
                  });
            });
        }

        if (! empty($filters['extension'])) {
            $ext = strtolower($filters['extension']);
            if ($ext === 'image' || $ext === 'gambar') {
                $query->whereIn('extension', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);
            } elseif ($ext === 'word') {
                $query->whereIn('extension', ['doc', 'docx']);
            } elseif ($ext === 'excel') {
                $query->whereIn('extension', ['xls', 'xlsx', 'csv']);
            } else {
                $query->where('extension', $ext);
            }
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
