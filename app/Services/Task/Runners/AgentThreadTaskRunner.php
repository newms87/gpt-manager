<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\Artifact;
use App\Services\AgentThread\AgentThreadMessageToArtifactMapper;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\TaskDefinitionToAgentThreadMapper;
use App\Services\JsonSchema\JsonSchemaService;
use Exception;
use Newms87\Danx\Helpers\StringHelper;

class AgentThreadTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'AI Agent';

    protected bool $includePageNumbersInThread = false;

    public function prepareProcess(): void
    {
        // Fragment and agent are used for naming the process
        $fragment = $this->taskProcess->outputSchemaAssociation?->schemaFragment;
        $agent    = $this->taskDefinition->agent;

        if (!$agent) {
            throw new Exception(static::class . ": Agent not found for TaskRun: $this->taskRun");
        }

        // Process named based on agent
        $name = "$agent->name ($agent->model)";

        // Add the fragment to the name if it is set
        if ($fragment) {
            $name = StringHelper::limitText(100, $name, ': ' . $fragment->name);
        }

        $this->taskProcess->name = $name;

        $this->activity("Preparing agent thread", 1);
    }

    public function run(): void
    {
        $agentThread = $this->setupAgentThread();
        $artifact    = $this->runAgentThread($agentThread);
        $this->complete([$artifact]);
    }

    public function runAgentThread(AgentThread $agentThread)
    {
        $agent             = $agentThread->agent;
        $schemaDefinition  = $this->taskDefinition->schemaDefinition;
        $schemaAssociation = $this->taskProcess->outputSchemaAssociation;

        if (($schemaDefinition && $schemaAssociation) && $schemaDefinition->id !== $schemaAssociation->schema_definition_id) {
            throw new Exception(static::class . ": Schema definition mismatch: $schemaDefinition->id !== $schemaAssociation->schema_definition_id: for TaskProcess: $this->taskProcess");
        }

        // The default agent thread task runner will use the JsonSchemaService with the database fields (ie: id and name) so we are enabling database I/O
        $jsonSchemaService = app(JsonSchemaService::class)->useArtifactMeta()->includeNullValues();

        $this->activity("Communicating with AI agent in thread", 11);
        $artifact = $this->runAgentThreadWithSchema($agentThread, $schemaDefinition, $schemaAssociation?->schemaFragment, $jsonSchemaService);

        if ($artifact) {
            $schemaType = $schemaDefinition?->schema['title'] ?? null;

            if ($schemaType) {
                $this->hydrateArtifactJsonContentIds($artifact, $schemaType);
            }

            // If the task definition is set to include text sources, we will add the text sources to the artifact
            // By default, this is set to true for all task definitions w/ JSON responses, as they are
            // assumed to be collecting data from text sources in this case
            if ($this->config('include_text_sources', $this->taskDefinition->isJsonResponse())) {
                $this->appendTextSources($artifact);
            }

            $this->activity("Received response from $agent->name", 100);
        } else {
            $this->taskProcess->failed_at = now();
            $this->taskProcess->save();
            $this->activity("No response from $agent->name", 100);
        }

        return $artifact;
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

        $taskDefinition = $this->taskRun->taskDefinition;
        $this->activity("Setting up agent thread for: {$taskDefinition->agent->name}", 5);

        $agentThread = app(TaskDefinitionToAgentThreadMapper::class)
            ->setTaskRun($this->taskRun)
            ->setTaskDefinition($this->taskRun->taskDefinition)
            ->setArtifacts($this->taskProcess->inputArtifacts()->get())
            ->includePageNumbers($this->includePageNumbersInThread)
            ->map();

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

    /**
     * Append the text sources to the artifact's text content.
     */
    protected function appendTextSources(Artifact $artifact): void
    {
        static::log("Appending text sources to artifact: $artifact");

        $sourceTextContent = '';
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $text = trim($inputArtifact->text_content);
            if ($text) {
                $sourceTextContent .= "\n\n-----\n\n" . $text;
            }
        }

        if ($sourceTextContent) {
            $artifact->text_content = ($artifact->text_content ?? "") . "\n\n-----\n\n# Sources:";
        }
    }
}
