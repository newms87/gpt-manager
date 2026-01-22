<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\ArtifactCategoryDefinition;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Repositories\ThreadRepository;
use App\Services\Task\TaskProcessDispatcherService;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Generates artifacts for TeamObjects based on ArtifactCategoryDefinitions.
 *
 * This runner:
 * - Receives an input TeamObject reference via the task run input artifact
 * - For each ArtifactCategoryDefinition in the schema, creates TaskProcesses per matching TeamObject
 * - Each process generates a text artifact using LLM and attaches it to the target TeamObject
 */
class SchemaDefinitionArtifactTaskRunner extends AgentThreadTaskRunner
{
    public const string RUNNER_NAME = 'Schema Definition Artifact';

    public const string OPERATION_GENERATE_ARTIFACT = 'Generate Artifact';

    public const string CONFIG_ARTIFACT_NAME = 'Artifact Generation Config';

    /**
     * Get the task runner name.
     */
    public static function name(): string
    {
        return self::RUNNER_NAME;
    }

    /**
     * Get the task runner slug.
     */
    public static function slug(): string
    {
        return 'schema-definition-artifact';
    }

    /**
     * Get the task runner description.
     */
    public static function description(): string
    {
        return 'Generates text artifacts for TeamObjects based on ArtifactCategoryDefinitions. Each category definition specifies a prompt and optional fragment selector to target specific related objects.';
    }

    /**
     * Prepare the task run.
     * Creates TaskProcesses for each (ArtifactCategoryDefinition, target TeamObject) pair.
     */
    public function prepareRun(): void
    {
        parent::prepareRun();

        static::logDebug('SchemaDefinitionArtifactTaskRunner - Preparing artifact generation task run');

        // Validate schema definition exists and is configured
        $schemaDefinitionId = $this->config('schema_definition_id');
        if (!$schemaDefinitionId) {
            throw new ValidationError(
                'SchemaDefinitionArtifactTaskRunner requires a schema_definition_id in task_runner_config.'
            );
        }

        $schemaDefinition = SchemaDefinition::with('artifactCategoryDefinitions')->find($schemaDefinitionId);
        if (!$schemaDefinition) {
            throw new ValidationError(
                "Schema Definition with ID {$schemaDefinitionId} not found."
            );
        }

        $artifactCategoryDefinitions = $schemaDefinition->artifactCategoryDefinitions;
        if ($artifactCategoryDefinitions->isEmpty()) {
            static::logDebug('No artifact category definitions found - nothing to generate');
            $this->taskRun->skipped_at = now();
            $this->taskRun->save();

            return;
        }

        // Get the root TeamObject from the task run's input artifact
        $rootTeamObject = $this->resolveRootTeamObject();
        if (!$rootTeamObject) {
            throw new ValidationError(
                'SchemaDefinitionArtifactTaskRunner requires an input artifact with team_object_id in json_content.'
            );
        }

        static::logDebug("Processing {$artifactCategoryDefinitions->count()} artifact category definitions for TeamObject: {$rootTeamObject->id}");

        // Create processes for each category definition and target TeamObject pair
        $this->createProcessesForCategoryDefinitions($schemaDefinition, $artifactCategoryDefinitions, $rootTeamObject);
    }

    /**
     * Route to appropriate handler based on operation type.
     */
    public function run(): void
    {
        static::logDebug("SchemaDefinitionArtifactTaskRunner - Running operation: {$this->taskProcess->operation}");

        match ($this->taskProcess->operation) {
            self::OPERATION_GENERATE_ARTIFACT => $this->runGenerateArtifactOperation(),
            default                           => $this->runInitializeOperation()
        };
    }

    /**
     * Run the initialize operation (first process created by prepareRun).
     * Simply completes since all work is done via spawned processes.
     */
    protected function runInitializeOperation(): void
    {
        static::logDebug('Running initialize operation');
        $this->complete();
    }

    /**
     * Run the generate artifact operation.
     * Gets config from input artifact, calls LLM, creates output artifact, and attaches to TeamObject.
     */
    protected function runGenerateArtifactOperation(): void
    {
        static::logDebug('Running generate artifact operation');

        // Get configuration from input artifact
        $config                     = $this->getConfigFromProcess();
        $teamObjectId               = $config['team_object_id']                  ?? null;
        $artifactCategoryDefId      = $config['artifact_category_definition_id'] ?? null;
        $prompt                     = $config['prompt']                          ?? '';
        $categoryName               = $config['category_name']                   ?? 'generated';
        $data                       = $config['data']                            ?? [];

        if (!$teamObjectId || !$artifactCategoryDefId) {
            throw new ValidationError('Missing team_object_id or artifact_category_definition_id in process config');
        }

        $teamObject = TeamObject::find($teamObjectId);
        if (!$teamObject) {
            throw new ValidationError("TeamObject with ID {$teamObjectId} not found");
        }

        static::logDebug("Generating artifact for TeamObject: {$teamObject->id}, category: {$categoryName}");

        // Build the LLM prompt with the TeamObject data
        $fullPrompt = $this->buildLlmPrompt($prompt, $data, $teamObject);

        // Setup and run agent thread
        $agentThread = $this->setupAgentThreadWithPrompt($fullPrompt);
        $artifact    = $this->runAgentThread($agentThread);

        if ($artifact) {
            // Attach the artifact to the TeamObject with the category
            $teamObject->artifacts()->attach($artifact->id, ['category' => $categoryName]);

            static::logDebug("Attached artifact {$artifact->id} to TeamObject {$teamObject->id} with category: {$categoryName}");

            $this->complete([$artifact]);
        } else {
            static::logDebug('No artifact generated from agent thread');
            $this->complete();
        }
    }

    /**
     * Resolve the root TeamObject from the task run's input artifacts.
     */
    protected function resolveRootTeamObject(): ?TeamObject
    {
        $inputArtifacts = $this->taskRun->inputArtifacts;

        foreach ($inputArtifacts as $artifact) {
            $teamObjectId = $artifact->json_content['team_object_id'] ?? null;
            if ($teamObjectId) {
                return TeamObject::find($teamObjectId);
            }
        }

        return null;
    }

    /**
     * Create TaskProcesses for each artifact category definition and target TeamObject pair.
     */
    protected function createProcessesForCategoryDefinitions(
        SchemaDefinition $schemaDefinition,
        Collection $artifactCategoryDefinitions,
        TeamObject $rootTeamObject
    ): void {
        $processCount = 0;

        foreach ($artifactCategoryDefinitions as $categoryDefinition) {
            // Resolve target TeamObjects based on fragment_selector
            $targetTeamObjects = $this->resolveTargetTeamObjects($categoryDefinition, $rootTeamObject);

            static::logDebug("Category '{$categoryDefinition->name}': found {$targetTeamObjects->count()} target TeamObjects");

            foreach ($targetTeamObjects as $targetTeamObject) {
                $this->createArtifactGenerationProcess($categoryDefinition, $targetTeamObject, $rootTeamObject);
                $processCount++;
            }
        }

        static::logDebug("Created {$processCount} artifact generation processes");

        // Dispatch the created processes
        TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);
    }

    /**
     * Resolve target TeamObjects based on the fragment_selector.
     * If fragment_selector is null, returns the root TeamObject.
     * Otherwise, traverses the relationship path to find related TeamObjects.
     */
    protected function resolveTargetTeamObjects(ArtifactCategoryDefinition $categoryDefinition, TeamObject $rootTeamObject): Collection
    {
        $fragmentSelector = $categoryDefinition->fragment_selector;

        // If no fragment_selector, target is the root TeamObject
        if (!$fragmentSelector || empty($fragmentSelector)) {
            return collect([$rootTeamObject]);
        }

        // The fragment_selector is a path array like ["providers"] that points to a relationship
        // Traverse the path to get related TeamObjects
        return $this->traverseRelationshipPath($rootTeamObject, $fragmentSelector);
    }

    /**
     * Traverse a relationship path from the root TeamObject to get related objects.
     *
     * @param  array  $path  Array of relationship names to traverse, e.g., ["providers"] or ["providers", "contacts"]
     */
    protected function traverseRelationshipPath(TeamObject $startObject, array $path): Collection
    {
        $currentObjects = collect([$startObject]);

        foreach ($path as $relationshipName) {
            $nextObjects = collect();

            foreach ($currentObjects as $object) {
                // Use the relatedObjects method to get TeamObjects for this relationship name
                $related     = $object->relatedObjects($relationshipName)->get();
                $nextObjects = $nextObjects->merge($related);
            }

            $currentObjects = $nextObjects;

            // If no objects found at any level, return empty collection
            if ($currentObjects->isEmpty()) {
                return collect();
            }
        }

        return $currentObjects;
    }

    /**
     * Create a TaskProcess for generating an artifact for a specific TeamObject.
     */
    protected function createArtifactGenerationProcess(
        ArtifactCategoryDefinition $categoryDefinition,
        TeamObject $targetTeamObject,
        TeamObject $rootTeamObject
    ): void {
        // Resolve the full TeamObject data for the LLM
        $teamObjectData = $this->resolveTeamObjectData($targetTeamObject, $rootTeamObject);

        // Create config artifact with all needed data
        $configArtifact = $this->createConfigArtifact([
            'team_object_id'                  => $targetTeamObject->id,
            'artifact_category_definition_id' => $categoryDefinition->id,
            'prompt'                          => $categoryDefinition->prompt,
            'category_name'                   => $categoryDefinition->name,
            'data'                            => $teamObjectData,
        ]);

        // Create the task process
        $taskProcess = $this->taskRun->taskProcesses()->create([
            'name'      => "Generate: {$categoryDefinition->label} for {$targetTeamObject->name}",
            'operation' => self::OPERATION_GENERATE_ARTIFACT,
            'activity'  => 'Preparing artifact generation',
            'is_ready'  => true,
        ]);

        // Attach the config artifact as input
        $taskProcess->inputArtifacts()->attach($configArtifact->id);
        $taskProcess->updateRelationCounter('inputArtifacts');

        $this->taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created process: {$taskProcess->id} for category: {$categoryDefinition->name}, TeamObject: {$targetTeamObject->id}");
    }

    /**
     * Create a configuration artifact for a task process.
     */
    protected function createConfigArtifact(array $config): Artifact
    {
        return Artifact::create([
            'team_id'      => $this->taskRun->team_id,
            'task_run_id'  => $this->taskRun->id,
            'name'         => self::CONFIG_ARTIFACT_NAME,
            'json_content' => $config,
        ]);
    }

    /**
     * Get configuration from the task process's input artifacts.
     */
    protected function getConfigFromProcess(): array
    {
        $configArtifact = $this->taskProcess->inputArtifacts()
            ->where('name', self::CONFIG_ARTIFACT_NAME)
            ->first();

        if (!$configArtifact) {
            throw new ValidationError(
                "No config artifact found for TaskProcess {$this->taskProcess->id}."
            );
        }

        return $configArtifact->json_content ?? [];
    }

    /**
     * Resolve full TeamObject data including attributes and relationships for the LLM.
     */
    protected function resolveTeamObjectData(TeamObject $targetTeamObject, TeamObject $rootTeamObject): array
    {
        // Load relationships
        $targetTeamObject->load(['attributes', 'relationships.related']);

        $data = [
            'id'       => $targetTeamObject->id,
            'name'     => $targetTeamObject->name,
            'type'     => $targetTeamObject->type,
            'date'     => $targetTeamObject->date?->toISOString(),
            'meta'     => $targetTeamObject->meta,
            'is_root'  => $targetTeamObject->id === $rootTeamObject->id,
        ];

        // Include all attributes as key-value pairs
        $attributes = [];
        foreach ($targetTeamObject->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }
        $data['attributes'] = $attributes;

        // Include relationships as arrays of related object summaries
        $relationships = [];
        foreach ($targetTeamObject->relationships as $relationship) {
            $relName = $relationship->relationship_name;
            if (!isset($relationships[$relName])) {
                $relationships[$relName] = [];
            }
            if ($relationship->related) {
                $relationships[$relName][] = [
                    'id'   => $relationship->related->id,
                    'name' => $relationship->related->name,
                    'type' => $relationship->related->type,
                ];
            }
        }
        $data['relationships'] = $relationships;

        // Include root object context if target is not the root
        if ($targetTeamObject->id !== $rootTeamObject->id) {
            $data['root_object'] = [
                'id'   => $rootTeamObject->id,
                'name' => $rootTeamObject->name,
                'type' => $rootTeamObject->type,
            ];
        }

        return $data;
    }

    /**
     * Build the full LLM prompt with TeamObject data.
     */
    protected function buildLlmPrompt(string $basePrompt, array $data, TeamObject $teamObject): string
    {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
{$basePrompt}

## Context Data

The following is the data for the {$teamObject->type} named "{$teamObject->name}":

```json
{$dataJson}
```

Please generate the requested content based on this data.
PROMPT;
    }

    /**
     * Setup agent thread with a specific prompt message.
     */
    protected function setupAgentThreadWithPrompt(string $prompt): AgentThread
    {
        // First setup the base agent thread
        $agentThread = $this->setupAgentThread();

        // Add the prompt as a message to the thread
        app(ThreadRepository::class)->addMessageToThread($agentThread, $prompt);

        return $agentThread;
    }
}
