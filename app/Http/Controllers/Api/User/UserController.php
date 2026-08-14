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

    public function index(Request $request): JsonResponse
    {
        $result = $this->userService->getUsers($request->all());
        return $this->success($result, 'Daftar pengguna berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $result = $this->userService->createUser($validated);
        return $this->success($result, 'Pengguna baru berhasil ditambahkan.', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $result = $this->userService->updateUser($user, $validated);
        return $this->success($result, 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user);
        return $this->success(null, 'Pengguna berhasil dihapus.');
    }
}
