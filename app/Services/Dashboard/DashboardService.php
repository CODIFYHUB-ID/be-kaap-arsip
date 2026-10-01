<?php

namespace App\Services\Dashboard;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Mitra;
use App\Models\Receipt;

class DashboardService
{
    /**
     * Retrieve summary statistics and recent activities for the dashboard with optional date filtering.
     */
    public function getSummary(?string $startDate = null, ?string $endDate = null, ?\App\Models\User $currentUser = null): array
    {
        $currentUser = $currentUser ?? auth()->user();

        $docQuery = Document::query();
        $mitraQuery = Mitra::where('status', 'active');
        $incomingQuery = Letter::where('type', 'incoming');
        $outgoingQuery = Letter::where('type', 'outgoing');
        $receiptQuery = Receipt::query();
        $activityQuery = ActivityLog::with('user:id,name');

        if ($currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $docQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('uploaded_by', $currentUser->id);
            });
            $mitraQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id);
            });
            $incomingQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id);
            });
            $outgoingQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id);
            });
            $receiptQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('created_by', $currentUser->id);
            });
            $activityQuery->where('user_id', $currentUser->id);
        }

        if ($startDate && $endDate) {
            $start = $startDate . ' 00:00:00';
            $end = $endDate . ' 23:59:59';

            $docQuery->whereBetween('created_at', [$start, $end]);
            $mitraQuery->whereBetween('created_at', [$start, $end]);
            $incomingQuery->whereBetween('created_at', [$start, $end]);
            $outgoingQuery->whereBetween('created_at', [$start, $end]);
            $receiptQuery->whereBetween('created_at', [$start, $end]);
            $activityQuery->whereBetween('created_at', [$start, $end]);
        }

        $totalDocuments = $docQuery->count();
        $totalMitras = $mitraQuery->count();
        $totalIncomingLetters = $incomingQuery->count();
        $totalOutgoingLetters = $outgoingQuery->count();
        $totalReceipts = $receiptQuery->count();
        $totalStorageBytes = $docQuery->sum('file_size');

        $recentActivities = $activityQuery
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDocQuery = Document::with(['mitra:id,name', 'category:id,name', 'uploader:id,name']);
        if ($currentUser && $currentUser->isMitra() && ! $currentUser->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $recentDocQuery->where(function ($q) use ($currentUser) {
                if ($currentUser->mitra_id) {
                    $q->where('mitra_id', $currentUser->mitra_id);
                }
                $q->orWhere('uploaded_by', $currentUser->id);
            });
        }
        if ($startDate && $endDate) {
            $recentDocQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        $recentDocuments = $recentDocQuery
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $categoryDistribution = Category::withCount(['documents' => function ($q) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }
        }])
            ->get()
            ->map(function ($cat) use ($totalDocuments) {
                $count = $cat->documents_count;
                $percentage = $totalDocuments > 0 ? round(($count / $totalDocuments) * 100, 1) : 0;
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            });

        return [
            'stats' => [
                'total_documents' => $totalDocuments,
                'total_mitras' => $totalMitras,
                'total_incoming_letters' => $totalIncomingLetters,
                'total_outgoing_letters' => $totalOutgoingLetters,
                'total_receipts' => $totalReceipts,
                'total_storage_bytes' => (int) $totalStorageBytes,
            ],
            'category_distribution' => $categoryDistribution,
            'recent_activities' => $recentActivities,
            'recent_documents' => $recentDocuments,
        ];
    }
}

