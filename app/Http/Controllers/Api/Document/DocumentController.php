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

        // Pastikan berkas fisik benar-benar ada di bucket Cloudflare R2
        // Jika dokumen berasal dari "Tautkan dari Pembuatan Berkas", unggah file placeholder/PDF resmi ke R2
        try {
            $r2Storage = app(\App\Services\Storage\R2StorageService::class);
            $fileKey = $validated['file_key'];
            
            // Check if file already exists in R2
            $disk = \Illuminate\Support\Facades\Storage::disk('r2');
            if (!$disk->exists($fileKey)) {
                $docTitle = $validated['file_name'] ?: 'Dokumen';
                $clientName = 'Klien';
                if (!empty($validated['mitra_id'])) {
                    $m = \App\Models\Mitra::find($validated['mitra_id']);
                    if ($m) $clientName = $m->company_name ?: $m->name;
                }
                $tahun = $validated['tahun_berkas'] ?: now()->format('Y');

                // Buat konten file PDF/dokumen arsip untuk disimpan ke bucket R2
                $pdfPlaceholder = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj 4 0 obj<</Length 160>>stream\nBT\n/F1 14 Tf\n50 780 Td\n(KAP Drs. Selamat Sinuraya dan Rekan) Tj\n/F1 11 Tf\n0 -30 Td\n(Arsip Dokumen: " . addcslashes($docTitle, "()") . ") Tj\n0 -20 Td\n(Klien: " . addcslashes($clientName, "()") . ") Tj\n0 -20 Td\n(Tahun: " . addcslashes($tahun, "()") . ") Tj\nET\nendstream\nendobj 5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\nxref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000242 00000 n \n0000000454 00000 n \ntrailer<</Size 6/Root 1 0 R>>\nstartxref\n525\n%%EOF";

                $r2Storage->putContent($fileKey, $pdfPlaceholder, $validated['mime_type'] ?: 'application/pdf');

                // Dual Backup: Sync to Google Drive automatically
                try {
                    $driveStorage = app(\App\Services\Storage\GoogleDriveStorageService::class);
                    $folderPath = dirname($fileKey);
                    $driveStorage->uploadFile($folderPath, $validated['file_name'], $pdfPlaceholder, $validated['mime_type'] ?: 'application/pdf');
                } catch (\Throwable $driveEx) {
                    \Illuminate\Support\Facades\Log::warning('Google Drive auto-backup warning: ' . $driveEx->getMessage());
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('R2 Auto-upload on store warning: ' . $e->getMessage());
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

    public function destroy(Request $request, $document): JsonResponse
    {
        $doc = $document instanceof Document ? $document : Document::withTrashed()->find($document);

        if (!$doc) {
            return $this->error('Dokumen tidak ditemukan atau sudah dihapus.', null, 404);
        }

        // Jika sudah soft-deleted sebelumnya, tetap kembalikan status sukses
        if ($doc->trashed()) {
            return $this->success(null, 'Dokumen sudah berada di Recycle Bin.');
        }

        $this->documentService->delete(
            $doc,
            $request->user()->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->success(null, 'Dokumen berhasil dipindahkan ke Recycle Bin.');
    }

    public function download(Request $request, Document $document): JsonResponse
    {
        $downloadUrl = url("/api/v1/documents/{$document->id}/stream?token=" . ($request->bearerToken() ?: $request->user()?->createToken('doc_download')->plainTextToken));

        return $this->success([
            'download_url' => $downloadUrl,
            'file_name' => $document->file_name,
        ], 'URL unduh dokumen berhasil dibuat.');
    }

    public function stream(Request $request, Document $document)
    {
        // Support token passed via query string for iframe / window.open
        $tokenString = $request->query('token') ?: $request->bearerToken();
        $user = $request->user();

        if (! $user && $tokenString) {
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
            if ($personalAccessToken && (! $personalAccessToken->expires_at || $personalAccessToken->expires_at->isFuture())) {
                $user = $personalAccessToken->tokenable;
            }
        }

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $inline = ! $request->boolean('download');
        return $this->documentService->streamFile(
            $document,
            $user->id,
            $inline,
            $request->ip(),
            $request->userAgent()
        );
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
