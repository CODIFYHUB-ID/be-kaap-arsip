<?php

namespace App\Services\Dashboard;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Mitra;

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
        $totalStorageBytes = Document::sum('file_size');

        $recentActivities = ActivityLog::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDocuments = Document::with(['mitra:id,name', 'category:id,name', 'uploader:id,name'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'stats' => [
                'total_documents' => $totalDocuments,
                'total_mitras' => $totalMitras,
                'total_incoming_letters' => $totalIncomingLetters,
                'total_outgoing_letters' => $totalOutgoingLetters,
                'total_storage_bytes' => (int) $totalStorageBytes,
            ],
            'recent_activities' => $recentActivities,
            'recent_documents' => $recentDocuments,
        ];
    }
}
