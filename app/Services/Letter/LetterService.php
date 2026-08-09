<?php

namespace App\Services\Letter;

use App\Models\Letter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LetterService
{
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Letter::with(['mitra:id,name', 'category:id,name', 'document:id,file_name', 'creator:id,name']);

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
        return $letter->delete();
    }
}
