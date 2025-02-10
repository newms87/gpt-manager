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
    const string RUNNER_NAME = 'AI Agent';

    public function prepareProcess(): void
    {
        $defAgent = $this->taskProcess->taskDefinitionAgent;
        $agent    = $defAgent->agent;
        $name     = "$agent->name ($agent->model)";

        $outputSchema         = $defAgent->outputSchemaAssociation?->schemaDefinition;
        $outputSchemaFragment = $defAgent->outputSchemaAssociation?->schemaFragment;
        if ($outputSchema) {
            $name .= ': ' . $outputSchema->name . ($outputSchemaFragment ? ' [' . $outputSchemaFragment->name . ']' : '');
        }

        $this->taskProcess->name = $name;
        $this->activity("Preparing agent thread", 1);
    }

    public function run(): void
    {
        Log::debug("AgentThreadTaskRunner Running: $this->taskProcess");

        $thread = $this->setupAgentThread();
        $agent  = $thread->agent;

        $this->activity("Communicating with agent $agent->name", 10);

        // Run the thread synchronously (ie: dispatch = false)
        $taskDefinitionAgent = $this->taskProcess->taskDefinitionAgent;
        $threadRun           = (new AgentThreadService)
            ->withResponseFormat($taskDefinitionAgent->outputSchemaAssociation?->schemaDefinition, $taskDefinitionAgent->outputSchemaAssociation?->schemaFragment)
            ->run($thread, dispatch: false);

        // Create the artifact and associate it with the task process
        if ($threadRun->lastMessage) {
            $this->activity("Received response from $agent->name", 100);
            $artifact = (new AgentThreadMessageToArtifactMapper)->setMessage($threadRun->lastMessage)->map();
            $this->complete($artifact);
        } else {
            $this->taskProcess->failed_at = now();
            $this->activity("No response from $agent->name", 100);
        }
    }

    /**
     * Setup the agent thread with the input artifacts.
     * Associate the thread to the TaskProcess so it has everything it needs to run in an independent job
     */
    public function setupAgentThread()
    {
        if ($this->taskProcess->agentThread) {
            return $this->taskProcess->agentThread;
        }

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

        Log::debug("\tAdding " . count($inputArtifacts) . " input artifacts for " . $definitionAgent);
        $artifactFilter = (new ArtifactFilter())
            ->includeText($definitionAgent->include_text)
            ->includeFiles($definitionAgent->include_files)
            ->includeJson($definitionAgent->include_data, $definitionAgent->getInputFragmentSelector());

        foreach($inputArtifacts as $inputArtifact) {
            $artifactFilter->setArtifact($inputArtifact);
            app(ThreadRepository::class)->addMessageToThread($thread, $artifactFilter->filter());
        }

        $this->taskProcess->agentThread()->associate($thread)->save();

        return $thread;
    }
}
