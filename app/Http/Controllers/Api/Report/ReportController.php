<?php

namespace App\Http\Controllers\Api\Report;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportService $reportService
    ) {}

    public function documents(Request $request): JsonResponse
    {
        $data = $this->reportService->getDocumentReport($request->all());
        return $this->success($data, 'Laporan dokumen berhasil diambil.');
    }

    public function mitras(): JsonResponse
    {
        $data = $this->reportService->getMitraReport();
        return $this->success($data, 'Laporan mitra berhasil diambil.');
    }

    public function storage(): JsonResponse
    {
        $data = $this->reportService->getStorageReport();
        return $this->success($data, 'Laporan penggunaan storage berhasil diambil.');
    }

    public function export(Request $request): JsonResponse
    {
        // Placeholder for queue export background process
        return $this->success([
            'status' => 'queued',
            'message' => 'Proses export sedang berjalan di background.',
        ], 'Permintaan export laporan diterima.');
    }
}
