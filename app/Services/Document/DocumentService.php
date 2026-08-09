<?php

namespace App\Services\Document;

use App\Enums\ActivityAction;
use App\Enums\ActivityModule;
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
        $document = Document::create($data);

        $this->activityLogService->log(
            userId: $userId,
            action: ActivityAction::UPLOAD,
            module: ActivityModule::DOCUMENT,
            description: "Unggah dokumen: {$document->file_name}",
            resourceType: Document::class,
            resourceId: $document->id,
            ipAddress: $ip,
            userAgent: $ua
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
}
