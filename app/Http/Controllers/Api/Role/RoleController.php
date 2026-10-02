<?php

namespace App\Http\Controllers\Api\Role;

use App\Http\Controllers\Controller;
use App\Services\Role\RoleService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RoleService $roleService
    ) {}

    protected function checkRoleAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (($user->isMitra() || $user->isAuditor()) && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->forbidden('Akses ditolak. Anda tidak berwenang mengelola data role & permission.');
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->checkRoleAccess($request)) return $deny;

        $roles = $this->roleService->getRoles();
        return $this->success($roles, 'Daftar role berhasil diambil.');
    }

    public function permissions(Request $request): JsonResponse
    {
        if ($deny = $this->checkRoleAccess($request)) return $deny;

        $permissions = $this->roleService->getPermissions();
        return $this->success($permissions, 'Daftar permission berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->checkRoleAccess($request)) return $deny;

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $role = $this->roleService->createRole($validated);
        return $this->success($role, 'Role baru berhasil dibuat.', 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($deny = $this->checkRoleAccess($request)) return $deny;

        $validated = $request->validate([
            'name' => "sometimes|string|unique:roles,name,{$role->id}",
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $updated = $this->roleService->updateRole($role, $validated);
        return $this->success($updated, 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($deny = $this->checkRoleAccess($request)) return $deny;

        try {
            $this->roleService->deleteRole($role);
            return $this->success(null, 'Role berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
