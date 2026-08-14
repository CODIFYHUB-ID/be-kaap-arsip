<?php

namespace App\Services\Role;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function getRoles(): array
    {
        $roles = Role::with('permissions')->get();

        return $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        })->toArray();
    }

    public function getPermissions(): array
    {
        return Permission::all(['id', 'name', 'guard_name'])->toArray();
    }

    public function createRole(array $data): array
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->toArray(),
        ];
    }

    public function updateRole(Role $role, array $data): array
    {
        if (!empty($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->toArray(),
        ];
    }

    public function deleteRole(Role $role): bool
    {
        if ($role->name === 'Owner' || $role->name === 'Super Admin') {
            throw new \Exception("Role {$role->name} utama tidak dapat dihapus.");
        }
        return (bool) $role->delete();
    }
}
