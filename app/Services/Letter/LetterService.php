<?php

namespace App\Services\Letter;

use App\Models\Letter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LetterService
{
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Letter::with([
            'mitra:id,name,code,company_name',
            'category:id,name',
            'document:id,file_name,file_size,mime_type,extension',
            'creator:id,name',
        ]);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('letter_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['mitra_id'])) {
            $query->where('mitra_id', $filters['mitra_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['year']) && $filters['year'] !== 'all') {
            $year = $filters['year'];
            $query->where(function ($q) use ($year) {
                $q->whereYear('letter_date', $year)
                  ->orWhereYear('received_date', $year)
                  ->orWhereYear('created_at', $year);
            });
        }

        return $query->orderByDesc('letter_date')->paginate($perPage);
    }

    public function create(array $data, int $userId): Letter
    {
        $data['created_by'] = $userId;
        return Letter::create($data)->load(['mitra', 'category', 'document', 'creator']);
    }

    public function update(Letter $letter, array $data): Letter
    {
        $letter->update($data);
        return $letter->load(['mitra', 'category', 'document', 'creator']);
    }

    public function delete(Letter $letter): bool
    {
        if ($letter->document_id) {
            \App\Models\Document::where('id', $letter->document_id)->delete();
        }
        return $letter->delete();
    }
}
