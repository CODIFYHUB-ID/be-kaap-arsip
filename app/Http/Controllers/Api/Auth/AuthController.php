<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login(
            $credentials,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($result, 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return $this->success(null, 'Logout berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('mitra');
        $userData = $user->toArray();
        $userData['roles'] = $user->getRoleNames()->toArray();
        $userData['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
        $userData['mitra'] = $user->mitra;

        return $this->success($userData, 'Data user berhasil diambil.');
    }
}
