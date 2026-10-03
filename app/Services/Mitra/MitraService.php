<?php

namespace App\Services\Mitra;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Enums\UserStatus;
use App\Models\Mitra;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class MitraService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 20, ?User $currentUser = null): LengthAwarePaginator
    {
        $currentUser = $currentUser ?? auth()->user();

        $query = Mitra::withCount(['documents'])
            ->withSum('documents as total_size', 'file_size')
            ->with([
                'user:id,name,email,status,last_login_at',
                'creator:id,name',
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(array $data, ?User $currentUser = null): Mitra
    {
        $currentUser = $currentUser ?? auth()->user();

        if ($currentUser) {
            $data['created_by'] = $currentUser->id;
        }

        unset($data['password']);

        $mitra = Mitra::create($data);

        $this->activityLogService->log(
            userId: $currentUser?->id ?? auth()->id(),
            action: ActivityAction::CREATE,
            module: ActivityModule::MITRA,
            description: "Menambahkan mitra baru: {$mitra->name} ({$mitra->code})",
            resourceType: 'Mitra',
            resourceId: $mitra->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $mitra->load(['user:id,name,email,status,last_login_at', 'creator:id,name']);
    }

    public function update(Mitra $mitra, array $data, ?User $currentUser = null): Mitra
    {
        $currentUser = $currentUser ?? auth()->user();
        unset($data['password']);

        $mitra->update($data);

        $this->activityLogService->log(
            userId: $currentUser?->id ?? auth()->id(),
            action: ActivityAction::UPDATE,
            module: ActivityModule::MITRA,
            description: "Memperbarui data mitra: {$mitra->name} ({$mitra->code})",
            resourceType: 'Mitra',
            resourceId: $mitra->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return $mitra->load(['user:id,name,email,status,last_login_at', 'creator:id,name']);
    }

    public function delete(Mitra $mitra, ?User $currentUser = null): bool
    {
        $currentUser = $currentUser ?? auth()->user();
        $name = $mitra->name;
        $code = $mitra->code;
        $id = $mitra->id;

        // If user account is linked, soft delete user as well
        if ($mitra->user_id) {
            User::where('id', $mitra->user_id)->delete();
        }

        $deleted = $mitra->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: $currentUser?->id ?? auth()->id(),
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
