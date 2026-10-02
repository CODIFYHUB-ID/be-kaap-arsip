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

        $query = Mitra::withCount(['documents', 'letters'])
            ->withSum('documents as total_size', 'file_size')
            ->with([
                'user:id,name,email,status,last_login_at',
                'creator:id,name',
            ]);

        // If the authenticated user is a Mitra, scope to their own mitra profile and any clients/mitras created by them
        if ($currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $query->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id);
            });
        }

        // If the authenticated user is an Auditor, scope strictly to their assigned active clients
        if ($currentUser && $currentUser->isAuditor() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $currentUser->id)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            $query->whereIn('id', $assignedMitraIds);
        }

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

        // If created by someone who is a Mitra, mark created_by
        if ($currentUser) {
            $data['created_by'] = $currentUser->id;
        }

        $password = $data['password'] ?? null;
        unset($data['password']);

        $mitra = Mitra::create($data);

        // If email and password provided, create login account for Mitra
        if (! empty($mitra->email) && ! empty($password)) {
            $user = User::where('email', $mitra->email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $mitra->name,
                    'email' => $mitra->email,
                    'password' => Hash::make($password),
                    'status' => $mitra->status === 'inactive' ? UserStatus::INACTIVE : UserStatus::ACTIVE,
                    'mitra_id' => $mitra->id,
                ]);
                $user->assignRole('Mitra');
            } else {
                $user->update([
                    'mitra_id' => $mitra->id,
                    'password' => Hash::make($password),
                ]);
                if (! $user->hasRole('Mitra')) {
                    $user->assignRole('Mitra');
                }
            }

            $mitra->update(['user_id' => $user->id]);
        }

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
        $password = $data['password'] ?? null;
        unset($data['password']);

        $mitra->update($data);

        // Synchronize user account if exists or create if password provided
        if (! empty($password)) {
            if ($mitra->user_id && $user = User::find($mitra->user_id)) {
                $userUpdate = ['password' => Hash::make($password)];
                if (! empty($mitra->email)) {
                    $userUpdate['email'] = $mitra->email;
                }
                if ($mitra->status) {
                    $userUpdate['status'] = $mitra->status === 'inactive' ? UserStatus::INACTIVE : UserStatus::ACTIVE;
                }
                $user->update($userUpdate);
                if (! $user->hasRole('Mitra')) {
                    $user->assignRole('Mitra');
                }
            } elseif (! empty($mitra->email)) {
                $user = User::firstOrCreate(
                    ['email' => $mitra->email],
                    [
                        'name' => $mitra->name,
                        'password' => Hash::make($password),
                        'status' => $mitra->status === 'inactive' ? UserStatus::INACTIVE : UserStatus::ACTIVE,
                        'mitra_id' => $mitra->id,
                    ]
                );
                $user->update(['mitra_id' => $mitra->id, 'password' => Hash::make($password)]);
                if (! $user->hasRole('Mitra')) {
                    $user->assignRole('Mitra');
                }
                $mitra->update(['user_id' => $user->id]);
            }
        } elseif ($mitra->user_id && $user = User::find($mitra->user_id)) {
            // Update email or status on user if changed
            $userUpdate = [];
            if (! empty($mitra->email) && $user->email !== $mitra->email) {
                $userUpdate['email'] = $mitra->email;
            }
            if ($mitra->status) {
                $userUpdate['status'] = $mitra->status === 'inactive' ? UserStatus::INACTIVE : UserStatus::ACTIVE;
            }
            if (! empty($userUpdate)) {
                $user->update($userUpdate);
            }
        }

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
