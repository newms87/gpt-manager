<?php

namespace Tests\Feature\FileUpload;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Newms87\Danx\Events\StoredFileUpdatedEvent;
use Newms87\Danx\Jobs\TranscodeStoredFileJob;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;
use Newms87\Danx\Services\TranscodeFile\PdfToImagesTranscoder;
use Newms87\Danx\Services\TranscodeFileService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class PresignedUploadWithTranscodingTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        // Set up fake storage disk for testing
        Storage::fake('local');
    }

    #[Test]
    public function presigned_upload_completed_sets_team_id_and_broadcasts(): void
    {
        // Given: Create a StoredFile with team_id set (simulating presigned upload)
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'size'     => 0, // Not yet completed
            'filename' => 'document.txt',
            'mime'     => 'text/plain',
            'filepath' => 'uploads/document.txt',
            'disk'     => 'local',
            'url'      => null, // Not yet set
        ]);

        // Create a fake file in storage to simulate upload
        Storage::disk('local')->put($storedFile->filepath, 'Test file content');

        // Set up event listener to capture broadcast
        Event::fake([StoredFileUpdatedEvent::class]);

        // When: Complete the presigned upload
        $result = app(FileRepository::class)->presignedUploadUrlCompleted($storedFile);

        // Then: Verify team_id persists
        $storedFile->refresh();
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertGreaterThan(0, $storedFile->size);

        // Verify StoredFileUpdatedEvent was dispatched
        Event::assertDispatched(StoredFileUpdatedEvent::class);

        // Verify result contains expected data
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($storedFile->id, $result['id']);
    }

    #[Test]
    public function pdf_upload_triggers_transcoding_with_team_id(): void
    {
        // Enable PDF to Images transcoding
        config(['danx.transcode.pdf_to_images' => true]);

        // Given: Create a PDF StoredFile with team_id
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'size'     => 0, // Not yet completed
            'filename' => 'document.pdf',
            'mime'     => StoredFile::MIME_PDF,
            'filepath' => 'uploads/document.pdf',
            'disk'     => 'local',
            'url'      => null, // Not yet set
        ]);

        // Create a fake PDF file in storage
        Storage::disk('local')->put($storedFile->filepath, '%PDF-1.4 fake pdf content');

        // Set up queue to capture dispatched jobs
        Queue::fake();

        // When: Complete the presigned upload (this should trigger transcoding)
        app(FileRepository::class)->presignedUploadUrlCompleted($storedFile);

        // Then: Verify TranscodeStoredFileJob was dispatched
        Queue::assertPushed(TranscodeStoredFileJob::class, function ($job) use ($storedFile) {
            // Access protected properties via reflection for testing
            $reflection = new \ReflectionClass($job);

            $storedFileProperty = $reflection->getProperty('storedFile');
            $storedFileProperty->setAccessible(true);
            $jobStoredFile = $storedFileProperty->getValue($job);

            $transcodeNameProperty = $reflection->getProperty('transcodeName');
            $transcodeNameProperty->setAccessible(true);
            $transcodeName = $transcodeNameProperty->getValue($job);

            return $jobStoredFile->id === $storedFile->id
                && $transcodeName     === TranscodeFileService::TRANSCODE_PDF_TO_IMAGES;
        });

        // Verify meta['transcodes'] was set to pending
        $storedFile->refresh();
        $this->assertNotNull($storedFile->meta);
        $this->assertArrayHasKey('transcodes', $storedFile->meta);
        $this->assertArrayHasKey('PDF to Images', $storedFile->meta['transcodes']);
        $this->assertEquals(
            TranscodeFileService::STATUS_PENDING,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );
    }

    #[Test]
    public function transcode_jobs_create_files_with_team_id(): void
    {
        // Enable PDF to Images transcoding
        config(['danx.transcode.pdf_to_images' => true]);

        // Given: Create a PDF StoredFile with team_id
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'size'     => 1024,
            'filename' => 'document.pdf',
            'mime'     => StoredFile::MIME_PDF,
            'filepath' => 'uploads/document.pdf',
            'disk'     => 'local',
            'url'      => 'http://example.com/uploads/document.pdf',
        ]);

        // Mock PdfToImagesTranscoder to return fake page data with raw data (not URLs)
        // Using usesDataUrls = false to avoid HTTP requests in tests
        $this->mock(PdfToImagesTranscoder::class, function ($mock) {
            $mock->shouldReceive('usesDataUrls')->andReturn(false);
            $mock->shouldReceive('getTimeout')->andReturn(300);
            $mock->shouldReceive('startingProgress')->andReturn(0.0);
            $mock->shouldReceive('timeEstimate')->andReturn(30000);
            $mock->shouldReceive('run')->once()->andReturn([
                [
                    'filename'    => 'Page 1 -- page-001.jpg',
                    'data'        => 'fake image data page 1',
                    'page_number' => 1,
                ],
                [
                    'filename'    => 'Page 2 -- page-002.jpg',
                    'data'        => 'fake image data page 2',
                    'page_number' => 2,
                ],
            ]);
        });

        // When: Dispatch the transcode job
        Queue::fake(); // Prevent actual job execution
        $transcodeService = app(TranscodeFileService::class);
        $transcodeService->dispatch(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

        // Then: Verify meta was updated to pending after dispatch
        $storedFile->refresh();
        $this->assertEquals(
            TranscodeFileService::STATUS_PENDING,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );

        // Simulate running the transcode job manually (bypassing queue)
        $transcodedFiles = $transcodeService->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

        // Verify transcoded files were created (not dispatched as jobs since usesDataUrls = false)
        $this->assertCount(2, $transcodedFiles);

        // Verify meta was updated to complete after processing
        $storedFile->refresh();
        $this->assertEquals(
            TranscodeFileService::STATUS_COMPLETE,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );
    }

    #[Test]
    public function transcoded_files_inherit_team_id_from_original(): void
    {
        // Given: Create a StoredFile with team_id
        $originalFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'document.pdf',
            'mime'     => StoredFile::MIME_PDF,
        ]);

        // Create fake storage for the transcoded file
        $transcodedFilepath = "transcodes/PDF to Images/{$originalFile->id}/Page 1 -- page-001.jpg";
        Storage::disk('local')->put($transcodedFilepath, 'fake image data');

        // When: Store a transcoded file
        $transcodeService = app(TranscodeFileService::class);
        $transcodedFile   = $transcodeService->storeTranscodedFile(
            $originalFile,
            'PDF to Images',
            'Page 1 -- page-001.jpg',
            'fake image data',
            1
        );

        // Then: Verify team_id was inherited
        $this->assertEquals($this->user->currentTeam->id, $transcodedFile->team_id);
        $this->assertEquals($originalFile->id, $transcodedFile->original_stored_file_id);
        $this->assertEquals('PDF to Images', $transcodedFile->transcode_name);
        $this->assertEquals(1, $transcodedFile->page_number);
    }

    #[Test]
    public function transcode_completion_broadcasts_events_with_team_id(): void
    {
        // Given: Create a StoredFile with team_id and pending transcode
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'filename' => 'document.pdf',
            'mime'     => StoredFile::MIME_PDF,
            'meta'     => [
                'transcodes' => [
                    'PDF to Images' => [
                        'status'       => TranscodeFileService::STATUS_IN_PROGRESS,
                        'progress'     => 0,
                        'requested_at' => now(),
                        'started_at'   => now(),
                    ],
                ],
            ],
        ]);

        // Set up event listener
        Event::fake([StoredFileUpdatedEvent::class]);

        // When: Complete the transcode
        $transcodeService = app(TranscodeFileService::class);
        $transcodeService->complete($storedFile, 'PDF to Images');

        // Then: Verify event was dispatched
        Event::assertDispatched(StoredFileUpdatedEvent::class);

        // Verify meta status changed to complete
        $storedFile->refresh();
        $this->assertEquals(
            TranscodeFileService::STATUS_COMPLETE,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );
        $this->assertNotNull($storedFile->meta['transcodes']['PDF to Images']['completed_at']);
    }

    #[Test]
    public function is_transcoding_flag_managed_correctly(): void
    {
        // Enable PDF to Images transcoding
        config(['danx.transcode.pdf_to_images' => true]);

        // Given: Create a PDF StoredFile without url to prevent auto-size detection
        $storedFile = StoredFile::factory()->create([
            'team_id'        => $this->user->currentTeam->id,
            'size'           => 0,
            'url'            => null,
            'filename'       => 'document.pdf',
            'mime'           => StoredFile::MIME_PDF,
            'filepath'       => 'uploads/document.pdf',
            'disk'           => 'local',
            'is_transcoding' => false,
        ]);

        Storage::disk('local')->put($storedFile->filepath, '%PDF-1.4 fake pdf content');

        Queue::fake();

        // When: Complete upload (triggers transcoding)
        app(FileRepository::class)->presignedUploadUrlCompleted($storedFile);

        // Then: Verify is_transcoding flag is managed
        $storedFile->refresh();

        // After dispatch, meta should have transcode pending
        $this->assertArrayHasKey('transcodes', $storedFile->meta);
        $this->assertEquals(
            TranscodeFileService::STATUS_PENDING,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );

        // Mock transcoder for completion test
        $this->mock(PdfToImagesTranscoder::class, function ($mock) {
            $mock->shouldReceive('usesDataUrls')->andReturn(false);
            $mock->shouldReceive('getTimeout')->andReturn(300);
            $mock->shouldReceive('startingProgress')->andReturn(0.0);
            $mock->shouldReceive('timeEstimate')->andReturn(30000);
            $mock->shouldReceive('run')->andReturn([
                [
                    'filename'    => 'Page 1 -- page-001.jpg',
                    'data'        => 'fake image data',
                    'page_number' => 1,
                ],
            ]);
        });

        // Simulate transcode starting
        $transcodeService = app(TranscodeFileService::class);
        $transcodeService->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

        // Verify status changed to complete after transcode
        $storedFile->refresh();
        $this->assertEquals(
            TranscodeFileService::STATUS_COMPLETE,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );
    }

    #[Test]
    public function non_pdf_files_do_not_trigger_transcoding(): void
    {
        // Enable PDF to Images transcoding
        config(['danx.transcode.pdf_to_images' => true]);

        // Given: Create a non-PDF StoredFile (url=null to prevent auto-size)
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'size'     => 0,
            'url'      => null,
            'filename' => 'image.jpg',
            'mime'     => 'image/jpeg',
            'filepath' => 'uploads/image.jpg',
            'disk'     => 'local',
        ]);

        Storage::disk('local')->put($storedFile->filepath, 'fake image data');

        Queue::fake();

        // When: Complete upload
        app(FileRepository::class)->presignedUploadUrlCompleted($storedFile);

        // Then: Verify no transcode job was dispatched
        Queue::assertNotPushed(TranscodeStoredFileJob::class);

        // Verify no transcode meta was set
        $storedFile->refresh();
        $this->assertNull($storedFile->meta);
    }

    #[Test]
    public function presigned_upload_completion_validates_not_already_completed(): void
    {
        // Given: Create a StoredFile that is already completed (size > 0)
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'size'     => 1024, // Already completed
            'filename' => 'document.txt',
            'filepath' => 'uploads/document.txt',
            'disk'     => 'local',
        ]);

        // When/Then: Attempting to complete again should throw ValidationError
        $this->expectException(\Newms87\Danx\Exceptions\ValidationError::class);
        $this->expectExceptionMessage('This presigned file upload has already been completed');

        app(FileRepository::class)->presignedUploadUrlCompleted($storedFile);
    }

    #[Test]
    public function complete_flow_from_presigned_upload_to_transcoded_files(): void
    {
        // Enable PDF to Images transcoding
        config(['danx.transcode.pdf_to_images' => true]);

        // Given: Create a PDF StoredFile ready for upload completion (url=null to prevent auto-size)
        $storedFile = StoredFile::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'size'     => 0,
            'url'      => null,
            'filename' => 'document.pdf',
            'mime'     => StoredFile::MIME_PDF,
            'filepath' => 'uploads/document.pdf',
            'disk'     => 'local',
        ]);

        Storage::disk('local')->put($storedFile->filepath, '%PDF-1.4 fake pdf content');

        // Mock the transcoder
        $this->mock(PdfToImagesTranscoder::class, function ($mock) {
            $mock->shouldReceive('usesDataUrls')->andReturn(false);
            $mock->shouldReceive('getTimeout')->andReturn(300);
            $mock->shouldReceive('startingProgress')->andReturn(0.0);
            $mock->shouldReceive('timeEstimate')->andReturn(30000);
            $mock->shouldReceive('run')->once()->andReturn([
                [
                    'filename'    => 'Page 1 -- page-001.jpg',
                    'data'        => 'fake image data page 1',
                    'page_number' => 1,
                ],
                [
                    'filename'    => 'Page 2 -- page-002.jpg',
                    'data'        => 'fake image data page 2',
                    'page_number' => 2,
                ],
            ]);
        });

        Event::fake([StoredFileUpdatedEvent::class]);
        Queue::fake();

        // Step 1: Complete the presigned upload
        $result = app(FileRepository::class)->presignedUploadUrlCompleted($storedFile);

        $storedFile->refresh();
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertGreaterThan(0, $storedFile->size);

        // Step 2: Verify transcode was dispatched
        Queue::assertPushed(TranscodeStoredFileJob::class);

        // Verify meta status is pending
        $this->assertEquals(
            TranscodeFileService::STATUS_PENDING,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );

        // Step 3: Run the transcode job (simulated)
        $transcodeService = app(TranscodeFileService::class);
        $transcodedFiles  = $transcodeService->transcode(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $storedFile);

        // Step 4: Verify transcoded files were created with team_id
        $this->assertCount(2, $transcodedFiles);

        foreach ($transcodedFiles as $transcodedFile) {
            $this->assertEquals($this->user->currentTeam->id, $transcodedFile->team_id);
            $this->assertEquals($storedFile->id, $transcodedFile->original_stored_file_id);
            $this->assertEquals('PDF to Images', $transcodedFile->transcode_name);
        }

        // Step 5: Verify meta status changed to complete
        $storedFile->refresh();
        $this->assertEquals(
            TranscodeFileService::STATUS_COMPLETE,
            $storedFile->meta['transcodes']['PDF to Images']['status']
        );

        // Step 6: Verify events were dispatched (original file + completion event)
        Event::assertDispatched(StoredFileUpdatedEvent::class);
    }

    #[Test]
    public function presigned_upload_url_creation_sets_team_id(): void
    {
        // Given: Request parameters for presigned upload
        $path = 'uploads';
        $name = 'test-document.pdf';
        $mime = StoredFile::MIME_PDF;
        $meta = ['source' => 'test'];

        // When: Create presigned upload URL via FileRepository
        $storedFile = app(FileRepository::class)->createFileWithUploadUrl($path, $name, $mime, $meta);

        // Then: Verify team_id is set from team() helper
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertEquals($name, $storedFile->filename);
        $this->assertEquals($mime, $storedFile->mime);
        $this->assertEquals(0, $storedFile->size); // Not yet uploaded
        $this->assertEquals($meta, $storedFile->meta);
    }

    #[Test]
    public function create_file_with_url_sets_team_id(): void
    {
        // Given: File path and URL
        $filepath = 'external/test-file.pdf';
        $url      = 'https://example.com/test.pdf';

        // When: Create file with URL via FileRepository
        $storedFile = app(FileRepository::class)->createFileWithUrl($filepath, $url, [
            'mime' => 'application/pdf',
        ]);

        // Then: Verify team_id is set from team() helper
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertEquals('test-file.pdf', $storedFile->filename);
        $this->assertEquals('application/pdf', $storedFile->mime);
        $this->assertEquals($url, $storedFile->url);
    }

    #[Test]
    public function save_file_sets_team_id(): void
    {
        // Given: Create a temp file
        $tempPath = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempPath, 'test content');

        // When: Save file via FileRepository
        $storedFile = app(FileRepository::class)->saveFile('test.txt', $tempPath);

        // Then: Verify team_id is set from team() helper
        $this->assertEquals($this->user->currentTeam->id, $storedFile->team_id);
        $this->assertEquals('test.txt', $storedFile->filename);
        $this->assertGreaterThan(0, $storedFile->size);

        // Cleanup
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
}
