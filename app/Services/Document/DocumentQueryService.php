<?php

namespace App\Services\Document;

use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DocumentQueryService
{
    /**
     * Query documents with efficient database filtering and pagination.
     */
    public function getPaginated(array $filters = [], int $perPage = 20, ?\App\Models\User $currentUser = null): LengthAwarePaginator
    {
        $currentUser = $currentUser ?? auth()->user();
        $query = Document::with(['mitra:id,name,code,company_name', 'category:id,name', 'uploader:id,name', 'reviewer:id,name']);

        // Scope to Mitra's own documents if logged in as Mitra
        if ($currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $query->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('uploaded_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
        }

        // Scope to Auditor assigned clients if logged in as Auditor
        if ($currentUser && $currentUser->isAuditor() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $currentUser->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            $query->where(function ($q) use ($assignedMitraIds, $currentUser) {
                $q->whereIn('mitra_id', $assignedMitraIds)
                  ->orWhere('uploaded_by', $currentUser->id);
            });
        }

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

        if (isset($filters['is_working_paper']) && $filters['is_working_paper'] !== '' && $filters['is_working_paper'] !== 'all') {
            $isWp = filter_var($filters['is_working_paper'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_working_paper', $isWp);
        }

        if (! empty($filters['review_status']) && $filters['review_status'] !== 'all') {
            $query->where('review_status', $filters['review_status']);
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
