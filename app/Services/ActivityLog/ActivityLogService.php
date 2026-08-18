<?php

namespace App\Services\ActivityLog;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogService
{
    /**
     * Record an activity log.
     */
    public function log(
        ?int $userId,
        ActivityAction $action,
        ActivityModule $module,
        ?string $description = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * Get paginated activity logs with search & filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ActivityLog::with('user:id,name,email')->orderByDesc('created_at');

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'like', "%{$filters['search']}%")
                  ->orWhere('ip_address', 'like', "%{$filters['search']}%")
                  ->orWhereHas('user', function ($uq) use ($filters) {
                      $uq->where('name', 'like', "%{$filters['search']}%")
                         ->orWhere('email', 'like', "%{$filters['search']}%");
                  });
            });
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get summary statistics of activity logs.
     */
    public function getStats(): array
    {
        $today = now()->startOfDay();
        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::where('created_at', '>=', $today)->count();
        $uniqueUsers = ActivityLog::whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $topModule = ActivityLog::select('module', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('module')
            ->orderByDesc('count')
            ->first();

        return [
            'total_logs' => $totalLogs,
            'today_logs' => $todayLogs,
            'unique_users' => $uniqueUsers,
            'top_module' => $topModule ? $topModule->module : 'DOCUMENT',
        ];
    }
}
