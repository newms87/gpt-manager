<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\ArtifactFilterService;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;

class ImageToTextTranscoderTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Image To Text Transcoder';

    public function run(): void
    {
        $this->activity("Running {$this->taskProcess->name}", 1);

        $fileToTranscode = $this->getFileToTranscode();

        $transcodedFile = $fileToTranscode->transcodes()->where('transcode_name', static::RUNNER_NAME)->first();

        // If the file is already transcoded, just return the completed transcode immediately
        if ($transcodedFile) {
            $this->activity("File already transcoded", 100);
            $artifact = Artifact::create([
                'name'            => $transcodedFile->filename,
                'task_process_id' => $this->taskProcess->id,
                'text_content'    => $transcodedFile->getContents(),
            ]);
            $artifact->storedFiles()->attach($transcodedFile->originalFile);
            $this->complete([$artifact]);

            return;
        }

        $agentThread = $this->setupThreadForFile($fileToTranscode);

        $agent             = $agentThread->agent;
        $schemaAssociation = $this->taskProcess->taskDefinitionAgent->outputSchemaAssociation;

        $this->activity("Using agent to transcode $agent->name", 10);
        $artifact = $this->runAgentThreadWithSchema($agentThread, $schemaAssociation?->schemaDefinition, $schemaAssociation?->schemaFragment);

        // If we didn't receive an artifact from the agent, record the failure
        if (!$artifact) {
            $this->taskProcess->failed_at = now();
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

    public function setupThreadForFile(StoredFile $file)
    {
        $definitionAgent = $this->taskProcess->taskDefinitionAgent;
        $definition      = $definitionAgent?->taskDefinition;
        $agent           = $definitionAgent?->agent;

        if (!$agent) {
            throw new Exception(static::class . ": Agent not found for TaskProcess: $this->taskProcess");
        }

        $agentThread = app(ThreadRepository::class)->create($agent, "$definition->name: $agent->name");

        $this->activity("Setup agent thread with Stored File $file->id" . ($file->page_number ? " (page: $file->page_number)" : ''), 5);

        $artifactFilter = (new ArtifactFilterService())
            ->includeText($definitionAgent->include_text)
            ->includeJson($definitionAgent->include_data, $definitionAgent->getInputFragmentSelector());

        foreach($this->taskProcess->inputArtifacts as $index => $inputArtifact) {
            $artifact = $artifactFilter->setArtifact($inputArtifact)->filter();

            // Only set file on the first artifact (only process one time)
            if ($index === 0) {
                $artifact['files'] = [$file];
            }

            app(ThreadRepository::class)->addMessageToThread($agentThread, $artifact);
        }

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        return $agentThread;
    }
}
