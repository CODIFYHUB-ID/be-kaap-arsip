<?php

namespace App\Services\Document;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
use App\Models\Category;
use App\Models\Document;
use App\Services\ActivityLog\ActivityLogService;
use App\Services\Storage\R2StorageService;

class DocumentService
{
    public function __construct(
        protected R2StorageService $r2StorageService,
        protected ActivityLogService $activityLogService
    ) {}

    public function create(array $data, int $userId, ?string $ip = null, ?string $ua = null): Document
    {
        $data['uploaded_by'] = $userId;

        // Auto-detect if category or file name represents Final Audit Report (LAI / Final Archive)
        if (! isset($data['is_final_archive'])) {
            $isFinal = false;
            if (! empty($data['category_id'])) {
                $category = \App\Models\Category::find($data['category_id']);
                if ($category && (
                    str_contains(strtolower($category->name), 'laporan audit') ||
                    str_contains(strtolower($category->name), 'lai') ||
                    str_contains(strtolower($category->name), 'arsip final')
                )) {
                    $isFinal = true;
                }
            }
            if (isset($data['file_name']) && (
                str_contains(strtolower($data['file_name']), 'lai') ||
                str_contains(strtolower($data['file_name']), 'laporan auditor') ||
                str_contains(strtolower($data['file_name']), 'final')
            )) {
                $isFinal = true;
            }
            $data['is_final_archive'] = $isFinal;
        }

        if (! isset($data['is_working_paper'])) {
            $isKKP = false;
            if (! empty($data['category_id'])) {
                $category = Category::find($data['category_id']);
                if ($category && (str_contains(strtolower($category->name), 'kertas kerja') || str_contains(strtolower($category->name), 'kkp'))) {
                    $isKKP = true;
                }
            }
            if (isset($data['file_name']) && (str_contains(strtolower($data['file_name']), 'kkp') || str_contains(strtolower($data['file_name']), 'kertas kerja'))) {
                $isKKP = true;
            }
            $data['is_working_paper'] = $isKKP;
        }

        if (! empty($data['is_working_paper']) && empty($data['review_status'])) {
            $data['review_status'] = 'draft';
        }

        if (! empty($data['is_final_archive']) && empty($data['approval_status'])) {
            // Final audit documents require Owner approval before release
            $data['approval_status'] = 'pending';
        } else if (empty($data['approval_status'])) {
            $data['approval_status'] = 'approved';
        }

        $document = Document::create($data);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPLOAD,
            module: ActivityModule::DOCUMENT,
            description: "Unggah dokumen: {$document->file_name}" . ($document->is_final_archive ? " (Menunggu Approval Pimpinan)" : ""),
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        // Dispatch in-app notification to Manager, Partner, Owner & Admin
        \App\Services\Notification\NotificationDispatcher::notifyAll(
            title: $document->is_final_archive ? "Arsip Final Memerlukan Persetujuan" : "Dokumen Baru Diunggah",
            message: "Berkas '{$document->file_name}' telah diunggah ke sistem.",
            type: "document",
            url: "/mitra",
            meta: [
                'document_id' => $document->id,
                'mitra_id' => $document->mitra_id,
                'file_name' => $document->file_name,
                'approval_status' => $document->approval_status,
            ],
            excludeUserId: $userId
        );

        return $document->load(['mitra', 'category', 'uploader']);
    }

    public function update(Document $document, array $data, int $userId, ?string $ip = null, ?string $ua = null): Document
    {
        $document->update($data);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPDATE,
            module: ActivityModule::DOCUMENT,
            description: "Update metadata dokumen: {$document->file_name}",
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        return $document->load(['mitra', 'category', 'uploader']);
    }

    public function delete(Document $document, int $userId, ?string $ip = null, ?string $ua = null): bool
    {
        $fileName = $document->file_name;
        $id = $document->id;

        // Cascade soft delete to associated letters
        \App\Models\Letter::where('document_id', $id)->delete();

        $deleted = $document->delete();

        if ($deleted) {
            $this->activityLogService->log(
                userId: $userId,
                action: ActivityAction::DELETE,
                module: ActivityModule::DOCUMENT,
                description: "Hapus dokumen ke Recycle Bin: {$fileName}",
                resourceType: Document::class,
                resourceId: $id,
                ipAddress: $ip,
                userAgent: $ua
            );
        }

        return $deleted;
    }

    public function getDownloadUrl(Document $document, int $userId, ?string $ip = null, ?string $ua = null): string
    {
        // Enforce Owner approval for final archive files
        if ($document->is_final_archive && $document->approval_status !== 'approved') {
            $user = \App\Models\User::find($userId);
            if (! $user || ! $user->hasAnyRole(['Owner', 'Super Admin'])) {
                abort(403, 'Berkas Laporan Auditor Independen (LAI) / Arsip Final sedang menunggu persetujuan Pimpinan KAP (Owner).');
            }
        }

        // Enforce assignment scoping for Auditor role
        $requestUser = \App\Models\User::find($userId);
        if ($requestUser && $requestUser->isAuditor() && ! $requestUser->hasAnyRole(['Owner', 'Super Admin', 'Admin'])) {
            $assignedMitraIds = \App\Models\AuditorAssignment::where('auditor_id', $userId)
                ->where('status', 'active')
                ->pluck('mitra_id')
                ->toArray();
            if (! in_array($document->mitra_id, $assignedMitraIds) && $document->uploaded_by !== $userId) {
                abort(403, 'Akses ditolak. Anda hanya berwenang mengunduh berkas bukti audit untuk klien yang ditugaskan kepada Anda.');
            }
        }

        $url = $this->r2StorageService->generatePresignedDownloadUrl($document->file_key, $document->file_name);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::DOWNLOAD,
            module: ActivityModule::DOCUMENT,
            description: "Download dokumen: {$document->file_name}",
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        return $url;
    }

    public function approve(Document $document, int $userId, ?string $notes = null, ?string $ip = null, ?string $ua = null): Document
    {
        $document->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::APPROVE,
            module: ActivityModule::DOCUMENT,
            description: "Menyetujui penerbitan Laporan Audit / Arsip Final: {$document->file_name}",
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        \App\Services\Notification\NotificationDispatcher::notifyAll(
            title: "Arsip Final Telah Disetujui Pimpinan",
            message: "Berkas '{$document->file_name}' telah disetujui resmi oleh Pimpinan KAP.",
            type: "document",
            url: "/mitra",
            meta: [
                'document_id' => $document->id,
                'approval_status' => 'approved',
            ],
            excludeUserId: $userId
        );

        return $document->load(['mitra', 'category', 'uploader', 'approver']);
    }

    public function reject(Document $document, int $userId, string $notes, ?string $ip = null, ?string $ua = null): Document
    {
        $document->update([
            'approval_status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::REJECT,
            module: ActivityModule::DOCUMENT,
            description: "Menolak persetujuan Arsip Final: {$document->file_name}. Catatan: {$notes}",
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        if ($document->uploaded_by) {
            \App\Services\Notification\NotificationDispatcher::notifyUser(
                userId: $document->uploaded_by,
                title: "Arsip Final Perlu Revisi",
                message: "Berkas '{$document->file_name}' ditolak oleh Pimpinan KAP. Catatan: {$notes}",
                type: "document",
                url: "/mitra",
                meta: [
                    'document_id' => $document->id,
                    'approval_status' => 'rejected',
                    'notes' => $notes,
                ]
            );
        }

        return $document->load(['mitra', 'category', 'uploader', 'approver']);
    }

    public function getPendingApprovals(?int $perPage = 15)
    {
        return Document::with(['mitra:id,name,company_name', 'category:id,name', 'uploader:id,name'])
            ->where('approval_status', 'pending')
            ->orderByDesc('created_at')
            ->paginate($perPage ?? 15);
    }

    public function getBundle(int $mitraId, ?string $year = null): array
    {
        $query = Document::where('mitra_id', $mitraId)
            ->where(function ($q) {
                $q->where('approval_status', 'approved')
                  ->orWhere('is_final_archive', false);
            });

        if ($year && $year !== 'all') {
            $query->where('tahun_berkas', $year);
        }

        $docs = $query->orderBy('category_id')->get();
        $bundle = [];

        foreach ($docs as $doc) {
            $bundle[] = [
                'id' => $doc->id,
                'file_name' => $doc->file_name,
                'category' => $doc->category?->name ?? 'Umum',
                'tahun_berkas' => $doc->tahun_berkas,
                'download_url' => $this->r2StorageService->generatePresignedDownloadUrl($doc->file_key, $doc->file_name),
            ];
        }

        return $bundle;
    }

    public function reviewKKP(Document $document, int $userId, string $status, ?string $notes = null, ?string $ip = null, ?string $ua = null): Document
    {
        $document->update([
            'review_status' => $status,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPDATE,
            module: ActivityModule::DOCUMENT,
            description: "Penelaahan KKP ({$status}): {$document->file_name}",
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
        );

        \App\Services\Notification\NotificationDispatcher::notifyAll(
            title: "Penelaahan KKP Diperbarui",
            message: "Status Kertas Kerja '{$document->file_name}' kini: {$status}.",
            type: "document",
            url: "/auditor/kkp",
            meta: [
                'document_id' => $document->id,
                'review_status' => $status,
            ],
            excludeUserId: $userId
        );

        return $document->load(['mitra', 'category', 'uploader', 'reviewer']);
    }
}
