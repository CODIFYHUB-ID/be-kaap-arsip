<?php

namespace App\Http\Controllers\Api\RecycleBin;

use App\Http\Controllers\Controller;
use App\Services\RecycleBin\RecycleBinService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecycleBinController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RecycleBinService $recycleBinService
    ) {}

    public function index(): JsonResponse
    {
        $items = $this->recycleBinService->getDeletedItems();
        return $this->success($items, 'Daftar data di Recycle Bin berhasil diambil.');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:document,mitra,letter,category',
        ]);

        $restored = $this->recycleBinService->restoreItem($request->type, $id);
        if ($restored) {
            return $this->success(null, 'Data berhasil dipulihkan.');
        }

        return $this->error('Gagal memulihkan data.', null, 400);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:document,mitra,letter,category',
        ]);

        $deleted = $this->recycleBinService->forceDeleteItem($request->type, $id);
        if ($deleted) {
            return $this->success(null, 'Data berhasil dihapus permanen.');
        }

        return $this->error('Gagal menghapus data secara permanen.', null, 400);
    }
}
