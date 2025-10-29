<?php

namespace Tests\Unit\Services\GoogleDocs;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Services\GoogleDocs\GoogleDriveFolderService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class GoogleDriveFolderServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected GoogleDriveFolderService $service;

    protected GoogleDocsApi $mockApi;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = new GoogleDriveFolderService();
        $this->mockApi = $this->createMock(GoogleDocsApi::class);
    }

    // ============================================================
    // verifyFolderExists() Tests
    // ============================================================

    #[Test]
    public function verifyFolderExists_withValidFolder_returnsTrue(): void
    {
        // Given
        $folderId = 'test_folder_id_123';
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn(true);
        $response->method('json')->willReturn([
            'id'       => $folderId,
            'name'     => 'Test Folder',
            'mimeType' => 'application/vnd.google-apps.folder',
            'trashed'  => false,
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$folderId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willReturn($response);

        // When
        $result = $this->service->verifyFolderExists($this->mockApi, $folderId);

        // Then
        $this->assertTrue($result);
    }

    #[Test]
    public function verifyFolderExists_whenFolderDoesNotExist_returnsFalse(): void
    {
        // Given
        $folderId = 'nonexistent_folder_id';
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn(false);
        $response->method('status')->willReturn(404);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$folderId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willReturn($response);

        // When
        $result = $this->service->verifyFolderExists($this->mockApi, $folderId);

        // Then
        $this->assertFalse($result);
    }

    #[Test]
    public function verifyFolderExists_whenFolderIsTrashed_returnsFalse(): void
    {
        // Given
        $folderId = 'trashed_folder_id';
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn(true);
        $response->method('json')->willReturn([
            'id'       => $folderId,
            'name'     => 'Trashed Folder',
            'mimeType' => 'application/vnd.google-apps.folder',
            'trashed'  => true,
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$folderId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willReturn($response);

        // When
        $result = $this->service->verifyFolderExists($this->mockApi, $folderId);

        // Then
        $this->assertFalse($result);
    }

    #[Test]
    public function verifyFolderExists_whenItemIsNotFolder_returnsFalse(): void
    {
        // Given
        $fileId   = 'file_not_folder_id';
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn(true);
        $response->method('json')->willReturn([
            'id'       => $fileId,
            'name'     => 'Document.docx',
            'mimeType' => 'application/vnd.google-apps.document', // Not a folder
            'trashed'  => false,
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$fileId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willReturn($response);

        // When
        $result = $this->service->verifyFolderExists($this->mockApi, $fileId);

        // Then
        $this->assertFalse($result);
    }

    #[Test]
    public function verifyFolderExists_whenApiRequestFails_returnsFalse(): void
    {
        // Given
        $folderId = 'error_folder_id';
        $response = $this->createMock(Response::class);
        $response->method('successful')->willReturn(false);
        $response->method('status')->willReturn(500);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$folderId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willReturn($response);

        // When
        $result = $this->service->verifyFolderExists($this->mockApi, $folderId);

        // Then
        $this->assertFalse($result);
    }

    #[Test]
    public function verifyFolderExists_whenExceptionThrown_returnsFalse(): void
    {
        // Given
        $folderId = 'exception_folder_id';

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$folderId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willThrowException(new \Exception('Network timeout'));

        // When
        $result = $this->service->verifyFolderExists($this->mockApi, $folderId);

        // Then
        $this->assertFalse($result);
    }

    // ============================================================
    // findOrCreateFolder() with Cache Validation Tests
    // ============================================================

    #[Test]
    public function findOrCreateFolder_withValidCachedFolder_returnsCachedId(): void
    {
        // Given
        $folderName      = 'Test Folder';
        $cachedFolderId  = 'cached_folder_123';
        $parentFolderId  = null;
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}";

        Cache::put($cacheKey, $cachedFolderId, now()->addDay());

        // Mock verification response - folder exists and is valid
        $verifyResponse = $this->createMock(Response::class);
        $verifyResponse->method('successful')->willReturn(true);
        $verifyResponse->method('json')->willReturn([
            'id'       => $cachedFolderId,
            'name'     => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'trashed'  => false,
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with("files/{$cachedFolderId}", ['fields' => 'id,name,mimeType,trashed'])
            ->willReturn($verifyResponse);

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($cachedFolderId, $result);
        $this->assertTrue(Cache::has($cacheKey)); // Cache should still be valid
    }

    #[Test]
    public function findOrCreateFolder_whenCachedFolderDoesNotExist_invalidatesCacheAndSearches(): void
    {
        // Given
        $folderName      = 'Test Folder';
        $cachedFolderId  = 'stale_cached_folder';
        $actualFolderId  = 'found_folder_456';
        $parentFolderId  = null;
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}";

        Cache::put($cacheKey, $cachedFolderId, now()->addDay());

        // Mock verification response - folder doesn't exist (404)
        $verifyResponse = $this->createMock(Response::class);
        $verifyResponse->method('successful')->willReturn(false);
        $verifyResponse->method('status')->willReturn(404);

        // Mock search response - finds a different folder
        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn([
            'files' => [
                ['id' => $actualFolderId, 'name' => $folderName],
            ],
        ]);

        $this->mockApi->expects($this->exactly(2))
            ->method('getToDriveApi')
            ->willReturnCallback(function ($endpoint, $params) use ($cachedFolderId, $verifyResponse, $searchResponse) {
                if ($endpoint === "files/{$cachedFolderId}") {
                    return $verifyResponse;
                }
                if ($endpoint === 'files') {
                    return $searchResponse;
                }
            });

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($actualFolderId, $result);
        // Cache should be updated with new folder ID
        $this->assertEquals($actualFolderId, Cache::get($cacheKey));
    }

    #[Test]
    public function findOrCreateFolder_whenCachedFolderIsTrashed_invalidatesCacheAndCreatesNew(): void
    {
        // Given
        $folderName      = 'Test Folder';
        $cachedFolderId  = 'trashed_cached_folder';
        $newFolderId     = 'new_folder_789';
        $parentFolderId  = null;
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}";

        Cache::put($cacheKey, $cachedFolderId, now()->addDay());

        // Mock verification response - folder is trashed
        $verifyResponse = $this->createMock(Response::class);
        $verifyResponse->method('successful')->willReturn(true);
        $verifyResponse->method('json')->willReturn([
            'id'       => $cachedFolderId,
            'name'     => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'trashed'  => true,
        ]);

        // Mock search response - no folders found
        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn(['files' => []]);

        // Mock create response - creates new folder
        $createResponse = $this->createMock(Response::class);
        $createResponse->method('successful')->willReturn(true);
        $createResponse->method('status')->willReturn(200);
        $createResponse->method('json')->willReturn(['id' => $newFolderId]);

        $this->mockApi->expects($this->exactly(2))
            ->method('getToDriveApi')
            ->willReturnCallback(function ($endpoint, $params) use ($cachedFolderId, $verifyResponse, $searchResponse) {
                if ($endpoint === "files/{$cachedFolderId}") {
                    return $verifyResponse;
                }
                if ($endpoint === 'files') {
                    return $searchResponse;
                }
            });

        $this->mockApi->expects($this->once())
            ->method('postToDriveApi')
            ->with('files', [
                'name'     => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ])
            ->willReturn($createResponse);

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($newFolderId, $result);
        // Cache should be updated with new folder ID
        $this->assertEquals($newFolderId, Cache::get($cacheKey));
    }

    #[Test]
    public function findOrCreateFolder_withoutCache_searchesAndReturnsExistingFolder(): void
    {
        // Given
        $folderName      = 'Test Folder';
        $foundFolderId   = 'existing_folder_123';
        $parentFolderId  = null;
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}";

        // Ensure no cache exists
        Cache::forget($cacheKey);

        // Mock search response - finds existing folder
        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn([
            'files' => [
                ['id' => $foundFolderId, 'name' => $folderName],
            ],
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with('files', $this->callback(function ($params) use ($folderName) {
                return isset($params['q']) && str_contains($params['q'], $folderName);
            }))
            ->willReturn($searchResponse);

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($foundFolderId, $result);
        // Cache should be populated
        $this->assertEquals($foundFolderId, Cache::get($cacheKey));
    }

    #[Test]
    public function findOrCreateFolder_whenNoFolderFound_createsNewFolder(): void
    {
        // Given
        $folderName      = 'New Test Folder';
        $newFolderId     = 'created_folder_999';
        $parentFolderId  = null;
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}";

        // Ensure no cache exists
        Cache::forget($cacheKey);

        // Mock search response - no folders found
        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn(['files' => []]);

        // Mock create response - creates new folder
        $createResponse = $this->createMock(Response::class);
        $createResponse->method('successful')->willReturn(true);
        $createResponse->method('status')->willReturn(200);
        $createResponse->method('json')->willReturn(['id' => $newFolderId]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->willReturn($searchResponse);

        $this->mockApi->expects($this->once())
            ->method('postToDriveApi')
            ->with('files', [
                'name'     => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ])
            ->willReturn($createResponse);

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($newFolderId, $result);
        // Cache should be populated with new folder ID
        $this->assertEquals($newFolderId, Cache::get($cacheKey));
    }

    #[Test]
    public function findOrCreateFolder_withParentFolder_includesParentInQuery(): void
    {
        // Given
        $folderName      = 'Child Folder';
        $foundFolderId   = 'child_folder_123';
        $parentFolderId  = 'parent_folder_456';
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}_{$parentFolderId}";

        // Ensure no cache exists
        Cache::forget($cacheKey);

        // Mock search response
        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn([
            'files' => [
                ['id' => $foundFolderId, 'name' => $folderName],
            ],
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->with('files', $this->callback(function ($params) use ($folderName, $parentFolderId) {
                return isset($params['q'])
                    && str_contains($params['q'], $folderName)
                    && str_contains($params['q'], $parentFolderId);
            }))
            ->willReturn($searchResponse);

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($foundFolderId, $result);
        // Cache key should include parent folder ID
        $this->assertEquals($foundFolderId, Cache::get($cacheKey));
    }

    #[Test]
    public function findOrCreateFolder_withParentFolder_createsWithParent(): void
    {
        // Given
        $folderName      = 'Child Folder';
        $newFolderId     = 'new_child_folder_789';
        $parentFolderId  = 'parent_folder_456';
        $teamId          = $this->user->currentTeam->id;
        $cacheKey        = "google_drive_folder_{$teamId}_{$folderName}_{$parentFolderId}";

        // Ensure no cache exists
        Cache::forget($cacheKey);

        // Mock search response - no folders found
        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn(['files' => []]);

        // Mock create response
        $createResponse = $this->createMock(Response::class);
        $createResponse->method('successful')->willReturn(true);
        $createResponse->method('status')->willReturn(200);
        $createResponse->method('json')->willReturn(['id' => $newFolderId]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->willReturn($searchResponse);

        $this->mockApi->expects($this->once())
            ->method('postToDriveApi')
            ->with('files', [
                'name'     => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$parentFolderId],
            ])
            ->willReturn($createResponse);

        // When
        $result = $this->service->findOrCreateFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($newFolderId, $result);
        $this->assertEquals($newFolderId, Cache::get($cacheKey));
    }

    // ============================================================
    // Additional Coverage Tests
    // ============================================================

    #[Test]
    public function searchFolder_withValidQuery_returnsFoundFolderId(): void
    {
        // Given
        $folderName     = 'Search Test Folder';
        $foundFolderId  = 'search_folder_123';
        $parentFolderId = null;

        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn([
            'files' => [
                ['id' => $foundFolderId, 'name' => $folderName],
            ],
        ]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->willReturn($searchResponse);

        // When
        $result = $this->service->searchFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($foundFolderId, $result);
    }

    #[Test]
    public function searchFolder_whenNoFoldersFound_returnsNull(): void
    {
        // Given
        $folderName     = 'Nonexistent Folder';
        $parentFolderId = null;

        $searchResponse = $this->createMock(Response::class);
        $searchResponse->method('successful')->willReturn(true);
        $searchResponse->method('json')->willReturn(['files' => []]);

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->willReturn($searchResponse);

        // When
        $result = $this->service->searchFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertNull($result);
    }

    #[Test]
    public function searchFolder_whenSearchFails_returnsNull(): void
    {
        // Given
        $folderName     = 'Error Folder';
        $parentFolderId = null;

        $this->mockApi->expects($this->once())
            ->method('getToDriveApi')
            ->willThrowException(new \Exception('Search API error'));

        // When
        $result = $this->service->searchFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertNull($result);
    }

    #[Test]
    public function createFolder_withValidInput_returnsFolderId(): void
    {
        // Given
        $folderName     = 'New Folder';
        $newFolderId    = 'new_folder_123';
        $parentFolderId = null;

        $createResponse = $this->createMock(Response::class);
        $createResponse->method('successful')->willReturn(true);
        $createResponse->method('status')->willReturn(200);
        $createResponse->method('json')->willReturn(['id' => $newFolderId]);

        $this->mockApi->expects($this->once())
            ->method('postToDriveApi')
            ->with('files', [
                'name'     => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ])
            ->willReturn($createResponse);

        // When
        $result = $this->service->createFolder($this->mockApi, $folderName, $parentFolderId);

        // Then
        $this->assertEquals($newFolderId, $result);
    }
}
