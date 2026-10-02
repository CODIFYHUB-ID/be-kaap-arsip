<?php

namespace App\Http\Controllers\Api\ActivityLog;

use App\Http\Controllers\Controller;
use App\Services\ActivityLog\ActivityLogService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ActivityLogService $activityLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->error('Akses ditolak. Log audit aktivitas mendalam hanya dapat diakses oleh Pimpinan KAP / Owner.', 403);
        }

        $filters = $request->only(['module', 'action', 'user_id', 'search', 'start_date', 'end_date']);
        $perPage = (int) $request->get('per_page', 20);

        $logs = $this->activityLogService->getPaginated($filters, $perPage);
        return $this->paginated($logs, 'Activity logs berhasil diambil.');
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            return $this->error('Akses ditolak. Statistik log aktivitas hanya dapat diakses oleh Pimpinan KAP / Owner.', 403);
        }

        $stats = $this->activityLogService->getStats();
        return $this->success($stats, 'Statistik activity logs berhasil diambil.');
    }
}
