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
    public function generatePresignedUploadUrl(string $filename, string $mimeType): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $uuid = Str::uuid()->toString();
        $datePath = now()->format('Y/m');
        $key = "documents/{$datePath}/{$uuid}.{$extension}";

        $adapter = Storage::disk($this->disk)->getAdapter();
        
        // If S3 driver is configured, create presigned URL; fallback for local dev
        try {
            $client = Storage::disk($this->disk)->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket' => config("filesystems.disks.{$this->disk}.bucket"),
                'Key' => $key,
                'ContentType' => $mimeType,
            ]);

            $request = $client->createPresignedRequest($command, '+20 minutes');
            $uploadUrl = (string) $request->getUri();
        } catch (\Throwable $e) {
            // Fallback for dev / testing if S3 client is not fully configured
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
