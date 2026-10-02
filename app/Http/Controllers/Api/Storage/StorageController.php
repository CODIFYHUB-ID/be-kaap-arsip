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

        $folder = $validated['folder'] ?? null;
        if (!$folder) {
            $parts = [];
            // 1. Client / Mitra prefix
            if ($request->filled('mitra_id')) {
                $mitra = \App\Models\Mitra::find($request->input('mitra_id'));
                if ($mitra) {
                    $parts[] = \Illuminate\Support\Str::slug($mitra->code ?: $mitra->name);
                }
            } elseif ($request->filled('client_code')) {
                $parts[] = \Illuminate\Support\Str::slug($request->input('client_code'));
            } else {
                $parts[] = 'general';
            }

            // 2. Tahun Dokumen / Buku
            $year = $request->input('tahun_berkas') ?: $request->input('tahun_buku') ?: now()->format('Y');
            $parts[] = $year;

            // 3. Kategori Surat / Dokumen
            if ($request->filled('category_id')) {
                $category = \App\Models\Category::find($request->input('category_id'));
                if ($category) {
                    $parts[] = \Illuminate\Support\Str::slug($category->name);
                } else {
                    $parts[] = 'berkas';
                }
            } elseif ($request->filled('category_name')) {
                $parts[] = \Illuminate\Support\Str::slug($request->input('category_name'));
            } else {
                $parts[] = 'dokumen';
            }

            $folder = implode('/', $parts);
        }

        $data = $this->r2StorageService->generatePresignedUploadUrl(
            $validated['file_name'],
            $validated['mime_type'],
            $folder
        );

        return $this->success($data, 'Presigned URL upload berhasil dibuat.');
    }

    public function uploadMock(Request $request): JsonResponse
    {
        $key = $request->query('key') ?: $request->input('key');
        if ($key) {
            $rawContent = $request->getContent();
            if (!empty($rawContent)) {
                \Illuminate\Support\Facades\Storage::disk('public')->put($key, $rawContent);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Mock upload berhasil diproses.',
            'data' => [
                'key' => $key,
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
