<?php

namespace App\Services\Mitra;

use App\Models\Mitra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MitraService
{
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Mitra::withCount(['documents', 'letters'])->withSum('documents as total_size', 'file_size');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(array $data): Mitra
    {
        return Mitra::create($data);
    }

    public function update(Mitra $mitra, array $data): Mitra
    {
        $mitra->update($data);
        return $mitra;
    }

    public function delete(Mitra $mitra): bool
    {
        return $mitra->delete();
    }
}
