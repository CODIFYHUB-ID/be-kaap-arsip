<?php

namespace App\Http\Controllers\Api\Document;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Document\DocumentQueryService;
use App\Services\Document\DocumentService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentQueryService $documentQueryService,
        protected DocumentService $documentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'mitra_id', 'category_id', 'uploaded_by']);
        $perPage = (int) $request->get('per_page', 20);

        $documents = $this->documentQueryService->getPaginated($filters, $perPage);
        return $this->paginated($documents, 'Inventory dokumen berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_key' => 'required|string',
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer',
            'mime_type' => 'required|string',
            'extension' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $document = $this->documentService->create(
            $validated,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($document, 'Metadata dokumen berhasil disimpan.', 201);
    }

    public function show(Document $document): JsonResponse
    {
        return $this->success($document->load(['mitra', 'category', 'uploader']), 'Detail dokumen berhasil diambil.');
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $updated = $this->documentService->update(
            $document,
            $validated,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($updated, 'Metadata dokumen berhasil diperbarui.');
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->documentService->delete(
            $document,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success(null, 'Dokumen berhasil dipindahkan ke Recycle Bin.');
    }

    public function download(Request $request, Document $document): JsonResponse
    {
        $downloadUrl = $this->documentService->getDownloadUrl(
            $document,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success([
            'download_url' => $downloadUrl,
            'file_name' => $document->file_name,
        ], 'URL unduh dokumen berhasil dibuat.');
    }
}
