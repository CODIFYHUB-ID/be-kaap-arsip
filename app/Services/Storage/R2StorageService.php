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
    public function generatePresignedUploadUrl(string $filename, string $mimeType, ?string $folder = ''): array
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
        $originalBasename = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($originalBasename) ?: 'file';
        $uuidShort = substr(Str::uuid()->toString(), 0, 8);
        // Hierarchical path structure: [klien]/[tahun]/[kategori]/[file] (langsung di root bucket)
        $cleanFolder = trim($folder ?: 'umum', '/');
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
     * Generate a download/preview URL for R2 object.
     * Menggunakan CLOUDFLARE_R2_URL langsung (jika ada) untuk menghindari benturan
     * otentikasi dual (X-Amz-Signature vs Authorization header di browser).
     */
    public function generatePresignedDownloadUrl(string $fileKey, string $fileName, int $expirationMinutes = 30): string
    {
        $publicUrl = config("filesystems.disks.{$this->disk}.url");
        if (!empty($publicUrl)) {
            $trimmedUrl = rtrim($publicUrl, '/');
            $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($fileKey, '/'))));
            return "{$trimmedUrl}/{$encodedKey}";
        }

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
     * Store raw file content directly into R2 storage.
     */
    public function putContent(string $fileKey, string $content, string $mimeType = 'application/pdf'): bool
    {
        try {
            return Storage::disk($this->disk)->put($fileKey, $content, [
                'ContentType' => $mimeType,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('R2 putContent Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if object exists in R2 storage.
     */
    public function exists(string $fileKey): bool
    {
        try {
            return Storage::disk($this->disk)->exists($fileKey);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return direct binary response stream for preview or download.
     */
    public function streamResponse(string $fileKey, string $fileName, ?string $mimeType = null, bool $inline = true)
    {
        $mime = $mimeType ?: 'application/pdf';
        $disposition = $inline ? 'inline' : 'attachment';

        try {
            $disk = Storage::disk($this->disk);
            if ($disk->exists($fileKey)) {
                return $disk->response($fileKey, basename($fileName), [
                    'Content-Type' => $mime,
                    'Content-Disposition' => "{$disposition}; filename=\"" . basename($fileName) . "\"",
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('R2 Stream Response Error: ' . $e->getMessage());
        }

        // Fallback: check local storage disk
        if (Storage::disk('local')->exists($fileKey)) {
            return Storage::disk('local')->response($fileKey, basename($fileName), [
                'Content-Type' => $mime,
                'Content-Disposition' => "{$disposition}; filename=\"" . basename($fileName) . "\"",
            ]);
        }

        abort(404, 'Berkas fisik dokumen tidak ditemukan di penyimpanan server atau cloud.');
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
