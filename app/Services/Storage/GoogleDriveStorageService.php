<?php

namespace App\Services\Storage;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveStorageService
{
    protected ?Client $client = null;
    protected ?Drive $service = null;
    protected ?string $rootFolderId = null;

    public function __construct()
    {
        $this->rootFolderId = env('GOOGLE_DRIVE_ROOT_FOLDER_ID');

        $clientId = env('GOOGLE_DRIVE_CLIENT_ID');
        $clientSecret = env('GOOGLE_DRIVE_CLIENT_SECRET');
        $refreshToken = env('GOOGLE_DRIVE_REFRESH_TOKEN');

        if (!empty($clientId) && !empty($clientSecret) && !empty($refreshToken)) {
            try {
                $this->client = new Client();
                $this->client->setClientId($clientId);
                $this->client->setClientSecret($clientSecret);
                $this->client->addScope(Drive::DRIVE);
                $token = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (isset($token['access_token'])) {
                    $this->client->setAccessToken($token);
                    $this->service = new Drive($this->client);
                    return;
                } else {
                    Log::error('GoogleDrive fetchAccessTokenWithRefreshToken error: ' . json_encode($token));
                }
            } catch (\Throwable $e) {
                Log::error('GoogleDriveStorageService OAuth init failed: ' . $e->getMessage());
            }
        }

        $credentialsPath = base_path(env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON', 'storage/app/google-drive-credentials.json'));
        if (file_exists($credentialsPath)) {
            try {
                $this->client = new Client();
                $this->client->setAuthConfig($credentialsPath);
                $this->client->addScope(Drive::DRIVE);
                $this->service = new Drive($this->client);
            } catch (\Throwable $e) {
                Log::error('GoogleDriveStorageService ServiceAccount init failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Check if service account is configured and ready.
     */
    public function isConfigured(): bool
    {
        return $this->service !== null && !empty($this->rootFolderId);
    }

    /**
     * Ensure a nested folder hierarchy exists under root, returning deepest folder ID.
     * Example $folderPath: "codifyhub/2026/surat-konfirmasi"
     */
    public function findOrCreateFolderHierarchy(string $folderPath): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($folderPath, '/'))));
        $currentParentId = $this->rootFolderId;

        foreach ($segments as $segment) {
            $folderId = $this->findFolderByName($segment, $currentParentId);
            if (!$folderId) {
                $folderId = $this->createFolder($segment, $currentParentId);
            }
            if (!$folderId) {
                return null;
            }
            $currentParentId = $folderId;
        }

        return $currentParentId;
    }

    /**
     * Find a folder by name inside a parent folder.
     */
    protected function findFolderByName(string $name, string $parentId): ?string
    {
        try {
            $safeName = str_replace("'", "\\'", $name);
            $query = "mimeType = 'application/vnd.google-apps.folder' and name = '{$safeName}' and '{$parentId}' in parents and trashed = false";

            $response = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)',
                'pageSize' => 1,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);

            $files = $response->getFiles();
            return !empty($files) ? $files[0]->getId() : null;
        } catch (\Throwable $e) {
            Log::error("GoogleDrive findFolderByName error for '{$name}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new folder inside a parent folder.
     */
    protected function createFolder(string $name, string $parentId): ?string
    {
        try {
            $folderMetadata = new DriveFile([
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentId],
            ]);

            $folder = $this->service->files->create($folderMetadata, [
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]);

            return $folder->getId();
        } catch (\Throwable $e) {
            Log::error("GoogleDrive createFolder error for '{$name}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload or backup raw file content directly into Google Drive in the specified folder hierarchy.
     */
    public function uploadFile(string $folderPath, string $filename, string $content, string $mimeType = 'application/pdf'): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $targetFolderId = $this->findOrCreateFolderHierarchy($folderPath) ?: $this->rootFolderId;

            $fileMetadata = new DriveFile([
                'name' => basename($filename),
                'parents' => [$targetFolderId],
            ]);

            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, name, webViewLink, webContentLink',
                'supportsAllDrives' => true,
            ]);

            return [
                'drive_file_id' => $file->getId(),
                'web_view_link' => $file->getWebViewLink(),
                'web_content_link' => $file->getWebContentLink(),
            ];
        } catch (\Throwable $e) {
            Log::error("GoogleDrive uploadFile error for '{$filename}': " . $e->getMessage());
            return null;
        }
    }
}
