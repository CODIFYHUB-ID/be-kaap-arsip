<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Services\Permission\PermissionService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PermissionService $permissionService
    ) {}

    public function index(): JsonResponse
    {
        $permissions = $this->permissionService->getAllPermissions();
        return $this->success($permissions, 'Daftar permission berhasil diambil.');
    }
}
