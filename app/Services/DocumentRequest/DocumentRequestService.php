<?php

namespace App\Services\DocumentRequest;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DocumentRequestService
{
    /**
     * Get paginated document requests based on filters and user role scoping
     */
    public function getPaginated(array $filters, int $perPage = 20, ?User $currentUser = null): LengthAwarePaginator
    {
        $currentUser = $currentUser ?? auth()->user();
        $query = DocumentRequest::with([
            'mitra:id,name,code,company_name',
            'category:id,name',
            'creator:id,name',
            'document:id,file_name,file_key,extension,file_size',
            'reviewer:id,name'
        ]);

        // Scoping for Klien (hanya melihat permintaan perusahaannya)
        if ($currentUser && $currentUser->isKlien() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $query->where('mitra_id', $currentUser->mitra_id);
        }

        // Scoping for Mitra (melihat permintaan dari klien binaannya atau yang dibuat olehnya)
        if ($currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $query->where(function ($q) use ($currentUser) {
                $q->where('created_by', $currentUser->id)
                  ->orWhereHas('mitra', function ($mq) use ($currentUser) {
                      $mq->where('created_by', $currentUser->id);
                  });
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['mitra_id'])) {
            $query->where('mitra_id', $filters['mitra_id']);
        }

        if (! empty($filters['tahun_buku'])) {
            $query->where('tahun_buku', $filters['tahun_buku']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('request_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('mitra', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new document request
     */
    public function create(array $data, int $userId): DocumentRequest
    {
        $year = now()->format('Y');
        $randomSeq = strtoupper(Str::random(4));
        $countToday = DocumentRequest::whereDate('created_at', now()->toDateString())->count() + 1;
        $reqNumber = sprintf("REQ-%s-%03d-%s", $year, $countToday, $randomSeq);

        $data['request_number'] = $reqNumber;
        $data['created_by'] = $userId;
        $data['status'] = 'requested';

        $req = DocumentRequest::create($data);

        return $req->load(['mitra', 'category', 'creator']);
    }

    /**
     * Client fulfills request by attaching uploaded document
     */
    public function fulfill(DocumentRequest $request, int $documentId): DocumentRequest
    {
        $request->update([
            'document_id' => $documentId,
            'status' => 'client_uploaded',
        ]);

        return $request->load(['mitra', 'category', 'document']);
    }

    /**
     * Review the request fulfillment (approve or request revision)
     */
    public function review(DocumentRequest $request, string $action, ?string $notes, int $reviewerId): DocumentRequest
    {
        $status = $action === 'approve' ? 'approved' : 'revision_needed';

        $request->update([
            'status' => $status,
            'review_notes' => $notes,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        // Jika diapprove dan ada document terhubung, update review_status dokumen jadi approved
        if ($action === 'approve' && $request->document_id) {
            $request->document()->update([
                'review_status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);
        }

        return $request->load(['mitra', 'category', 'document', 'reviewer']);
    }
}
