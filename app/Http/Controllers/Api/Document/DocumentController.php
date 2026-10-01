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
        $filters = $request->only(['search', 'mitra_id', 'category_id', 'uploaded_by', 'tahun_berkas', 'extension']);
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
        ]);

        // Auto-assign or validate mitra_id for Mitra users
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            if (empty($validated['mitra_id'])) {
                $validated['mitra_id'] = $user->mitra_id;
            } elseif ($validated['mitra_id'] != $user->mitra_id) {
                $isAllowed = \App\Models\Mitra::where('id', $validated['mitra_id'])->where('created_by', $user->id)->exists();
                if (! $isAllowed) {
                    return $this->error('Anda tidak berwenang mengunggah dokumen untuk mitra ini.', 403);
                }
            }
        }

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
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $document->mitra_id == $user->mitra_id)
                || $document->uploaded_by == $user->id
                || ($document->mitra && $document->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses ke dokumen ini.', 403);
            }
        }

        return $this->success($document->load(['mitra', 'category', 'uploader']), 'Detail dokumen berhasil diambil.');
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $document->mitra_id == $user->mitra_id)
                || $document->uploaded_by == $user->id
                || ($document->mitra && $document->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses untuk mengubah dokumen ini.', 403);
            }
        }

        $validated = $request->validate([
            'mitra_id' => 'nullable|exists:mitras,id',
            'category_id' => 'nullable|exists:categories,id',
            'file_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'tahun_berkas' => 'nullable|string|max:10',
            'tanggal_dokumen' => 'nullable|date',
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
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $document->mitra_id == $user->mitra_id)
                || $document->uploaded_by == $user->id
                || ($document->mitra && $document->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki akses untuk menghapus dokumen ini.', 403);
            }
        }

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
        $user = $request->user();
        if ($user && $user->isMitra() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            $isOwner = ($user->mitra_id && $document->mitra_id == $user->mitra_id)
                || $document->uploaded_by == $user->id
                || ($document->mitra && $document->mitra->created_by == $user->id);

            if (! $isOwner) {
                return $this->error('Anda tidak memiliki izin mengunduh dokumen ini.', 403);
            }
        }

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
