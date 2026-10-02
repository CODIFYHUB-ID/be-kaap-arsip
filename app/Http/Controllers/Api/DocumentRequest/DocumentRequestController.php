<?php

namespace App\Http\Controllers\Api\DocumentRequest;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Services\DocumentRequest\DocumentRequestService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentRequestService $documentRequestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'mitra_id', 'tahun_buku', 'priority', 'search']);
        $perPage = (int) $request->get('per_page', 20);

        $list = $this->documentRequestService->getPaginated($filters, $perPage, $request->user());
        return $this->paginated($list, 'Daftar permintaan dokumen berhasil diambil.');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isKlien() && ! $user->hasAnyRole(['Owner', 'Super Admin', 'Admin', 'Staff'])) {
            return $this->error('Akses ditolak. Klien tidak memiliki hak membuat permintaan dokumen.', 403);
        }

        $validated = $request->validate([
            'mitra_id' => 'required|exists:mitras,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'tahun_buku' => 'required|string|max:10',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
        ]);

        $docRequest = $this->documentRequestService->create($validated, $user->id);
        return $this->success($docRequest, 'Permintaan dokumen berhasil dibuat.', 201);
    }

    public function show(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isKlien() && $documentRequest->mitra_id !== $user->mitra_id) {
            return $this->error('Anda tidak berwenang mengakses permintaan dokumen ini.', 403);
        }

        return $this->success($documentRequest->load(['mitra', 'category', 'creator', 'document', 'reviewer']), 'Detail permintaan dokumen berhasil diambil.');
    }

    public function fulfill(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isKlien() && $documentRequest->mitra_id !== $user->mitra_id) {
            return $this->error('Anda tidak berwenang memperbarui berkas untuk permintaan ini.', 403);
        }

        $validated = $request->validate([
            'document_id' => 'required|exists:documents,id',
        ]);

        $updated = $this->documentRequestService->fulfill($documentRequest, $validated['document_id']);
        return $this->success($updated, 'Dokumen berhasil diunggah dan ditautkan ke permintaan.');
    }

    public function review(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->isKlien()) {
            return $this->error('Klien tidak dapat melakukan verifikasi/review permintaan dokumen.', 403);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'notes' => 'nullable|string|max:500',
        ]);

        $updated = $this->documentRequestService->review(
            $documentRequest,
            $validated['action'],
            $validated['notes'] ?? null,
            $user->id
        );

        $msg = $validated['action'] === 'approve'
            ? 'Dokumen berhasil disetujui.'
            : 'Permintaan dokumen ditolak dan menunggu revisi klien.';

        return $this->success($updated, $msg);
    }
}
