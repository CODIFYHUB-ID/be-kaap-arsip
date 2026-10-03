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

    protected function checkReportAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            return $this->error('Akses ditolak.', 403);
        }

        return null;
    }

    public function documents(Request $request): JsonResponse
    {
        if ($deny = $this->checkReportAccess($request)) return $deny;

        $data = $this->reportService->getDocumentReport($request->all());
        return $this->success($data, 'Laporan dokumen berhasil diambil.');
    }

    public function mitras(Request $request): JsonResponse
    {
        if ($deny = $this->checkReportAccess($request)) return $deny;

        $data = $this->reportService->getMitraReport($request->all());
        return $this->success($data, 'Laporan mitra berhasil diambil.');
    }

    public function storage(Request $request): JsonResponse
    {
        if ($deny = $this->checkReportAccess($request)) return $deny;

        $data = $this->reportService->getStorageReport();
        return $this->success($data, 'Laporan penggunaan storage berhasil diambil.');
    }

    public function export(Request $request): JsonResponse
    {
        if ($deny = $this->checkReportAccess($request)) return $deny;

        // Placeholder for queue export background process
        return $this->success([
            'status' => 'queued',
            'message' => 'Proses export sedang berjalan di background.',
        ], 'Permintaan export laporan diterima.');
    }
}
