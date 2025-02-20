<?php

namespace App\Services\Task\Runners;

use App\Models\Workflow\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadMessageToArtifactMapper;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;

class ImageToTextTranscoderTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Image To Text Transcoder';

    public function getFileToTranscode(): StoredFile
    {
        $filesToTranscode = [];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            foreach($inputArtifact->storedFiles as $storedFile) {
                $filesToTranscode[] = $storedFile;
            }
        }

        if (count($filesToTranscode) > 1) {
            throw new ValidationError("Only one file can be transcoded at a time. Use the 'Split by file' option in the grouping settings.");
        }

        if (empty($filesToTranscode)) {
            throw new ValidationError("No files found to transcode");
        }

        return $filesToTranscode[0];
    }

    public function run(): void
    {
        static::log("Running $this->taskProcess");

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
            $artifact->storedFiles()->attach($transcodedFile);
            $this->complete([$artifact]);

            return;
        }

        $thread = $this->setupThreadForFile($fileToTranscode);
        $this->taskProcess->agentThread()->associate($thread)->save();

        $agent = $thread->agent;

        $this->activity("Using agent to transcode $agent->name", 10);

        // Run the thread synchronously (ie: dispatch = false)
        $taskDefinitionAgent = $this->taskProcess->taskDefinitionAgent;
        $threadRun           = (new AgentThreadService)
            ->withResponseFormat($taskDefinitionAgent->outputSchemaAssociation?->schemaDefinition, $taskDefinitionAgent->outputSchemaAssociation?->schemaFragment)
            ->run($thread, dispatch: false);

        // Create the artifact and associate it with the task process
        if ($threadRun->lastMessage) {
            $this->activity("Storing transcoded data for $fileToTranscode->filename", 100);
            $artifact = (new AgentThreadMessageToArtifactMapper)->setMessage($threadRun->lastMessage)->map();

            // Save the transcoded record
            $transcodedFile = app(TranscodeFileService::class)->storeTranscodedFile(
                $fileToTranscode,
                static::RUNNER_NAME,
                'image-to-text-transcode-' . uniqid() . '.txt',
                $artifact->text_content
            );

            $artifact->storedFiles()->attach($transcodedFile);
            $artifact->save();

            $this->complete([$artifact]);
        } else {
            $this->taskProcess->failed_at = now();
            $this->activity("No response from $agent->name", 100);
        }
    }

    public function setupThreadForFile(StoredFile $file)
    {
        $definitionAgent = $this->taskProcess->taskDefinitionAgent;
        $definition      = $definitionAgent?->taskDefinition;
        $agent           = $definitionAgent?->agent;

        if (!$agent) {
            throw new Exception(static::class . ": Agent not found for TaskProcess: $this->taskProcess");
        }

        $thread = app(ThreadRepository::class)->create($agent, "$definition->name: $agent->name");

        static::log("Setup agent thread: $thread with $file");

        $artifactFilter = (new ArtifactFilter())
            ->includeText($definitionAgent->include_text)
            ->includeJson($definitionAgent->include_data, $definitionAgent->getInputFragmentSelector());

        foreach($this->taskProcess->inputArtifacts as $index => $inputArtifact) {
            $artifact = $artifactFilter->setArtifact($inputArtifact)->filter();

            // Only set file on the first artifact (only process one time)
            if ($index === 0) {
                $artifact['files'] = [$file];
            }

            app(ThreadRepository::class)->addMessageToThread($thread, $artifact);
        }


        return $thread;
    }
}
