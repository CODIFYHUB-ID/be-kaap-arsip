<?php

namespace App\Services\Dashboard;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\Mitra;

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
        $activityQuery = ActivityLog::with('user:id,name');

        if ($startDate && $endDate) {
            $start = $startDate . ' 00:00:00';
            $end = $endDate . ' 23:59:59';

            $docQuery->whereBetween('created_at', [$start, $end]);
            $mitraQuery->whereBetween('created_at', [$start, $end]);
            $activityQuery->whereBetween('created_at', [$start, $end]);
        }

        $totalDocuments = $docQuery->count();
        $totalMitras = $mitraQuery->count();
        $totalIncomingLetters = 0;
        $totalOutgoingLetters = 0;
        $totalReceipts = 0;
        $totalStorageBytes = $docQuery->sum('file_size');

        $recentActivities = $activityQuery
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDocQuery = Document::with(['mitra:id,name', 'category:id,name', 'uploader:id,name']);
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

        // Compute monthly trends for the last 6 months (Documents)
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthLabel = $monthDate->translatedFormat('M');

            $mDocQuery = Document::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month);

            $monthlyTrends[] = [
                'month' => $monthLabel,
                'year' => $monthDate->year,
                'month_num' => $monthDate->month,
                'upload_count' => $mDocQuery->count(),
                'incoming_count' => 0,
                'outgoing_count' => 0,
            ];
        }

        // Financial Overview (DP, Termin, Pelunasan, Operasional) - cleaned up from old Kwitansi module
        $financialOverview = [
            'total_dp' => 0.0,
            'total_termin' => 0.0,
            'total_pelunasan' => 0.0,
            'total_operasional' => 0.0,
            'total_revenue' => 0.0,
        ];

        // Correspondence Overview - cleaned up from old Surat module
        $correspondenceOverview = [
            'total_incoming' => 0,
            'total_outgoing' => 0,
            'draft_count' => 0,
            'final_count' => 0,
        ];

        // Pending Final Archive Approvals
        $pendingApprovalsCount = Document::where('approval_status', 'pending')->count();
        $pendingApprovalsList = Document::with(['mitra:id,name,company_name', 'category:id,name', 'uploader:id,name'])
            ->where('approval_status', 'pending')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'stats' => [
                'total_documents' => $totalDocuments,
                'total_mitras' => $totalMitras,
                'total_incoming_letters' => $totalIncomingLetters,
                'total_outgoing_letters' => $totalOutgoingLetters,
                'total_receipts' => $totalReceipts,
                'total_storage_bytes' => (int) $totalStorageBytes,
                'pending_approvals_count' => $pendingApprovalsCount,
            ],
            'category_distribution' => $categoryDistribution,
            'recent_activities' => $recentActivities,
            'recent_documents' => $recentDocuments,
            'monthly_trends' => $monthlyTrends,
            'financial_overview' => $financialOverview,
            'correspondence_overview' => $correspondenceOverview,
            'pending_approvals' => $pendingApprovalsList,
            'auditor_overview' => null,
        ];
    }
}
