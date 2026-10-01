<?php

namespace App\Services\Auth;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Authenticate user and issue access token.
     */
    public function login(array $credentials, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang Anda masukkan salah.'],
            ]);
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda sedang tidak aktif.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->activityLogService->log(
            userId: $user->id,
            action: ActivityAction::LOGIN,
            module: ActivityModule::AUTH,
            description: "User {$user->name} berhasil login",
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        $user->load('mitra');
        $userData = $user->toArray();
        $userData['roles'] = $user->getRoleNames()->toArray();
        $userData['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
        $userData['mitra'] = $user->mitra;

        return [
            'user' => $userData,
            'token' => $token,
        ];
    }

    /**
     * Logout current user by revoking tokens.
     */
    public function logout(User $user, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $user->tokens()->delete();

        $this->activityLogService->log(
            userId: $user->id,
            action: ActivityAction::LOGOUT,
            module: ActivityModule::AUTH,
            description: "User {$user->name} berhasil logout",
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        return true;
    }
}
