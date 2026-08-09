<?php

namespace App\Services\Permission;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    public function getAllRoles(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function createRole(array $data, array $permissionIds = []): Role
    {
        $role = Role::create($data);

        if (! empty($permissionIds)) {
            $role->permissions()->sync($permissionIds);
        }

        return $role->load('permissions');
    }

    public function updateRole(Role $role, array $data, ?array $permissionIds = null): Role
    {
        $role->update($data);

        if ($permissionIds !== null) {
            $role->permissions()->sync($permissionIds);
        }

        return $role->load('permissions');
    }
}
