<?php

namespace App\Http\Controllers\Api\PhysicalAudit;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\PhysicalAudit;
use App\Models\PhysicalAuditItem;
use App\Services\Storage\R2StorageService;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhysicalAuditController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected R2StorageService $r2StorageService
    ) {}

    /**
     * List all physical audits with filter by type, mitra_id, search & pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = PhysicalAudit::query()->with(['mitra', 'creator', 'archivedDocument', 'items']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($mitraId = $request->input('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('audit_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('location_or_cashier', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $list = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return $this->success($list, 'Daftar audit fisik berhasil diambil.');
    }

    /**
     * Generate next audit number recommendation
     * e.g. OP-CASH/2026/001, OP-PERS/2026/001, OP-ASET/2026/001
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $type = $request->input('type', 'cash');
        $prefix = match ($type) {
            'persediaan' => 'OP-PERS',
            'aset_tetap' => 'OP-ASET',
            default => 'OP-CASH',
        };

        $year = (int) date('Y');
        $count = PhysicalAudit::where('type', $type)->whereYear('created_at', $year)->count();
        $nextSeq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return $this->success([
            'seq_number' => $nextSeq,
            'year' => (string) $year,
            'recommended_number' => "{$prefix}/{$year}/{$nextSeq}",
        ], 'Rekomendasi nomor berita acara berhasil digenerate.');
    }

    /**
     * Get single Physical Audit detail
     */
    public function show(int $id): JsonResponse
    {
        $item = PhysicalAudit::with(['mitra', 'creator', 'archivedDocument', 'items'])->find($id);

        if (!$item) {
            return $this->notFound('Dokumen audit fisik tidak ditemukan.');
        }

        return $this->success($item, 'Detail dokumen audit fisik berhasil diambil.');
    }

    /**
     * Store new Physical Audit
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audit_number' => 'required|string|max:255|unique:physical_audits,audit_number',
            'type' => 'required|string|in:cash,persediaan,aset_tetap',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'required|string|max:255',
            'audit_period' => 'nullable|string|max:100',
            'day' => 'nullable|string|max:20',
            'audit_date' => 'nullable|date',
            'location_or_cashier' => 'nullable|string|max:255',
            'time_start' => 'nullable|string|max:10',
            'time_end' => 'nullable|string|max:10',
            'audit_data' => 'nullable|array',
            'total_amount' => 'nullable|numeric',
            'total_items_count' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,completed,archived',
            'pic_name' => 'nullable|string|max:255',
            'pic_title' => 'nullable|string|max:255',
            'auditor_name' => 'nullable|string|max:255',
            'auditor_title' => 'nullable|string|max:255',
            'items' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $user = $request->user();
            $validated['created_by'] = $user ? $user->id : null;
            $itemsData = $validated['items'] ?? [];
            unset($validated['items']);

            $audit = PhysicalAudit::create($validated);

            if (!empty($itemsData)) {
                foreach ($itemsData as $idx => $it) {
                    PhysicalAuditItem::create([
                        'physical_audit_id' => $audit->id,
                        'category' => $it['category'] ?? null,
                        'item_name' => $it['item_name'] ?? '',
                        'unit' => $it['unit'] ?? 'Unit',
                        'nominal' => $it['nominal'] ?? 0,
                        'quantity' => $it['quantity'] ?? 0,
                        'subtotal' => $it['subtotal'] ?? 0,
                        'condition_good' => $it['condition_good'] ?? true,
                        'condition_bad' => $it['condition_bad'] ?? false,
                        'notes' => $it['notes'] ?? null,
                        'sort_order' => $it['sort_order'] ?? $idx,
                    ]);
                }
            }

            return $this->created(
                $audit->load(['mitra', 'creator', 'items']),
                'Dokumen audit fisik berhasil disimpan.'
            );
        });
    }

    /**
     * Update existing Physical Audit
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $audit = PhysicalAudit::find($id);

        if (!$audit) {
            return $this->notFound('Dokumen audit fisik tidak ditemukan.');
        }

        $validated = $request->validate([
            'audit_number' => "sometimes|required|string|max:255|unique:physical_audits,audit_number,{$id}",
            'type' => 'sometimes|required|string|in:cash,persediaan,aset_tetap',
            'mitra_id' => 'nullable|exists:mitras,id',
            'client_name' => 'sometimes|required|string|max:255',
            'audit_period' => 'nullable|string|max:100',
            'day' => 'nullable|string|max:20',
            'audit_date' => 'nullable|date',
            'location_or_cashier' => 'nullable|string|max:255',
            'time_start' => 'nullable|string|max:10',
            'time_end' => 'nullable|string|max:10',
            'audit_data' => 'nullable|array',
            'total_amount' => 'nullable|numeric',
            'total_items_count' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,completed,archived',
            'pic_name' => 'nullable|string|max:255',
            'pic_title' => 'nullable|string|max:255',
            'auditor_name' => 'nullable|string|max:255',
            'auditor_title' => 'nullable|string|max:255',
            'items' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($audit, $validated, $request) {
            $user = $request->user();
            $validated['updated_by'] = $user ? $user->id : null;
            $itemsData = $validated['items'] ?? null;
            unset($validated['items']);

            $audit->update($validated);

            if (is_array($itemsData)) {
                // Refresh items
                $audit->items()->delete();
                foreach ($itemsData as $idx => $it) {
                    PhysicalAuditItem::create([
                        'physical_audit_id' => $audit->id,
                        'category' => $it['category'] ?? null,
                        'item_name' => $it['item_name'] ?? '',
                        'unit' => $it['unit'] ?? 'Unit',
                        'nominal' => $it['nominal'] ?? 0,
                        'quantity' => $it['quantity'] ?? 0,
                        'subtotal' => $it['subtotal'] ?? 0,
                        'condition_good' => $it['condition_good'] ?? true,
                        'condition_bad' => $it['condition_bad'] ?? false,
                        'notes' => $it['notes'] ?? null,
                        'sort_order' => $it['sort_order'] ?? $idx,
                    ]);
                }
            }

            return $this->success(
                $audit->fresh(['mitra', 'creator', 'items', 'archivedDocument']),
                'Dokumen audit fisik berhasil diperbarui.'
            );
        });
    }

    /**
     * Upload exported file (.xlsx / .pdf) directly to Cloudflare R2 and auto-archive into Documents
     */
    public function uploadToR2AndArchive(Request $request, int $id): JsonResponse
    {
        $audit = PhysicalAudit::find($id);

        if (!$audit) {
            return $this->notFound('Dokumen audit fisik tidak ditemukan.');
        }

        $request->validate([
            'file' => 'required|file|max:25600', // max 25MB
            'title' => 'nullable|string|max:255',
        ]);

        $uploadedFile = $request->file('file');
        $fileName = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $extension = $uploadedFile->getClientOriginalExtension() ?: 'xlsx';
        $fileSize = $uploadedFile->getSize();

        // 1. Generate path R2 yang rapi: opname/{type}/{tahun}/{slug}
        $year = $audit->audit_date ? date('Y', strtotime($audit->audit_date)) : date('Y');
        $clientSlug = Str::slug($audit->client_name) ?: 'klien';
        $folder = "opname/{$audit->type}/{$year}/{$clientSlug}";
        
        $uuidShort = substr(Str::uuid()->toString(), 0, 8);
        $fileSlug = Str::slug(pathinfo($fileName, PATHINFO_FILENAME));
        $fileKey = "{$folder}/{$fileSlug}-{$uuidShort}.{$extension}";

        try {
            // Upload to Cloudflare R2 Disk
            Storage::disk('r2')->put($fileKey, file_get_contents($uploadedFile), [
                'ContentType' => $mimeType,
            ]);

            // Direct / Temporary URL
            $fileUrl = $this->r2StorageService->generatePresignedDownloadUrl($fileKey, $fileName, 1440); // 24 hours
        } catch (\Throwable $e) {
            // Fallback ke local storage jika R2 credentials belum diset
            $fileKey = $uploadedFile->storeAs("opname/{$audit->type}", "{$fileSlug}-{$uuidShort}.{$extension}", 'public');
            $fileUrl = url("/storage/{$fileKey}");
        }

        // 2. Hubungkan otomatis ke Inventory Dokumen Klien / Kategori Opname
        $category = Category::where('name', 'like', '%Opname%')
            ->orWhere('name', 'like', '%Pemeriksaan%')
            ->orWhere('name', 'like', '%Kertas Kerja%')
            ->first();

        $user = $request->user();

        $docTitle = $request->input('title') ?: "Berita Acara {$audit->audit_number} - {$audit->client_name}";

        $document = Document::create([
            'mitra_id' => $audit->mitra_id,
            'category_id' => $category?->id,
            'file_key' => $fileKey,
            'file_name' => $docTitle . '.' . $extension,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'description' => "Dokumen Berita Acara Pemeriksaan Fisik ({$audit->type}) otomatis terarsip ke R2 Cloud Storage.",
            'tahun_berkas' => (string) $year,
            'tanggal_dokumen' => $audit->audit_date,
            'is_working_paper' => true,
            'review_status' => 'approved',
            'uploaded_by' => $user?->id,
        ]);

        // 3. Update Audit Record
        $audit->update([
            'r2_file_key' => $fileKey,
            'r2_file_url' => $fileUrl,
            'archived_document_id' => $document->id,
            'status' => 'archived',
        ]);

        return $this->success([
            'audit' => $audit->fresh(['mitra', 'archivedDocument']),
            'document' => $document,
            'file_key' => $fileKey,
            'file_url' => $fileUrl,
        ], 'File Berita Acara berhasil diunggah ke Cloudflare R2 dan diarsipkan ke Dokumen Klien.');
    }

    /**
     * Delete physical audit
     */
    public function destroy(int $id): JsonResponse
    {
        $audit = PhysicalAudit::find($id);

        if (!$audit) {
            return $this->notFound('Dokumen audit fisik tidak ditemukan.');
        }

        $audit->delete();

        return $this->success(null, 'Dokumen audit fisik berhasil dihapus.');
    }
}
