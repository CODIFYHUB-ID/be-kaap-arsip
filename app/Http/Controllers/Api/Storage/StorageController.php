<?php

namespace App\Http\Controllers\Api\Storage;

use App\Http\Controllers\Controller;
use App\Services\Storage\R2StorageService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected R2StorageService $r2StorageService
    ) {}

    public function presign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_name' => 'required|string',
            'mime_type' => 'required|string',
            'folder' => 'nullable|string',
        ]);

        $folder = $validated['folder'] ?? 'documents';

        $data = $this->r2StorageService->generatePresignedUploadUrl(
            $validated['file_name'],
            $validated['mime_type'],
            $folder
        );

        return $this->success($data, 'Presigned URL upload berhasil dibuat.');
    }

    public function uploadMock(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Mock upload berhasil diproses.',
            'data' => [
                'key' => $request->get('key'),
                'uploaded' => true,
            ]
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
        ]);
    }

    public function destroy(string $key): JsonResponse
    {
        $deleted = $this->r2StorageService->deleteObject(urldecode($key));
        if ($deleted) {
            return $this->success(null, 'Object R2 berhasil dihapus.');
        }

        return $this->error('Gagal menghapus object R2.', null, 400);
    }
}
