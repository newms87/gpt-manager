<?php

namespace Tests\Feature\Services\Task;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ImageToTextTranscoderTaskRunner;
use App\Services\Task\TranscodePrerequisiteService;
use Newms87\Danx\Models\Utilities\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TranscodePrerequisiteServiceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    private TranscodePrerequisiteService $service;

    private TaskDefinition $taskDefinition;

    private TaskRun $taskRun;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();

        $this->service = app(TranscodePrerequisiteService::class);

        $this->taskDefinition = TaskDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $this->taskRun = TaskRun::factory()->create([
            'task_definition_id' => $this->taskDefinition->id,
        ]);
    }

    #[Test]
    public function getArtifactsNeedingTranscode_returns_artifacts_without_llm_transcode(): void
    {
        // Create artifact with file that has NO transcode
        $artifactWithoutTranscode = $this->createArtifactWithFile(StoredFile::MIME_JPEG, hasTranscode: false);

        // Create artifact with file that HAS transcode
        $artifactWithTranscode = $this->createArtifactWithFile(StoredFile::MIME_PNG, hasTranscode: true);

        $artifacts = collect([$artifactWithoutTranscode, $artifactWithTranscode]);

        $artifactsNeedingTranscode = $this->service->getArtifactsNeedingTranscode($artifacts);

        $this->assertCount(1, $artifactsNeedingTranscode);

        // The artifact without transcode should be in the result
        $this->assertEquals($artifactWithoutTranscode->id, $artifactsNeedingTranscode->first()->id);
    }

    #[Test]
    public function getArtifactsNeedingTranscode_excludes_non_transcodable_mimes(): void
    {
        // Create artifacts with non-transcodable MIME types
        $textArtifact = $this->createArtifactWithFile(StoredFile::MIME_TEXT, hasTranscode: false);
        $jsonArtifact = $this->createArtifactWithFile(StoredFile::MIME_JSON, hasTranscode: false);

        // Create artifact with transcodable MIME type (no transcode yet)
        $imageArtifact = $this->createArtifactWithFile(StoredFile::MIME_JPEG, hasTranscode: false);

        $artifacts = collect([$textArtifact, $jsonArtifact, $imageArtifact]);

        $artifactsNeedingTranscode = $this->service->getArtifactsNeedingTranscode($artifacts);

        // Only the image artifact should need transcoding, not text or json
        $this->assertCount(1, $artifactsNeedingTranscode);
        $this->assertEquals($imageArtifact->id, $artifactsNeedingTranscode->first()->id);
    }

    #[Test]
    public function getArtifactsNeedingTranscode_returns_empty_when_all_transcoded(): void
    {
        // Create artifacts with files that ALL have transcodes
        $artifact1 = $this->createArtifactWithFile(StoredFile::MIME_JPEG, hasTranscode: true);
        $artifact2 = $this->createArtifactWithFile(StoredFile::MIME_PNG, hasTranscode: true);
        $artifact3 = $this->createArtifactWithFile(StoredFile::MIME_PDF, hasTranscode: true);

        $artifacts = collect([$artifact1, $artifact2, $artifact3]);

        $artifactsNeedingTranscode = $this->service->getArtifactsNeedingTranscode($artifacts);

        $this->assertCount(0, $artifactsNeedingTranscode);
        $this->assertTrue($artifactsNeedingTranscode->isEmpty());
    }

    #[Test]
    public function createTranscodeProcesses_creates_one_process_per_artifact(): void
    {
        // Create artifacts with stored files
        $artifacts = collect([
            $this->createArtifactWithFile(StoredFile::MIME_JPEG, hasTranscode: false),
            $this->createArtifactWithFile(StoredFile::MIME_PNG, hasTranscode: false),
            $this->createArtifactWithFile(StoredFile::MIME_PDF, hasTranscode: false),
        ]);

        $processes = $this->service->createTranscodeProcesses($this->taskRun, $artifacts);

        // Verify one process per artifact
        $this->assertCount(3, $processes);

        foreach ($processes as $index => $process) {
            $this->assertInstanceOf(TaskProcess::class, $process);
            $this->assertEquals(TranscodePrerequisiteService::OPERATION_TRANSCODE, $process->operation);
            $this->assertEmpty($process->meta); // No stored_file_id in meta anymore
            $this->assertTrue($process->is_ready);
            $this->assertEquals($this->taskRun->id, $process->task_run_id);
            $this->assertStringContains('Transcode:', $process->name);

            // Verify input artifact is attached
            $inputArtifact = $process->inputArtifacts()->first();
            $this->assertNotNull($inputArtifact);
            $this->assertEquals($artifacts[$index]->id, $inputArtifact->id);
        }
    }

    #[Test]
    public function isTranscodingComplete_returns_true_when_all_complete(): void
    {
        // Create transcode processes with completed_at set
        TaskProcess::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'operation'    => TranscodePrerequisiteService::OPERATION_TRANSCODE,
            'completed_at' => now(),
        ]);

        TaskProcess::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'operation'    => TranscodePrerequisiteService::OPERATION_TRANSCODE,
            'completed_at' => now(),
        ]);

        $isComplete = $this->service->isTranscodingComplete($this->taskRun);

        $this->assertTrue($isComplete);
    }

    #[Test]
    public function isTranscodingComplete_returns_false_when_some_pending(): void
    {
        // Create completed transcode process
        TaskProcess::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'operation'    => TranscodePrerequisiteService::OPERATION_TRANSCODE,
            'completed_at' => now(),
        ]);

        // Create pending transcode process (no completed_at)
        TaskProcess::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'operation'    => TranscodePrerequisiteService::OPERATION_TRANSCODE,
            'completed_at' => null,
        ]);

        $isComplete = $this->service->isTranscodingComplete($this->taskRun);

        $this->assertFalse($isComplete);
    }

    #[Test]
    public function isTranscodingComplete_returns_true_when_no_processes(): void
    {
        // No transcode processes created for this task run

        $isComplete = $this->service->isTranscodingComplete($this->taskRun);

        // Should return true because there's nothing to wait for
        $this->assertTrue($isComplete);
    }

    #[Test]
    public function isTranscodingComplete_ignores_non_transcode_operations(): void
    {
        // Create a non-transcode process that is NOT completed
        TaskProcess::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'operation'    => 'SomeOtherOperation',
            'completed_at' => null,
        ]);

        // Create a completed transcode process
        TaskProcess::factory()->create([
            'task_run_id'  => $this->taskRun->id,
            'operation'    => TranscodePrerequisiteService::OPERATION_TRANSCODE,
            'completed_at' => now(),
        ]);

        $isComplete = $this->service->isTranscodingComplete($this->taskRun);

        // Should return true because only transcode operations are checked
        $this->assertTrue($isComplete);
    }

    #[Test]
    public function getArtifactsNeedingTranscode_handles_pdf_files(): void
    {
        // PDF is a transcodable MIME type
        $pdfArtifact = $this->createArtifactWithFile(StoredFile::MIME_PDF, hasTranscode: false);

        $artifacts = collect([$pdfArtifact]);

        $artifactsNeedingTranscode = $this->service->getArtifactsNeedingTranscode($artifacts);

        $this->assertCount(1, $artifactsNeedingTranscode);
        $this->assertEquals($pdfArtifact->id, $artifactsNeedingTranscode->first()->id);
    }

    #[Test]
    public function getArtifactsNeedingTranscode_handles_multiple_files_per_artifact(): void
    {
        // Create an artifact with multiple stored files
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $file1 = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_JPEG,
            'filename' => 'image1.jpg',
        ]);
        $file2 = StoredFile::factory()->create([
            'mime'     => StoredFile::MIME_PNG,
            'filename' => 'image2.png',
        ]);

        $artifact->storedFiles()->attach([$file1->id, $file2->id]);

        $artifacts = collect([$artifact]);

        $artifactsNeedingTranscode = $this->service->getArtifactsNeedingTranscode($artifacts);

        // Artifact should be returned once (even with multiple files needing transcode)
        $this->assertCount(1, $artifactsNeedingTranscode);
        $this->assertEquals($artifact->id, $artifactsNeedingTranscode->first()->id);
    }

    /**
     * Helper method to create an artifact with a stored file.
     */
    private function createArtifactWithFile(string $mime = StoredFile::MIME_JPEG, bool $hasTranscode = false): Artifact
    {
        $artifact = Artifact::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $extension = match ($mime) {
            StoredFile::MIME_JPEG => 'jpg',
            StoredFile::MIME_PNG  => 'png',
            StoredFile::MIME_GIF  => 'gif',
            StoredFile::MIME_PDF  => 'pdf',
            StoredFile::MIME_TEXT => 'txt',
            StoredFile::MIME_JSON => 'json',
            StoredFile::MIME_TIFF => 'tiff',
            StoredFile::MIME_WEBP => 'webp',
            StoredFile::MIME_HEIC => 'heic',
            default               => 'bin',
        };

        $storedFile = StoredFile::factory()->create([
            'mime'     => $mime,
            'filename' => 'test-file.' . $extension,
        ]);

        $artifact->storedFiles()->attach($storedFile->id);

        if ($hasTranscode) {
            // Create an LLM transcode child file
            StoredFile::factory()->create([
                'original_stored_file_id' => $storedFile->id,
                'transcode_name'          => ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM,
                'mime'                    => StoredFile::MIME_TEXT,
                'filename'                => $storedFile->filename . '.image-to-text-transcode.txt',
            ]);
        }

        // Refresh the artifact to load the stored files relationship
        return $artifact->fresh(['storedFiles']);
    }

    /**
     * Custom assertion helper for string contains check.
     */
    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            $message ?: "Failed asserting that '$haystack' contains '$needle'."
        );
    }
}
