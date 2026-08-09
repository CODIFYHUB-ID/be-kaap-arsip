<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\Permission\PermissionService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PermissionService $permissionService
    ) {}

    public function index(): JsonResponse
    {
        $roles = $this->permissionService->getAllRoles();
        return $this->success($roles, 'Daftar role berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = $this->permissionService->createRole(
            ['name' => $validated['name'], 'description' => $validated['description'] ?? null],
            $validated['permission_ids'] ?? []
        );

        return $this->success($role, 'Role berhasil dibuat.', 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $updated = $this->permissionService->updateRole(
            $role,
            ['name' => $validated['name'], 'description' => $validated['description'] ?? null],
            $validated['permission_ids'] ?? null
        );

        return $this->success($updated, 'Role berhasil diperbarui.');
    }
}
