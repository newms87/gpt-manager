<?php

namespace App\Services\Task;

use App\Api\ImageToText\ImageToTextOcrApi;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ImageToTextTranscoderTaskRunner;
use App\Services\Usage\UsageTrackingService;
use App\Traits\HasDebugLogging;
use Aws\S3\Exception\S3Exception;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;
use Throwable;

/**
 * Service to ensure files have text transcodes before processing.
 *
 * Any task runner can use this service to:
 * 1. Identify files needing transcoding from artifacts
 * 2. Create parallel transcode processes
 * 3. Execute transcoding (OCR + LLM) for individual files
 * 4. Check if all transcoding is complete
 */
class TranscodePrerequisiteService
{
    use HasDebugLogging;

    public const string OPERATION_TRANSCODE = 'Transcode';

    /**
     * MIME types that can be transcoded to text
     */
    protected const array TRANSCODABLE_MIMES = [
        StoredFile::MIME_PDF,
        StoredFile::MIME_PNG,
        StoredFile::MIME_JPEG,
        StoredFile::MIME_GIF,
        StoredFile::MIME_TIFF,
        StoredFile::MIME_WEBP,
        StoredFile::MIME_HEIC,
    ];

    /**
     * Get artifacts that need transcoding.
     * Returns Artifacts whose stored files don't have an LLM text transcode.
     */
    public function getArtifactsNeedingTranscode(Collection $artifacts): Collection
    {
        $artifactsNeedingTranscode = collect();

        foreach ($artifacts as $artifact) {
            foreach ($artifact->storedFiles as $storedFile) {
                if ($this->needsTranscode($storedFile)) {
                    $artifactsNeedingTranscode->push($artifact);
                    break; // Only need to add artifact once
                }
            }
        }

        static::logDebug('Artifacts needing transcode', [
            'total_artifacts' => $artifacts->count(),
            'artifacts_needing' => $artifactsNeedingTranscode->count(),
        ]);

        return $artifactsNeedingTranscode;
    }

    /**
     * Create transcode processes for the given artifacts.
     * One TaskProcess per artifact for parallel execution.
     *
     * @return array<TaskProcess> Array of created TaskProcess instances
     */
    public function createTranscodeProcesses(TaskRun $taskRun, Collection $artifacts): array
    {
        $processes = [];

        foreach ($artifacts as $artifact) {
            $process = $taskRun->taskProcesses()->create([
                'name'      => 'Transcode: ' . $artifact->name,
                'operation' => self::OPERATION_TRANSCODE,
                'activity'  => 'Transcoding ' . $artifact->name,
                'meta'      => [],
                'is_ready'  => true,
            ]);

            // Attach artifact as input
            $process->inputArtifacts()->attach($artifact->id);
            $process->updateRelationCounter('inputArtifacts');

            $processes[] = $process;

            static::logDebug('Created transcode process', [
                'task_process_id' => $process->id,
                'artifact_id'     => $artifact->id,
                'artifact_name'   => $artifact->name,
            ]);
        }

        return $processes;
    }

    /**
     * Check if all transcode processes for this task run are complete.
     */
    public function isTranscodingComplete(TaskRun $taskRun): bool
    {
        $transcodeProcesses = $taskRun->taskProcesses()
            ->where('operation', self::OPERATION_TRANSCODE)
            ->get();

        if ($transcodeProcesses->isEmpty()) {
            return true;
        }

        $incompleteCount = $transcodeProcesses
            ->whereNull('completed_at')
            ->count();

        static::logDebug('Checking transcode completion', [
            'total_processes'      => $transcodeProcesses->count(),
            'incomplete_processes' => $incompleteCount,
        ]);

        return $incompleteCount === 0;
    }

    /**
     * Execute transcoding for an artifact.
     * Performs OCR first, then LLM transcode with OCR + image.
     */
    public function transcodeArtifact(TaskProcess $taskProcess, Artifact $artifact): void
    {
        $storedFile = $artifact->storedFiles()->first();

        if (!$storedFile) {
            throw new Exception("Artifact {$artifact->id} has no stored files");
        }

        static::logDebug('Starting transcode for artifact', [
            'task_process_id' => $taskProcess->id,
            'artifact_id'     => $artifact->id,
            'stored_file_id'  => $storedFile->id,
            'filename'        => $storedFile->filename,
        ]);

        // Check if LLM transcode already exists - skip if yes
        $existingTranscode = $storedFile->transcodes()
            ->where('transcode_name', ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM)
            ->first();

        if ($existingTranscode) {
            // Verify the transcode file actually exists in storage
            try {
                $existingTranscode->getContents();
                static::logDebug('LLM transcode already exists, skipping', [
                    'stored_file_id'    => $storedFile->id,
                    'transcode_file_id' => $existingTranscode->id,
                ]);

                return;
            } catch (Throwable $e) {
                // If file doesn't exist in S3, delete the bad record and continue
                if ($this->is404Error($e)) {
                    static::logDebug('Transcode record exists but file missing in S3, cleaning up', [
                        'stored_file_id'    => $storedFile->id,
                        'transcode_file_id' => $existingTranscode->id,
                    ]);
                    $existingTranscode->delete();
                } else {
                    throw $e;
                }
            }
        }

        // Get or create OCR transcode
        $ocrTranscode = $this->getOrCreateOcrTranscode($taskProcess, $storedFile);

        // Create agent thread with OCR text + image
        $agentThread = $this->setupThreadForFile($taskProcess, $storedFile, $ocrTranscode);

        // Run agent to get LLM transcode
        $runner            = $taskProcess->getRunner();
        $schemaAssociation = $taskProcess->outputSchemaAssociation;

        $artifact = $runner->runAgentThreadWithSchema(
            $agentThread,
            $schemaAssociation?->schemaDefinition,
            $schemaAssociation?->schemaFragment
        );

        if (!$artifact) {
            static::logDebug('No response from agent for transcode', [
                'task_process_id' => $taskProcess->id,
                'stored_file_id'  => $storedFile->id,
            ]);

            return;
        }

        // Store the transcoded text
        $transcodedFilename = preg_replace('/\\.[a-z0-9]+/', '.image-to-text-transcode.txt', $storedFile->filename);
        app(TranscodeFileService::class)->storeTranscodedFile(
            $storedFile,
            ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM,
            $transcodedFilename,
            $artifact->text_content
        );

        static::logDebug('Stored LLM transcode', [
            'stored_file_id' => $storedFile->id,
            'filename'       => $transcodedFilename,
        ]);
    }

    /**
     * Check if a stored file needs transcoding.
     * Returns true if file is transcodable (image/PDF) and has no LLM text transcode.
     */
    protected function needsTranscode(StoredFile $storedFile): bool
    {
        // Check mime type is transcodable
        if (!in_array($storedFile->mime, self::TRANSCODABLE_MIMES)) {
            return false;
        }

        // Check if LLM transcode already exists
        $hasLlmTranscode = $storedFile->transcodes()
            ->where('transcode_name', ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_LLM)
            ->exists();

        return !$hasLlmTranscode;
    }

    /**
     * Get or create OCR transcode for a stored file.
     */
    protected function getOrCreateOcrTranscode(TaskProcess $taskProcess, StoredFile $storedFile): StoredFile
    {
        $ocrTranscode = $storedFile->transcodes()
            ->where('transcode_name', ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR)
            ->first();

        if ($ocrTranscode) {
            // Verify the file exists
            try {
                $ocrTranscode->getContents();

                return $ocrTranscode;
            } catch (Throwable $e) {
                if ($this->is404Error($e)) {
                    static::logDebug('OCR transcode record exists but file missing in S3, recreating', [
                        'stored_file_id'    => $storedFile->id,
                        'transcode_file_id' => $ocrTranscode->id,
                    ]);
                    $ocrTranscode->delete();
                } else {
                    throw $e;
                }
            }
        }

        // Create new OCR transcode
        static::logDebug('Creating OCR transcode', [
            'stored_file_id' => $storedFile->id,
            'filename'       => $storedFile->filename,
        ]);

        $startTime = microtime(true);
        $ocrText   = app(ImageToTextOcrApi::class)->convert($storedFile->url);
        $endTime   = microtime(true);
        $runTimeMs = intval(($endTime - $startTime) * 1000);

        // Record usage for OCR API call
        app(UsageTrackingService::class)->recordApiUsage(
            $taskProcess,
            ImageToTextOcrApi::class,
            'ocr_conversion',
            [
                'data_volume' => strlen($ocrText),
                'metadata'    => [
                    'filename'  => $storedFile->filename,
                    'file_size' => $storedFile->size,
                    'url'       => $storedFile->url,
                ],
            ],
            $runTimeMs
        );

        $transcodedFilename = preg_replace('/\\.[a-z0-9]+/', '.ocr.txt', $storedFile->filename);

        return app(TranscodeFileService::class)->storeTranscodedFile(
            $storedFile,
            ImageToTextTranscoderTaskRunner::TRANSCODE_NAME_OCR,
            $transcodedFilename,
            $ocrText,
            $storedFile->page_number
        );
    }

    /**
     * Setup the agent thread for the given file to prepare the LLM agent to transcode the file to text.
     * Provides the OCR transcode as a reference for the agent to use.
     */
    protected function setupThreadForFile(TaskProcess $taskProcess, StoredFile $storedFile, StoredFile $ocrTranscodedFile)
    {
        $taskDefinition = $taskProcess->taskRun->taskDefinition;
        $agent          = $taskDefinition->agent;

        if (!$agent) {
            throw new Exception(static::class . ": Agent not found for TaskProcess: $taskProcess");
        }

        static::logDebug('Setting up agent thread for transcode', [
            'stored_file_id' => $storedFile->id,
            'page_number'    => $storedFile->page_number,
        ]);

        // Add the OCR transcode text to the thread
        $ocrPrompt = 'OCR Transcoded version of the file (use as reference with the image of the file to get the best transcode possible): ';

        try {
            $ocrContents = $ocrTranscodedFile->getContents();
        } catch (Throwable $e) {
            if ($this->is404Error($e)) {
                $ocrTranscodedFile->delete();
                throw new Exception('OCR transcode file not found in S3. Bad record cleaned up. Please retry the task.', 0, $e);
            }
            throw $e;
        }

        $agentThread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskProcess->taskRun)
            ->withMessage($ocrPrompt . $ocrContents)
            ->withMessage(['files' => [$storedFile]])
            ->build();

        $taskProcess->agentThread()->associate($agentThread)->save();

        return $agentThread;
    }

    /**
     * Check if the exception (or any in its chain) is a 404 Not Found error.
     */
    protected function is404Error(Throwable $e): bool
    {
        // Check the current exception
        if ($e instanceof ClientException && $e->getCode() === 404) {
            return true;
        }

        if ($e instanceof S3Exception && $e->getStatusCode() === 404) {
            return true;
        }

        // Check if the exception message contains S3 NoSuchKey error
        if (str_contains($e->getMessage(), 'NoSuchKey')) {
            return true;
        }

        // Check previous exceptions in the chain
        if ($e->getPrevious()) {
            return $this->is404Error($e->getPrevious());
        }

        return false;
    }
}
