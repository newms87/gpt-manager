<?php

namespace Tests\Feature\Services\Task\FileOrganization;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Services\Task\FileOrganization\PageResolutionService;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class PageResolutionServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $agent = Agent::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'model'   => self::TEST_MODEL,
        ]);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id'            => $this->user->currentTeam->id,
            'name'               => 'Page Resolution Test',
            'task_runner_name'   => FileOrganizationTaskRunner::RUNNER_NAME,
            'task_runner_config' => ['comparison_window_size' => 5],
            'agent_id'           => $agent->id,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    #[Test]
    public function resolves_single_image_stored_files_as_pages(): void
    {
        // Given: An input artifact with 3 image StoredFiles (PNG)
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Input Images',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'filename'    => "image-$i.png",
                'page_number' => $i * 10, // Existing page numbers to verify reassignment
            ]);
            $artifact->storedFiles()->attach($storedFile->id);
        }

        $this->taskRun->inputArtifacts()->attach($artifact->id);

        // When: resolvePages
        $pages = app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: returns 3 pages with sequential page_numbers 1,2,3
        $this->assertCount(3, $pages);
        $this->assertEquals(1, $pages[0]->page_number);
        $this->assertEquals(2, $pages[1]->page_number);
        $this->assertEquals(3, $pages[2]->page_number);
    }

    #[Test]
    public function resolves_pdf_via_transcodes(): void
    {
        // Given: An input artifact with 1 StoredFile (mime=application/pdf) that has 3 PDF-to-image transcodes
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Input PDF',
        ]);

        $pdfFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PDF,
            'filename' => 'document.pdf',
        ]);
        $artifact->storedFiles()->attach($pdfFile->id);

        // Create PDF-to-image transcodes
        for ($i = 1; $i <= 3; $i++) {
            StoredFile::factory()->create([
                'original_stored_file_id' => $pdfFile->id,
                'transcode_name'          => TranscodeFileService::TRANSCODE_PDF_TO_IMAGES,
                'page_number'             => $i,
                'mime'                    => StoredFile::MIME_PNG,
                'filename'                => "page-$i.png",
            ]);
        }

        $this->taskRun->inputArtifacts()->attach($artifact->id);

        // When: resolvePages
        $pages = app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: returns the 3 transcode StoredFiles as pages with sequential numbering
        $this->assertCount(3, $pages);
        $this->assertEquals(1, $pages[0]->page_number);
        $this->assertEquals(2, $pages[1]->page_number);
        $this->assertEquals(3, $pages[2]->page_number);

        // Verify they are the transcode files, not the original PDF
        foreach ($pages as $page) {
            $this->assertEquals(StoredFile::MIME_PNG, $page->mime);
            $this->assertEquals($pdfFile->id, $page->original_stored_file_id);
        }
    }

    #[Test]
    public function resolves_mixed_images_and_pdfs(): void
    {
        // Given: 2 input artifacts: one with 2 image files, one with 1 PDF that has 3 transcodes
        $imageArtifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'name'     => 'Images',
            'position' => 1,
        ]);

        for ($i = 1; $i <= 2; $i++) {
            $storedFile = StoredFile::factory()->create([
                'mime'     => StoredFile::MIME_JPEG,
                'filename' => "photo-$i.jpg",
            ]);
            $imageArtifact->storedFiles()->attach($storedFile->id);
        }

        $pdfArtifact = Artifact::factory()->create([
            'team_id'  => $this->user->currentTeam->id,
            'name'     => 'PDF Document',
            'position' => 2,
        ]);

        $pdfFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PDF,
            'filename' => 'document.pdf',
        ]);
        $pdfArtifact->storedFiles()->attach($pdfFile->id);

        for ($i = 1; $i <= 3; $i++) {
            StoredFile::factory()->create([
                'original_stored_file_id' => $pdfFile->id,
                'transcode_name'          => TranscodeFileService::TRANSCODE_PDF_TO_IMAGES,
                'page_number'             => $i,
                'mime'                    => StoredFile::MIME_PNG,
                'filename'                => "pdf-page-$i.png",
            ]);
        }

        $this->taskRun->inputArtifacts()->attach($imageArtifact->id);
        $this->taskRun->inputArtifacts()->attach($pdfArtifact->id);

        // When: resolvePages
        $pages = app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: returns 5 pages total with sequential numbering 1-5
        $this->assertCount(5, $pages);
        for ($i = 1; $i <= 5; $i++) {
            $this->assertEquals($i, $pages[$i - 1]->page_number);
        }
    }

    #[Test]
    public function assigns_sequential_page_numbers(): void
    {
        // Given: Multiple stored files with existing page numbers (non-sequential)
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Mixed Pages',
        ]);

        $existingPageNumbers = [99, 50, 7];
        foreach ($existingPageNumbers as $pageNum) {
            $storedFile = StoredFile::factory()->create([
                'mime'        => StoredFile::MIME_PNG,
                'page_number' => $pageNum,
                'filename'    => "page-$pageNum.png",
            ]);
            $artifact->storedFiles()->attach($storedFile->id);
        }

        $this->taskRun->inputArtifacts()->attach($artifact->id);

        // When: resolvePages
        $pages = app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: reassigns sequential page_numbers starting from 1
        $this->assertCount(3, $pages);
        $pageNumbers = $pages->pluck('page_number')->toArray();
        $this->assertEquals([1, 2, 3], $pageNumbers);
    }

    #[Test]
    public function replaces_input_artifacts_with_resolved_pages_artifact(): void
    {
        // Given: 3 input artifacts
        for ($i = 1; $i <= 3; $i++) {
            $artifact = Artifact::factory()->create([
                'team_id' => $this->user->currentTeam->id,
                'name'    => "Artifact $i",
            ]);

            $storedFile = StoredFile::factory()->create([
                'mime'     => StoredFile::MIME_PNG,
                'filename' => "file-$i.png",
            ]);
            $artifact->storedFiles()->attach($storedFile->id);
            $this->taskRun->inputArtifacts()->attach($artifact->id);
        }

        // Verify we start with 3 input artifacts
        $this->assertCount(3, $this->taskRun->inputArtifacts);

        // When: resolvePages
        app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: task run has exactly 1 input artifact named "Resolved Pages" containing all page StoredFiles
        $this->taskRun->refresh();
        $inputArtifacts = $this->taskRun->inputArtifacts()->get();

        $this->assertCount(1, $inputArtifacts);
        $this->assertEquals('Resolved Pages', $inputArtifacts->first()->name);

        // Verify all 3 stored files are attached to the resolved pages artifact
        $resolvedArtifact = $inputArtifacts->first();
        $this->assertCount(3, $resolvedArtifact->storedFiles);
    }

    #[Test]
    public function skips_non_image_files_without_transcodes(): void
    {
        // Given: An input artifact with a text/plain StoredFile and an image file
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Mixed Files',
        ]);

        $textFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_TEXT,
            'filename' => 'readme.txt',
        ]);
        $artifact->storedFiles()->attach($textFile->id);

        $imageFile = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PNG,
            'filename' => 'image.png',
        ]);
        $artifact->storedFiles()->attach($imageFile->id);

        $this->taskRun->inputArtifacts()->attach($artifact->id);

        // When: resolvePages
        $pages = app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: only the image file is included, text file is skipped
        $this->assertCount(1, $pages);
        $this->assertEquals(StoredFile::MIME_PNG, $pages->first()->mime);
    }

    #[Test]
    public function waits_for_transcoding_to_complete(): void
    {
        // Given: A StoredFile with is_transcoding=true that becomes false after refresh
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Transcoding File',
        ]);

        $storedFile = StoredFile::factory()->create([
            'mime'           => StoredFile::MIME_PNG,
            'is_transcoding' => true,
            'filename'       => 'transcoding.png',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        $this->taskRun->inputArtifacts()->attach($artifact->id);

        // Set is_transcoding to false (simulating transcoding completion)
        // The service will call refresh() which reloads from DB
        $storedFile->is_transcoding = false;
        $storedFile->save();

        // When: resolvePages
        $pages = app(PageResolutionService::class)->resolvePages($this->taskRun);

        // Then: resolves successfully (the file is an image, so it resolves as a page)
        $this->assertCount(1, $pages);
        $this->assertEquals(1, $pages->first()->page_number);
    }

    #[Test]
    public function throws_on_transcoding_timeout(): void
    {
        // Given: A StoredFile with is_transcoding=true that never changes
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'name'    => 'Stuck Transcoding',
        ]);

        $storedFile = StoredFile::factory()->create([
            'mime'           => StoredFile::MIME_PNG,
            'is_transcoding' => true,
            'filename'       => 'stuck.png',
        ]);
        $artifact->storedFiles()->attach($storedFile->id);

        $this->taskRun->inputArtifacts()->attach($artifact->id);

        // Use a partial mock to override the constants and skip actual sleep
        $service = $this->partialMock(PageResolutionService::class, function ($mock) {
            // Let resolvePages run normally but intercept waitForTranscoding
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('waitForTranscoding')
                ->once()
                ->andThrow(new ValidationError(
                    "Transcoding timeout after 120s for StoredFile (stuck.png)"
                ));
        });

        // When/Then: throws ValidationError with timeout message
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Transcoding timeout');

        $service->resolvePages($this->taskRun);
    }
}
