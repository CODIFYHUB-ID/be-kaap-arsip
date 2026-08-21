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
     * Retrieve summary statistics and recent activities for the dashboard.
     */
    public function getSummary(): array
    {
        $totalDocuments = Document::count();
        $totalMitras = Mitra::where('status', 'active')->count();
        $totalIncomingLetters = Letter::where('type', 'incoming')->count();
        $totalOutgoingLetters = Letter::where('type', 'outgoing')->count();
        $totalReceipts = Receipt::count();
        $totalStorageBytes = Document::sum('file_size');

        $recentActivities = ActivityLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDocuments = Document::with(['mitra:id,name', 'category:id,name', 'uploader:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $categoryDistribution = Category::withCount('documents')
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

