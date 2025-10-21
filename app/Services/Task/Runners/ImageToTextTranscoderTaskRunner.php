<?php

namespace App\Services\Task\Runners;

use App\Api\ImageToText\ImageToTextOcrApi;
use App\Models\Task\Artifact;
use App\Services\Task\TaskAgentThreadBuilderService;
use App\Services\Usage\UsageTrackingService;
use Aws\S3\Exception\S3Exception;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;
use Throwable;

class ImageToTextTranscoderTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Image To Text Transcoder';

    public function run(): void
    {
        $this->activity("Running {$this->taskProcess->name}", 1);

        $inputArtifact   = $this->taskProcess->inputArtifacts->first();
        $fileToTranscode = $this->getFileToTranscode();

        $transcodedFile = $fileToTranscode->transcodes()->where('transcode_name', static::RUNNER_NAME)->first();

        // If the file is already transcoded, just return the completed transcode immediately
        if ($transcodedFile) {
            $this->activity("File already transcoded", 100);
            static::log("$transcodedFile");

            try {
                $transcodedContents = $transcodedFile->getContents();
            } catch(Throwable $e) {
                // If the transcoded file doesn't exist in S3 (404 error), delete the bad record and continue processing
                if ($this->is404Error($e)) {
                    $this->activity("Transcoded file not found in S3, cleaning up bad transcode record: {$transcodedFile->filename}", 5);
                    $transcodedFile->delete();
                    // Continue processing as if the file wasn't transcoded
                } else {
                    throw $e;
                }
            }

            if (isset($transcodedContents)) {
                $artifact = Artifact::create([
                    'name'            => $transcodedFile->filename,
                    'task_process_id' => $this->taskProcess->id,
                    'text_content'    => $transcodedContents,
                    'json_content'    => $inputArtifact->json_content,
                    'meta'            => $inputArtifact->meta,
                ]);
                $artifact->storedFiles()->attach($transcodedFile->originalFile);
                $this->complete([$artifact]);

                return;
            }
        }

        $ocrTranscode = $this->getOcrTranscode($fileToTranscode);
        $agentThread  = $this->setupThreadForFile($fileToTranscode, $ocrTranscode);

        $agent             = $agentThread->agent;
        $schemaAssociation = $this->taskProcess->outputSchemaAssociation;

        $this->activity("Using agent to transcode $agent->name", 10);
        $artifact = $this->runAgentThreadWithSchema($agentThread, $schemaAssociation?->schemaDefinition, $schemaAssociation?->schemaFragment);

        // If we didn't receive an artifact from the agent, record the incomplete state for retry
        if (!$artifact) {
            $this->taskProcess->incomplete_at = now();
            $this->taskProcess->save();
            $this->activity("No response from $agent->name", 100);

            return;
        }

        $this->activity("Storing transcoded data for $fileToTranscode->filename", 100);

        $transcodedFilename = preg_replace("/\\.[a-z0-9]+/", ".image-to-text-transcode.txt", $fileToTranscode->filename);
        // Save the transcoded record
        $transcodedFile = app(TranscodeFileService::class)->storeTranscodedFile(
            $fileToTranscode,
            static::RUNNER_NAME,
            $transcodedFilename,
            $artifact->text_content
        );

        // Preserve metadata from the input artifact and add to the current artifact meta
        $artifact->meta = ArrayHelper::mergeArraysRecursivelyUnique($artifact->meta ?? [], $inputArtifact->meta ?? []);

        // The artifact name should be the name of the transcoded file
        $artifact->name = $transcodedFile->filename;
        $artifact->storedFiles()->attach($transcodedFile->originalFile);
        $artifact->save();

        $this->complete([$artifact]);
    }

    public function getFileToTranscode(): StoredFile
    {
        $filesToTranscode = [];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            foreach($inputArtifact->storedFiles as $storedFile) {
                $filesToTranscode[] = $storedFile;
            }
        }

        if (count($filesToTranscode) > 1) {
            throw new ValidationError("Only one file can be transcoded at a time. Set Artifact Split Mode = 'Artifact' to transcode one artifact per process.");
        }

        if (empty($filesToTranscode)) {
            throw new ValidationError("No files found to transcode");
        }

        return $filesToTranscode[0];
    }

    /**
     * Setup the agent thread for the given file to prepare the LLM agent to transcode the file to text
     * Provides the OCR transcode as a reference for the agent to use
     */
    public function setupThreadForFile(StoredFile $storedFile, StoredFile $ocrTranscodedFile)
    {
        $agent = $this->taskDefinition->agent;

        if (!$agent) {
            throw new Exception(static::class . ": Agent not found for TaskProcess: $this->taskProcess");
        }

        $this->activity("Setup agent thread with Stored File $storedFile->id" . ($storedFile->page_number ? " (page: $storedFile->page_number)" : ''), 15);

        // Add the OCR transcode text to the thread
        $ocrPrompt = "OCR Transcoded version of the file (use as reference with the image of the file to get the best transcode possible): ";

        try {
            $ocrContents = $ocrTranscodedFile->getContents();
        } catch(Throwable $e) {
            // If the OCR file doesn't exist in S3 (404 error), delete the bad record and re-throw
            if ($this->is404Error($e)) {
                $this->activity("OCR file not found in S3, cleaning up bad transcode record: {$ocrTranscodedFile->filename}", 5);
                $ocrTranscodedFile->delete();
                throw new Exception("OCR transcode file not found in S3. Bad record cleaned up. Please retry the task.", 0, $e);
            }
            throw $e;
        }

        $agentThread = TaskAgentThreadBuilderService::fromTaskDefinition($this->taskDefinition, $this->taskRun)
            ->withMessage($ocrPrompt . $ocrContents)
            ->withMessage(['files' => [$storedFile]])
            ->build();

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        return $agentThread;
    }

    /**
     * Get the OCR transcode for the given file using the ImageToText OCR API
     */
    public function getOcrTranscode(StoredFile $storedFile)
    {
        $ocrTranscode = $storedFile->transcodes()->where('transcode_name', 'OCR')->first();

        if (!$ocrTranscode) {
            $this->activity("Transcoding $storedFile->filename to text", 10);

            $startTime = microtime(true);
            $ocrText   = app(ImageToTextOcrApi::class)->convert($storedFile->url);
            $endTime   = microtime(true);
            $runTimeMs = intval(($endTime - $startTime) * 1000);

            // Record usage for OCR API call
            app(UsageTrackingService::class)->recordApiUsage(
                $this->taskProcess,
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

            $transcodedFilename = preg_replace("/\\.[a-z0-9]+/", ".ocr.txt", $storedFile->filename);
            // Save the transcoded record
            $ocrTranscode = app(TranscodeFileService::class)->storeTranscodedFile(
                $storedFile,
                'OCR',
                $transcodedFilename,
                $ocrText,
                $storedFile->page_number
            );
        }

        return $ocrTranscode;
    }

    /**
     * Check if the exception (or any in its chain) is a 404 Not Found error
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
