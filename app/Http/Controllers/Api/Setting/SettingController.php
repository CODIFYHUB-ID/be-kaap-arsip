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

    public function index(): JsonResponse
    {
        $settings = $this->settingService->getAll();
        return $this->success($settings, 'Pengaturan sistem berhasil diambil.');
    }

    public function update(Request $request): JsonResponse
    {
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
}
