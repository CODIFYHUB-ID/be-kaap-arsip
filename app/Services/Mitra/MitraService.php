<?php

namespace App\Services\Mitra;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\Mitra;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MitraService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

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
        $mitra = Mitra::create($data);

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::CREATE,
            module: ActivityModule::MITRA,
            description: "Menambahkan mitra baru: {$mitra->name} ({$mitra->code})",
            resourceType: 'Mitra',
            resourceId: $mitra->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $mitra;
    }

    public function update(Mitra $mitra, array $data): Mitra
    {
        $mitra->update($data);

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::UPDATE,
            module: ActivityModule::MITRA,
            description: "Memperbarui data mitra: {$mitra->name} ({$mitra->code})",
            resourceType: 'Mitra',
            resourceId: $mitra->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $mitra;
    }

    public function delete(Mitra $mitra): bool
    {
        $name = $mitra->name;
        $code = $mitra->code;
        $id = $mitra->id;
        $deleted = $mitra->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: auth()->id(),
                action: ActivityAction::DELETE,
                module: ActivityModule::MITRA,
                description: "Menghapus data mitra: {$name} ({$code})",
                resourceType: 'Mitra',
                resourceId: $id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent()
            );
        }

        return $deleted;
    }
}
