<?php

namespace App\Services\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Facades\Cache;
use Newms87\Danx\Exceptions\ApiException;

class GoogleDriveFolderService
{
    use HasDebugLogging;

    /**
     * Find or create a folder in Google Drive
     * Uses team-scoped caching to avoid repeated API calls
     */
    public function findOrCreateFolder(GoogleDocsApi $api, string $folderName, ?string $parentFolderId = null): string
    {
        $teamId   = team()->id;
        $cacheKey = $this->getCacheKey($teamId, $folderName, $parentFolderId);

        // Check cache first
        $cachedFolderId = Cache::get($cacheKey);
        if ($cachedFolderId) {
            // Verify cached folder still exists in Google Drive
            if ($this->verifyFolderExists($api, $cachedFolderId)) {
                static::log('Using cached folder ID', [
                    'folder_name' => $folderName,
                    'folder_id'   => $cachedFolderId,
                    'team_id'     => $teamId,
                ]);

                return $cachedFolderId;
            }

            // Cached folder no longer exists, invalidate cache
            static::log('Cached folder no longer exists, invalidating cache', [
                'folder_name' => $folderName,
                'folder_id'   => $cachedFolderId,
                'team_id'     => $teamId,
            ]);
            Cache::forget($cacheKey);
        }

        // Search for existing folder
        $folderId = $this->searchFolder($api, $folderName, $parentFolderId);

        // Create folder if not found
        if (!$folderId) {
            static::log('Folder not found, creating new folder', [
                'folder_name'      => $folderName,
                'parent_folder_id' => $parentFolderId,
                'team_id'          => $teamId,
            ]);
            $folderId = $this->createFolder($api, $folderName, $parentFolderId);
        } else {
            static::log('Found existing folder', [
                'folder_name' => $folderName,
                'folder_id'   => $folderId,
                'team_id'     => $teamId,
            ]);
        }

        // Cache for 1 day
        Cache::put($cacheKey, $folderId, now()->addDay());

        return $folderId;
    }

    /**
     * Create a folder in Google Drive
     */
    public function createFolder(GoogleDocsApi $api, string $folderName, ?string $parentFolderId = null): string
    {
        try {
            $metadata = [
                'name'     => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];

            if ($parentFolderId) {
                $metadata['parents'] = [$parentFolderId];
            }

            static::log('Creating folder via Drive API', [
                'folder_name'      => $folderName,
                'parent_folder_id' => $parentFolderId,
                'metadata'         => $metadata,
            ]);

            $response = $api->postToDriveApi('files', $metadata);

            $folderData = $response->json();

            // Log the response for debugging
            static::log('Drive API create folder response', [
                'status'   => $response->status(),
                'response' => $folderData,
            ]);

            if (!$response->successful()) {
                throw new ApiException('Drive API create folder request failed: ' . ($folderData['error']['message'] ?? 'Unknown error'));
            }

            $folderId = $folderData['id'] ?? null;

            if (!$folderId) {
                throw new ApiException('Failed to get folder ID from Drive API create response');
            }

            static::log('Folder created successfully', [
                'folder_name' => $folderName,
                'folder_id'   => $folderId,
            ]);

            return $folderId;

        } catch (\Exception $e) {
            static::log('Failed to create folder', [
                'folder_name' => $folderName,
                'error'       => $e->getMessage(),
            ]);

            throw new ApiException('Failed to create Google Drive folder: ' . $e->getMessage());
        }
    }

    /**
     * Search for a folder in Google Drive
     */
    public function searchFolder(GoogleDocsApi $api, string $folderName, ?string $parentFolderId = null): ?string
    {
        try {
            // Build query
            $query = "name='" . addslashes($folderName) . "' and mimeType='application/vnd.google-apps.folder' and trashed=false";

            if ($parentFolderId) {
                $query .= " and '" . addslashes($parentFolderId) . "' in parents";
            }

            static::log('Searching for folder via Drive API', [
                'folder_name'      => $folderName,
                'parent_folder_id' => $parentFolderId,
                'query'            => $query,
            ]);

            $response = $api->getToDriveApi('files', [
                'q'        => $query,
                'fields'   => 'files(id, name)',
                'pageSize' => 1,
            ]);

            $searchData = $response->json();

            // Log the response for debugging
            static::log('Drive API search folder response', [
                'status'   => $response->status(),
                'response' => $searchData,
            ]);

            if (!$response->successful()) {
                throw new ApiException('Drive API search request failed: ' . ($searchData['error']['message'] ?? 'Unknown error'));
            }

            $files = $searchData['files'] ?? [];

            if (empty($files)) {
                static::log('No folder found', [
                    'folder_name' => $folderName,
                ]);

                return null;
            }

            $folderId = $files[0]['id'] ?? null;

            static::log('Folder found', [
                'folder_name' => $folderName,
                'folder_id'   => $folderId,
            ]);

            return $folderId;

        } catch (\Exception $e) {
            static::log('Failed to search for folder', [
                'folder_name' => $folderName,
                'error'       => $e->getMessage(),
            ]);

            // Don't throw on search failure - just return null to trigger creation
            return null;
        }
    }

    /**
     * Verify that a folder exists in Google Drive by ID
     */
    public function verifyFolderExists(GoogleDocsApi $api, string $folderId): bool
    {
        try {
            static::log('Verifying folder exists', [
                'folder_id' => $folderId,
            ]);

            $response = $api->getToDriveApi("files/{$folderId}", [
                'fields' => 'id,name,mimeType,trashed',
            ]);

            if (!$response->successful()) {
                static::log('Folder verification failed', [
                    'folder_id' => $folderId,
                    'status'    => $response->status(),
                ]);

                return false;
            }

            $fileData = $response->json();

            // Verify it's a folder and not trashed
            $isFolder  = ($fileData['mimeType'] ?? null) === 'application/vnd.google-apps.folder';
            $isTrashed = $fileData['trashed'] ?? false;

            if (!$isFolder || $isTrashed) {
                static::log('Folder is invalid or trashed', [
                    'folder_id'  => $folderId,
                    'is_folder'  => $isFolder,
                    'is_trashed' => $isTrashed,
                ]);

                return false;
            }

            static::log('Folder exists and is valid', [
                'folder_id'   => $folderId,
                'folder_name' => $fileData['name'] ?? 'unknown',
            ]);

            return true;

        } catch (\Exception $e) {
            static::log('Error verifying folder existence', [
                'folder_id' => $folderId,
                'error'     => $e->getMessage(),
            ]);

            // If we can't verify, assume it doesn't exist to trigger recreation
            return false;
        }
    }

    /**
     * Generate cache key for folder ID
     */
    protected function getCacheKey(int $teamId, string $folderName, ?string $parentFolderId): string
    {
        $key = "google_drive_folder_{$teamId}_{$folderName}";

        if ($parentFolderId) {
            $key .= "_{$parentFolderId}";
        }

        return $key;
    }
}
