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
        $filters = $request->only(['search', 'role_id']);
        $perPage = (int) $request->get('per_page', 20);

        $users = $this->userService->getPaginated($filters, $perPage);
        return $this->paginated($users, 'Daftar user berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $user = $this->userService->create($validated);
        return $this->success($user, 'User berhasil ditambahkan.', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->success($user->load('role.permissions'), 'Detail user berhasil diambil.');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $updated = $this->userService->update($user, $validated);
        return $this->success($updated, 'User berhasil diperbarui.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);
        return $this->success(null, 'User berhasil dihapus.');
    }
}
