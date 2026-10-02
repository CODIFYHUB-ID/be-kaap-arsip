<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use App\Services\System\SystemSettingService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SystemSettingService $settingService
    ) {}

    protected function checkAdminAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (($user->isMitra() || $user->isAuditor()) && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->forbidden('Akses ditolak. Anda tidak berwenang mengakses atau mengubah pengaturan sistem.');
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->checkAdminAccess($request)) return $deny;

        $settings = $this->settingService->getAll();
        return $this->success($settings, 'Pengaturan sistem berhasil diambil.');
    }

    public function update(Request $request): JsonResponse
    {
        if ($deny = $this->checkAdminAccess($request)) return $deny;

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($validated['settings'] as $item) {
            $this->settingService->set($item['key'], $item['value']);
        }

        return $this->success(null, 'Pengaturan sistem berhasil diperbarui.');
    }

    public function getKapProfile(): JsonResponse
    {
        $profile = $this->settingService->getKapProfile();
        return $this->success($profile, 'Profil & format kop surat KAP berhasil diambil.');
    }

    public function updateKapProfile(Request $request): JsonResponse
    {
        if ($deny = $this->checkAdminAccess($request)) return $deny;

        $validated = $request->validate([
            'kap_name' => 'nullable|string|max:255',
            'kap_tagline' => 'nullable|string|max:255',
            'kap_license_no' => 'nullable|string|max:255',
            'kap_ojk_no' => 'nullable|string|max:255',
            'kap_iapi_no' => 'nullable|string|max:255',
            'kap_leader_name' => 'nullable|string|max:255',
            'kap_leader_license' => 'nullable|string|max:255',
            'kap_leader_position' => 'nullable|string|max:255',
            'kap_address' => 'nullable|string|max:500',
            'kap_city' => 'nullable|string|max:100',
            'kap_postal_code' => 'nullable|string|max:20',
            'kap_phone' => 'nullable|string|max:100',
            'kap_email' => 'nullable|string|email|max:150',
            'kap_website' => 'nullable|string|max:150',
            'kap_branch_address' => 'nullable|string|max:500',
            'kap_logo_url' => 'nullable|string|max:500',
            'kap_kop_layout' => 'nullable|string|in:standard,centered,modern',
            'kap_divider_style' => 'nullable|string|in:double,single,accent',
        ]);

        $updated = $this->settingService->updateKapProfile($validated);
        return $this->success($updated, 'Profil & format kop surat KAP berhasil diperbarui.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        if ($deny = $this->checkAdminAccess($request)) return $deny;

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp,svg|max:5120', // max 5MB
        ]);

        $url = $this->settingService->uploadLogo($request->file('logo'));
        return $this->success([
            'url' => $url,
        ], 'Logo resmi KAP berhasil diunggah ke storage lokal.');
    }
}
