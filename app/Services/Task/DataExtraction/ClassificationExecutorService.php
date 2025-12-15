<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\Task\TaskAgentThreadBuilderService;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Encapsulates reusable classification execution logic for task runners.
 * Handles running classification on artifacts using given schemas and storing results.
 *
 * Usage Example:
 * ```php
 * $service = app(ClassificationExecutorService::class);
 * $schema = app(ClassificationSchemaBuilder::class)->buildSchemaFromGroups($plan, $level);
 *
 * $results = $service->classifyArtifacts(
 *     $taskRun,
 *     $taskProcess,
 *     $schema,
 *     $artifacts
 * );
 *
 * // Get artifacts for a specific category
 * $categoryArtifacts = $service->getArtifactsForCategory($artifacts, 'category_key');
 * ```
 *
 * Classification results are stored in Artifact.meta['classification']:
 * ```php
 * $artifact->meta['classification'] = [
 *     'category_name' => 'value',
 *     'other_category' => 'value',
 *     // ... other classifications
 * ];
 * ```
 */
class ClassificationExecutorService
{
    use HasDebugLogging;

    /**
     * Classify a single page/artifact using a boolean schema.
     * Returns a boolean map indicating which properties apply to this page.
     *
     * @param  TaskRun  $taskRun  The task run context
     * @param  TaskProcess  $taskProcess  The task process for this classification
     * @param  array  $booleanSchema  Boolean schema mapping property names to descriptions
     * @param  Artifact  $artifact  Single artifact to classify (must have storedFiles attached)
     * @return array Boolean map like ['diagnosis_codes' => true, 'billing' => false, ...]
     */
    public function classifyPage(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $booleanSchema,
        Artifact $artifact
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent) {
            throw new ValidationError('Agent not found for TaskRun: ' . $taskRun->id);
        }

        if (!$schemaDefinition) {
            throw new ValidationError('SchemaDefinition not found for TaskRun: ' . $taskRun->id);
        }

        static::logDebug('Classifying single page', [
            'artifact_id'    => $artifact->id,
            'page_number'    => $artifact->position ?? 'unknown',
            'property_count' => count($booleanSchema['properties'] ?? []),
        ]);

        // Build classification instructions from boolean schema
        $instructions = $this->buildBooleanSchemaInstructions($booleanSchema);

        // Create filter that only includes files and text (no meta, no json for classification)
        $filter = new ArtifactFilter(
            includeText: true,
            includeFiles: true,
            includeJson: false,
            includeMeta: false
        );

        // Build agent thread with single artifact
        $agentThread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun)
            ->withArtifacts([$artifact], $filter)
            ->includePageNumbers(false)
            ->build();

        // Add classification instructions
        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);

        // Associate thread with task process
        $taskProcess->agentThread()->associate($agentThread)->save();

        // Get timeout from config
        $timeout = $taskDefinition->task_runner_config['timeout'] ?? null;
        if ($timeout !== null) {
            $timeout = (int)$timeout;
            $timeout = max(1, min($timeout, 600)); // Between 1-600 seconds
        }

        static::logDebug("Running page classification thread with agent: {$taskDefinition->agent->name}");

        // Create a temporary in-memory SchemaDefinition with the boolean schema
        // This allows us to pass a custom schema to withResponseFormat() without persisting to the database
        $tempSchemaDefinition = new SchemaDefinition([
            'name'   => 'page-classification',
            'schema' => $booleanSchema,
        ]);

        // Run the thread with the boolean schema as response format
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($tempSchemaDefinition)
            ->withTimeout($timeout)
            ->run($agentThread);

        if (!$threadRun->isCompleted()) {
            throw new ValidationError('Page classification thread failed: ' . ($threadRun->error ?? 'Unknown error'));
        }

        // Get JSON content from response
        $classificationResults = $threadRun->lastMessage?->getJsonContent();

        if (!$classificationResults || !is_array($classificationResults)) {
            throw new ValidationError('No valid JSON content returned from page classification thread');
        }

        static::logDebug('Page classification completed', [
            'artifact_id' => $artifact->id,
            'results'     => $classificationResults,
        ]);

        return $classificationResults;
    }

    /**
     * Build classification instructions from a boolean schema.
     * Returns the agent role description - property details are in the JSON schema itself.
     *
     * @param  array  $booleanSchema  Boolean schema with properties and descriptions
     * @return string Agent role description for the LLM
     */
    protected function buildBooleanSchemaInstructions(array $booleanSchema): string
    {
        return 'You are an expert, detail-oriented agent designed to precisely classify each page. Pay close attention to the descriptions for each data point in the schema and decide on the correct classification relative to the contents of the page.';
    }
}
