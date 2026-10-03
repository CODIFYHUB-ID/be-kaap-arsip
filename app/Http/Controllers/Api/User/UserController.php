<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\UserService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected UserService $userService
    ) {}

    protected function checkUserAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->forbidden('Akses ditolak. Anda tidak berwenang mengelola data pengguna.');
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->checkUserAccess($request)) return $deny;

        $result = $this->userService->getUsers($request->all());
        return $this->success($result, 'Daftar pengguna berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->checkUserAccess($request)) return $deny;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string',
            'mitra_id' => 'nullable|exists:mitras,id',
            'status' => 'nullable|string',
        ]);

        $result = $this->userService->createUser($validated);
        return $this->success($result, 'Pengguna baru berhasil ditambahkan.', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->checkUserAccess($request)) return $deny;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string',
            'mitra_id' => 'nullable|exists:mitras,id',
            'status' => 'nullable|string',
        ]);

        $result = $this->userService->updateUser($user, $validated);
        return $this->success($result, 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($deny = $this->checkUserAccess($request)) return $deny;

        $this->userService->deleteUser($user);
        return $this->success(null, 'Pengguna berhasil dihapus.');
    }
}
