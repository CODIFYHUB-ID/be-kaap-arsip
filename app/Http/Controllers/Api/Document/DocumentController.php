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
        $filters = $request->only(['search', 'mitra_id', 'category_id', 'uploaded_by', 'tahun_berkas', 'extension', 'is_working_paper', 'review_status']);
        $perPage = (int) $request->get('per_page', 20);

        $documents = $this->documentQueryService->getPaginated($filters, $perPage, $request->user());
        return $this->paginated($documents, 'Inventory dokumen berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_key' => 'required|string',
            'file_name' => 'required|string|max:255',
            'file_size' => 'required|integer',
            'mime_type' => 'required|string',
            'extension' => 'required|string',
            'description' => 'nullable|string',
            'tahun_berkas' => 'nullable|string|max:10',
            'tanggal_dokumen' => 'nullable|date',
            'is_working_paper' => 'nullable|boolean',
            'review_status' => 'nullable|string|in:draft,reviewed,revision_needed,approved',
            'review_notes' => 'nullable|string',
        ]);

        $document = $this->documentService->create(
            $validated,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($document, 'Metadata dokumen berhasil disimpan.', 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        return $this->success($document->load(['mitra', 'category', 'uploader', 'reviewer']), 'Detail dokumen berhasil diambil.');
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tahun_berkas' => 'nullable|string|max:10',
            'tanggal_dokumen' => 'nullable|date',
            'is_working_paper' => 'nullable|boolean',
            'review_status' => 'nullable|string|in:draft,reviewed,revision_needed,approved',
            'review_notes' => 'nullable|string',
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

    public function approve(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['Owner', 'Super Admin'])) {
            return $this->error('Akses ditolak. Hanya Pimpinan KAP (Owner) yang berwenang menyetujui Laporan Audit Final.', 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $approved = $this->documentService->approve(
            $document,
            $user->id,
            $validated['notes'] ?? null,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($approved, 'Dokumen Laporan Audit Final berhasil disetujui resmi oleh Pimpinan.');
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['Owner', 'Super Admin'])) {
            return $this->error('Akses ditolak. Hanya Pimpinan KAP (Owner) yang berwenang menolak dokumen final.', 403);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $rejected = $this->documentService->reject(
            $document,
            $user->id,
            $validated['notes'],
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($rejected, 'Dokumen Laporan Audit Final ditolak untuk revisi.');
    }

    public function pendingApprovals(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasAnyRole(['Owner', 'Super Admin'])) {
            return $this->error('Akses ditolak. Hanya Pimpinan KAP (Owner) yang dapat memantau antrean persetujuan.', 403);
        }

        $perPage = (int) $request->get('per_page', 15);
        $pending = $this->documentService->getPendingApprovals($perPage);

        return $this->paginated($pending, 'Daftar dokumen menunggu persetujuan berhasil diambil.');
    }

    public function bundle(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'mitra_id' => 'required|exists:mitras,id',
            'year' => 'nullable|string',
        ]);

        $bundle = $this->documentService->getBundle((int) $validated['mitra_id'], $validated['year'] ?? null);

        return $this->success($bundle, 'Bundel dokumen berhasil disiapkan.');
    }

    public function reviewKKP(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if (! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            return $this->error('Akses ditolak. Anda tidak berwenang menelaah Kertas Kerja Pemeriksaan (KKP).', 403);
        }

        $validated = $request->validate([
            'review_status' => 'required|string|in:draft,reviewed,revision_needed,approved',
            'notes' => 'nullable|string|max:1000',
        ]);

        $reviewed = $this->documentService->reviewKKP(
            $document,
            $user->id,
            $validated['review_status'],
            $validated['notes'] ?? null,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success($reviewed, 'Penelaahan Kertas Kerja Pemeriksaan (KKP) berhasil disimpan.');
    }
}
