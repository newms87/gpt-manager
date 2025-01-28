<?php

namespace App\Services\Task\Runners;

use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadMessageToArtifactMapper;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use Exception;
use Illuminate\Support\Facades\Log;

class AgentThreadTaskRunner extends TaskRunnerBase
{
    public function run(): void
    {
        Log::debug("AgentThreadTaskRunner Running: $this->taskProcess");

        $thread = $this->setupAgentThread();

        // Run the thread synchronously (ie: dispatch = false)
        $taskDefinitionAgent = $this->taskProcess->taskDefinitionAgent;
        $threadRun           = (new AgentThreadService)
            ->withResponseFormat($taskDefinitionAgent->outputSchema, $taskDefinitionAgent->outputSchemaFragment)
            ->run($thread, dispatch: false);

        // Create the artifact and associate it with the task process
        if ($threadRun->lastMessage) {
            $artifact = (new AgentThreadMessageToArtifactMapper)->setMessage($threadRun->lastMessage)->map();
            $this->complete($artifact);
        }
    }

    /**
     * Setup the agent thread with the input artifacts.
     * Associate the thread to the TaskProcess so it has everything it needs to run in an independent job
     */
    public function setupAgentThread()
    {
        $definitionAgent = $this->taskProcess->taskDefinitionAgent;
        $definition      = $definitionAgent?->taskDefinition;
        $agent           = $definitionAgent?->agent;

        if (!$agent) {
            throw new Exception("AgentThreadTaskRunner: Agent not found for TaskProcess: $this->taskProcess");
        }

        $inputArtifacts = $this->taskProcess->inputArtifacts()->get();

        $threadName = $definition->name . ': ' . $agent->name;
        $thread     = app(ThreadRepository::class)->create($agent, $threadName);

        Log::debug("Setup Task AgentThread: $thread");

        Log::debug("\tAdding " . count($inputArtifacts) . " input artifacts");
        $artifactFilter = (new ArtifactFilter())
            ->includeText($definitionAgent->include_text)
            ->includeFiles($definitionAgent->include_files)
            ->includeJson($definitionAgent->include_data, $definitionAgent->inputSchemaFragment?->fragment_selector ?? []);

        foreach($inputArtifacts as $inputArtifact) {
            $artifactFilter->setArtifact($inputArtifact);
            app(ThreadRepository::class)->addMessageToThread($thread, $artifactFilter->filter());
        }

        $this->taskProcess->agentThread()->associate($thread)->save();

        return $thread;
    }
}
