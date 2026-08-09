<?php

namespace App\Services\Report;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Mitra;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getDocumentReport(array $filters = []): array
    {
        $query = Document::select('category_id', DB::raw('count(*) as total'), DB::raw('sum(file_size) as total_size'))
            ->groupBy('category_id')
            ->with('category:id,name');

        return $query->get()->toArray();
    }

    public function getMitraReport(): array
    {
        return Mitra::withCount(['documents', 'letters'])->get()->toArray();
    }

    public function getStorageReport(): array
    {
        $totalBytes = Document::sum('file_size');

        $byExtension = Document::select('extension', DB::raw('count(*) as count'), DB::raw('sum(file_size) as total_size'))
            ->groupBy('extension')
            ->get();

        return [
            'total_bytes' => (int) $totalBytes,
            'by_extension' => $byExtension,
        ];
    }
}
