<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Workflow\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadMessageToArtifactMapper;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\JsonSchema\JsonSchemaService;
use Exception;

class AgentThreadTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'AI Agent';

    protected bool $includePageNumbersInThread = false;

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
        $agentThread       = $this->setupAgentThread();
        $agent             = $agentThread->agent;
        $schemaAssociation = $this->taskProcess->taskDefinitionAgent->outputSchemaAssociation;

        // The default agent thread task runner will use the JsonSchemaService with citations, id, and the name so we are enabling database I/O w/ citations
        $jsonSchemaService = app(JsonSchemaService::class);

        if ($schemaAssociation?->schemaDefinition) {
            $jsonSchemaService->useCitations()->useDbFields();
        }

        $this->activity("Communicating with AI agent in thread", 11);
        $artifact = $this->runAgentThreadWithSchema($agentThread, $schemaAssociation?->schemaDefinition, $schemaAssociation?->schemaFragment, $jsonSchemaService);

        if ($artifact) {
            $this->activity("Received response from $agent->name", 100);
            $this->complete([$artifact]);
        } else {
            $this->taskProcess->failed_at = now();
            $this->taskProcess->save();
            $this->activity("No response from $agent->name", 100);
        }
    }

    /**
     * Setup the agent thread with the input artifacts.
     * Associate the thread to the TaskProcess so it has everything it needs to run in an independent job
     */
    public function setupAgentThread(): AgentThread
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

        $this->activity("Setting up agent thread for: $agent->name", 5);

        $threadName  = $definition->name . ': ' . $agent->name;
        $agentThread = app(ThreadRepository::class)->create($agent, $threadName);

        $inputArtifacts = $this->taskProcess->inputArtifacts()->get();

        static::log("\tAdding " . count($inputArtifacts) . " input artifacts for " . $definitionAgent);
        $artifactFilter = (new ArtifactFilter())
            ->includePageNumbers($this->includePageNumbersInThread)
            ->includeText($definitionAgent->include_text)
            ->includeFiles($definitionAgent->include_files)
            ->includeJson($definitionAgent->include_data, $definitionAgent->getInputFragmentSelector());

        foreach($inputArtifacts as $inputArtifact) {
            $artifactFilter->setArtifact($inputArtifact);
            app(ThreadRepository::class)->addMessageToThread($agentThread, $artifactFilter->filter());
        }

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        return $agentThread;
    }

    /**
     * Run the agent thread and return the last message as an artifact
     */
    public function runAgentThreadWithSchema(AgentThread $agentThread, SchemaDefinition $schemaDefinition = null, SchemaFragment $schemaFragment = null, JsonSchemaService $jsonSchemaService = null): Artifact|null
    {
        // Run the thread synchronously (ie: dispatch = false)
        $threadRun = (new AgentThreadService)
            ->withResponseFormat($schemaDefinition, $schemaFragment, $jsonSchemaService)
            ->run($agentThread, dispatch: false);

        // Create the artifact and associate it with the task process
        if ($threadRun->lastMessage) {
            return (new AgentThreadMessageToArtifactMapper)->setMessage($threadRun->lastMessage)->map();
        }

        return null;
    }
}
