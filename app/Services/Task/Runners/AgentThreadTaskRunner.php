<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadMessageToArtifactMapper;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilterService;
use App\Services\JsonSchema\JsonSchemaService;
use Exception;
use Newms87\Danx\Helpers\StringHelper;

class AgentThreadTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'AI Agent';

    protected bool $includePageNumbersInThread = false;

    public function prepareProcess(): void
    {
        $defAgent = $this->taskProcess->taskDefinitionAgent;

        if ($defAgent) {
            $agent = $defAgent->agent;
            $name  = "$agent->name ($agent->model)";

            $outputSchema         = $defAgent->outputSchemaAssociation?->schemaDefinition;
            $outputSchemaFragment = $defAgent->outputSchemaAssociation?->schemaFragment;
            if ($outputSchema) {
                $name = StringHelper::limitText(100, $name, ': ' . $outputSchema->name . ($outputSchemaFragment ? ' [' . $outputSchemaFragment->name . ']' : ''));
            }

            $this->taskProcess->name = $name;
        }

        $this->activity("Preparing agent thread", 1);
    }

    public function run(): void
    {
        $agentThread       = $this->setupAgentThread();
        $agent             = $agentThread->agent;
        $schemaAssociation = $this->taskProcess->taskDefinitionAgent->outputSchemaAssociation;

        // The default agent thread task runner will use the JsonSchemaService with the database fields (ie: id and name) so we are enabling database I/O
        $jsonSchemaService = app(JsonSchemaService::class)->useArtifactMeta();

        $this->activity("Communicating with AI agent in thread", 11);
        $artifact = $this->runAgentThreadWithSchema($agentThread, $schemaAssociation?->schemaDefinition, $schemaAssociation?->schemaFragment, $jsonSchemaService);

        if ($artifact) {
            $schemaType = $schemaAssociation?->schemaDefinition?->schema['title'] ?? null;

            if ($schemaType) {
                $this->hydrateArtifactJsonContentIds($artifact, $schemaType);
            }

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
        $artifactFilter = (new ArtifactFilterService())
            ->includePageNumbers($this->includePageNumbersInThread)
            ->includeText($definitionAgent->include_text)
            ->includeFiles($definitionAgent->include_files)
            ->includeJson($definitionAgent->include_data, $definitionAgent->getInputFragmentSelector());

        foreach($inputArtifacts as $inputArtifact) {
            $artifactFilter->setArtifact($inputArtifact);
            $filteredMessage = $artifactFilter->filter();
            if ($filteredMessage) {
                app(ThreadRepository::class)->addMessageToThread($agentThread, $filteredMessage);
            }
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
            ->run($agentThread);

        // Create the artifact and associate it with the task process
        if ($threadRun->lastMessage) {
            $artifact = (new AgentThreadMessageToArtifactMapper)->setThreadRun($threadRun)->setMessage($threadRun->lastMessage)->map();

            if ($artifact && $schemaDefinition) {
                $artifact->schemaDefinition()->associate($schemaDefinition)->save();
            }

            return $artifact;
        }

        return null;
    }

    /**
     * Hydrate the artifact's json content with the ids from the reference json content.
     */
    protected function hydrateArtifactJsonContentIds(Artifact $artifact, string $schemaType): void
    {
        if ($artifact->json_content) {
            static::log("Hydrate artifact json content with reference data for $schemaType: $artifact");

            $referenceArtifact = null;
            foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
                $jsonContentType = $inputArtifact->json_content['type'] ?? null;
                if ($inputArtifact->json_content && $jsonContentType === $schemaType) {
                    $referenceArtifact = $inputArtifact;
                    break;
                }
            }

            static::log("Resolved reference artifact for $schemaType: $referenceArtifact");

            if ($referenceArtifact) {
                $artifact->json_content = app(JsonSchemaService::class)->hydrateIdsInJsonContent($artifact->json_content, $referenceArtifact->json_content);
                $artifact->save();
                static::log("Hydration completed");
            }
        }
    }
}
