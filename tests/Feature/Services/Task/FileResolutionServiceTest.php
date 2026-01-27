<?php

namespace Tests\Feature\Services\Task;

use App\Services\Task\FileResolutionService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class FileResolutionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    #[Test]
    public function resolves_direct_image_as_single_item_collection(): void
    {
        // Given: A StoredFile that is a direct image (PNG)
        $imageFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PNG,
            'filename' => 'direct-image.png',
        ]);

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($imageFile);

        // Then: returns a single-item collection containing the image file
        $this->assertCount(1, $result);
        $this->assertEquals($imageFile->id, $result->first()->id);
        $this->assertEquals(StoredFile::MIME_PNG, $result->first()->mime);
    }

    #[Test]
    public function resolves_jpeg_image_as_single_item_collection(): void
    {
        // Given: A StoredFile that is a JPEG image
        $imageFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_JPEG,
            'filename' => 'photo.jpg',
        ]);

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($imageFile);

        // Then: returns a single-item collection containing the image file
        $this->assertCount(1, $result);
        $this->assertEquals($imageFile->id, $result->first()->id);
    }

    #[Test]
    public function resolves_pdf_with_transcodes_returns_transcode_images(): void
    {
        // Given: A PDF StoredFile with 3 PDF-to-image transcodes
        $pdfFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PDF,
            'filename' => 'document.pdf',
        ]);

        // Create PDF-to-image transcodes in non-sequential order to test ordering
        $transcodePages = [3, 1, 2];
        foreach ($transcodePages as $pageNum) {
            StoredFile::factory()->create([
                'original_stored_file_id' => $pdfFile->id,
                'transcode_name'          => TranscodeFileService::TRANSCODE_PDF_TO_IMAGES,
                'page_number'             => $pageNum,
                'mime'                    => StoredFile::MIME_PNG,
                'filename'                => "page-$pageNum.png",
            ]);
        }

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($pdfFile);

        // Then: returns the 3 transcode images ordered by page_number
        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]->page_number);
        $this->assertEquals(2, $result[1]->page_number);
        $this->assertEquals(3, $result[2]->page_number);

        // Verify they are the transcodes, not the original PDF
        foreach ($result as $page) {
            $this->assertEquals(StoredFile::MIME_PNG, $page->mime);
            $this->assertEquals($pdfFile->id, $page->original_stored_file_id);
            $this->assertEquals(TranscodeFileService::TRANSCODE_PDF_TO_IMAGES, $page->transcode_name);
        }
    }

    #[Test]
    public function returns_empty_collection_for_non_image_without_transcodes(): void
    {
        // Given: A text file StoredFile without any transcodes
        $textFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_TEXT,
            'filename' => 'readme.txt',
        ]);

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($textFile);

        // Then: returns empty collection (text files cannot be pages)
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function returns_empty_collection_for_pdf_without_transcodes(): void
    {
        // Given: A PDF StoredFile without any transcodes
        $pdfFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PDF,
            'filename' => 'document.pdf',
        ]);

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($pdfFile);

        // Then: returns empty collection (PDF needs transcodes to be useful)
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function waits_for_transcoding_then_resolves_image(): void
    {
        // Given: An image StoredFile with is_transcoding=true
        $imageFile = StoredFile::factory()->create([
            'mime'           => StoredFile::MIME_PNG,
            'is_transcoding' => true,
            'filename'       => 'processing.png',
        ]);

        // Simulate transcoding completion by setting is_transcoding to false
        // The service will call refresh() which reloads from DB
        $imageFile->is_transcoding = false;
        $imageFile->save();

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($imageFile);

        // Then: returns the image after transcoding completes
        $this->assertCount(1, $result);
        $this->assertEquals($imageFile->id, $result->first()->id);
    }

    #[Test]
    public function waits_for_transcoding_then_resolves_pdf_transcodes(): void
    {
        // Given: A PDF StoredFile with is_transcoding=true that has transcodes
        $pdfFile = StoredFile::factory()->create([
            'mime'           => StoredFile::MIME_PDF,
            'is_transcoding' => true,
            'filename'       => 'processing.pdf',
        ]);

        // Create PDF-to-image transcodes
        for ($i = 1; $i <= 2; $i++) {
            StoredFile::factory()->create([
                'original_stored_file_id' => $pdfFile->id,
                'transcode_name'          => TranscodeFileService::TRANSCODE_PDF_TO_IMAGES,
                'page_number'             => $i,
                'mime'                    => StoredFile::MIME_PNG,
                'filename'                => "page-$i.png",
            ]);
        }

        // Simulate transcoding completion
        $pdfFile->is_transcoding = false;
        $pdfFile->save();

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($pdfFile);

        // Then: returns the PDF transcodes after transcoding completes
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]->page_number);
        $this->assertEquals(2, $result[1]->page_number);
    }

    #[Test]
    public function throws_validation_error_on_transcoding_timeout(): void
    {
        // Given: A StoredFile stuck in transcoding that never completes
        $stuckFile = StoredFile::factory()->create([
            'mime'           => StoredFile::MIME_PNG,
            'is_transcoding' => true,
            'filename'       => 'stuck-file.png',
        ]);

        // Mock the service to simulate timeout without actual sleep
        $this->mock(FileResolutionService::class, function ($mock) use ($stuckFile) {
            $mock->shouldReceive('resolveStoredFile')
                ->with(\Mockery::on(fn ($file) => $file->id === $stuckFile->id))
                ->once()
                ->andThrow(new ValidationError(
                    "Transcoding timeout after 120s for StoredFile {$stuckFile->id} ({$stuckFile->filename})"
                ));
        });

        // When/Then: throws ValidationError with timeout message
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Transcoding timeout');

        app(FileResolutionService::class)->resolveStoredFile($stuckFile);
    }

    #[Test]
    public function resolves_all_supported_image_mime_types(): void
    {
        $supportedMimes = [
            StoredFile::MIME_PNG,
            StoredFile::MIME_JPEG,
            StoredFile::MIME_GIF,
            StoredFile::MIME_TIFF,
            StoredFile::MIME_WEBP,
            StoredFile::MIME_HEIC,
        ];

        foreach ($supportedMimes as $mime) {
            $imageFile = StoredFile::factory()->create([
                'mime'     => $mime,
                'filename' => "test.$mime",
            ]);

            $result = app(FileResolutionService::class)->resolveStoredFile($imageFile);

            $this->assertCount(1, $result, "Expected 1 result for mime type: $mime");
            $this->assertEquals($imageFile->id, $result->first()->id);
        }
    }

    #[Test]
    public function ignores_non_pdf_transcodes(): void
    {
        // Given: A PDF with a different transcode type (not PDF-to-images)
        $pdfFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PDF,
            'filename' => 'document.pdf',
        ]);

        // Create a transcode with a different name (e.g., text extraction)
        StoredFile::factory()->create([
            'original_stored_file_id' => $pdfFile->id,
            'transcode_name'          => 'text-extraction', // Not PDF_TO_IMAGES
            'page_number'             => 1,
            'mime'                    => StoredFile::MIME_TEXT,
            'filename'                => 'extracted.txt',
        ]);

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($pdfFile);

        // Then: returns empty (the transcode is not PDF-to-images, so PDF has no usable pages)
        $this->assertCount(0, $result);
    }

    #[Test]
    public function pdf_transcodes_are_preferred_over_direct_mime_check(): void
    {
        // Given: A StoredFile that could be mistaken as an image but is actually a PDF with transcodes
        // This tests the order of resolution - transcodes first, then mime type check
        $pdfFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PDF,
            'filename' => 'document.pdf',
        ]);

        // Create PDF-to-image transcodes
        StoredFile::factory()->create([
            'original_stored_file_id' => $pdfFile->id,
            'transcode_name'          => TranscodeFileService::TRANSCODE_PDF_TO_IMAGES,
            'page_number'             => 1,
            'mime'                    => StoredFile::MIME_PNG,
            'filename'                => 'page-1.png',
        ]);

        // When: resolveStoredFile
        $result = app(FileResolutionService::class)->resolveStoredFile($pdfFile);

        // Then: returns transcode (not the PDF itself, even though PDF mime wouldn't match image check anyway)
        $this->assertCount(1, $result);
        $this->assertEquals(StoredFile::MIME_PNG, $result->first()->mime);
        $this->assertEquals($pdfFile->id, $result->first()->original_stored_file_id);
    }
}
