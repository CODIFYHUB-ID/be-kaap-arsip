<?php

namespace App\Services\User;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function getUsers(array $filters = []): array
    {
        $query = User::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $users = $query->with('mitra:id,name,code')->latest()->paginate($perPage);

        $mappedData = collect($users->items())->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'mitra_id' => $user->mitra_id,
                'mitra' => $user->mitra,
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ];
        });

        return [
            'data' => $mappedData,
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'last_page' => $users->lastPage(),
        ];
    }

    public function createUser(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? 'active',
            'mitra_id' => $data['mitra_id'] ?? null,
        ]);

        if (!empty($data['role'])) {
            $user->assignRole($data['role']);
        } else {
            $user->assignRole('Staff');
        }

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::CREATE,
            module: ActivityModule::USER,
            description: "Membuat akun pengguna baru: {$user->name} ({$user->email})",
            resourceType: 'User',
            resourceId: $user->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ];
    }

    public function updateUser(User $user, array $data): array
    {
        $updatePayload = [
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ];

        if (!empty($data['password'])) {
            $updatePayload['password'] = Hash::make($data['password']);
        }

        if (isset($data['status'])) {
            $updatePayload['status'] = $data['status'];
        }

        if (array_key_exists('mitra_id', $data)) {
            $updatePayload['mitra_id'] = $data['mitra_id'];
        }

        $user->update($updatePayload);

        if (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        $this->activityLogService->log(
            userId: auth()->id(),
            action: ActivityAction::UPDATE,
            module: ActivityModule::USER,
            description: "Memperbarui profil/role pengguna: {$user->name} ({$user->email})",
            resourceType: 'User',
            resourceId: $user->id,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent()
        );

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ];
    }

    public function deleteUser(User $user): bool
    {
        $name = $user->name;
        $email = $user->email;
        $id = $user->id;
        $deleted = (bool) $user->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: auth()->id(),
                action: ActivityAction::DELETE,
                module: ActivityModule::USER,
                description: "Menghapus akun pengguna: {$name} ({$email})",
                resourceType: 'User',
                resourceId: $id,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent()
            );
        }

        return $deleted;
    }
}
