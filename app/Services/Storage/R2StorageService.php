<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class R2StorageService
{
    protected string $disk = 'r2';

    /**
     * Generate a presigned upload URL for direct client upload to R2.
     */
    public function generatePresignedUploadUrl(string $filename, string $mimeType, ?string $folder = 'documents'): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
        $originalBasename = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($originalBasename) ?: 'file';
        $uuidShort = substr(Str::uuid()->toString(), 0, 8);
        // Hierarchical path structure: [klien]/[tahun]/[kategori]/[file]
        $cleanFolder = trim($folder ?: 'documents', '/');
        $key = "{$cleanFolder}/{$slug}-{$uuidShort}.{$extension}";

        $uploadUrl = null;

        try {
            // Attempt to get client from S3/R2 Flysystem driver
            $disk = Storage::disk($this->disk);
            $client = $disk->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket' => config("filesystems.disks.{$this->disk}.bucket"),
                'Key' => $key,
                'ContentType' => $mimeType,
            ]);

            $request = $client->createPresignedRequest($command, '+20 minutes');
            $uploadUrl = (string) $request->getUri();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('R2 Presign Fallback to Mock: ' . $e->getMessage());
            // Graceful fallback for local development / testing if AWS S3 Flysystem package or credentials are not present
            $uploadUrl = url("/api/v1/storage/upload-mock?key=" . urlencode($key));
        }

        return [
            'upload_url' => $uploadUrl,
            'file_key' => $key,
            'expires_in' => 1200, // 20 minutes
        ];
    }

    /**
     * Generate a presigned download URL for R2 object.
     */
    public function generatePresignedDownloadUrl(string $fileKey, string $fileName, int $expirationMinutes = 30): string
    {
        try {
            return Storage::disk($this->disk)->temporaryUrl(
                $fileKey,
                now()->addMinutes($expirationMinutes),
                [
                    'ResponseContentDisposition' => 'attachment; filename="' . basename($fileName) . '"',
                ]
            );
        } catch (\Throwable $e) {
            return url("/storage/{$fileKey}");
        }
    }

    /**
     * Delete an object from R2 storage.
     */
    public function deleteObject(string $fileKey): bool
    {
        try {
            return Storage::disk($this->disk)->delete($fileKey);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
