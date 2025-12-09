<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Task\TaskAgentThreadBuilderService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
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

    const string CATEGORY_EXCLUDE = '__exclude';

    /**
     * Run classification on artifacts using the given schema.
     *
     * @param  TaskRun  $taskRun  The task run context
     * @param  TaskProcess  $taskProcess  The task process for this classification
     * @param  array  $classificationSchema  Classification schema with categories
     * @param  Collection  $artifacts  Artifacts to classify
     * @return array The classification results from the LLM
     */
    public function classifyArtifacts(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $classificationSchema,
        Collection $artifacts
    ): array {
        static::logDebug('Classifying ' . $artifacts->count() . ' artifacts with ' . count($classificationSchema['categories'] ?? []) . ' categories');

        // Get context artifacts based on configuration
        $contextArtifacts = $this->getContextArtifacts($taskRun, $artifacts);

        // Build agent thread with artifacts
        $agentThread = $this->buildClassificationThread($taskRun, $taskProcess, $artifacts, $contextArtifacts, $classificationSchema);

        // Run the agent thread
        $responseArtifact = $this->runClassificationThread($agentThread, $taskProcess);

        if (!$responseArtifact || !$responseArtifact->json_content) {
            throw new ValidationError('No JSON content returned from classification agent thread');
        }

        $classificationResults = $responseArtifact->json_content;

        // Check if artifacts were excluded
        if ($this->isExcluded($classificationResults)) {
            static::logDebug('Classification results indicate artifacts should be excluded');

            return [];
        }

        // Store classification results on artifacts
        $this->storeClassificationResults($artifacts, $classificationResults);

        static::logDebug('Classification completed successfully');

        return $classificationResults;
    }

    /**
     * Classify a single page/artifact using a boolean schema.
     * Returns a boolean map indicating which properties apply to this page.
     *
     * This is a simplified classification method for per-page classification workflows.
     * Unlike classifyArtifacts(), this method:
     * - Works with a SINGLE artifact
     * - Returns boolean results directly (doesn't store on artifact)
     * - Uses a boolean schema (property => boolean)
     *
     * @param  TaskRun  $taskRun  The task run context
     * @param  TaskProcess  $taskProcess  The task process for this classification
     * @param  array  $booleanSchema  Boolean schema mapping property names to descriptions
     * @param  Artifact  $artifact  Single artifact to classify
     * @param  int  $fileId  The file ID this page belongs to
     * @return array Boolean map like ['diagnosis_codes' => true, 'billing' => false, ...]
     */
    public function classifyPage(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $booleanSchema,
        Artifact $artifact,
        int $fileId
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
            'artifact_id'       => $artifact->id,
            'file_id'           => $fileId,
            'page_number'       => $artifact->page_number ?? 'unknown',
            'property_count'    => count($booleanSchema['properties'] ?? []),
        ]);

        // Build classification instructions from boolean schema
        $instructions = $this->buildBooleanSchemaInstructions($booleanSchema);

        // Build agent thread with single artifact
        $agentThread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun)
            ->withArtifacts([$artifact])
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
     * Explains what each boolean property means based on the schema descriptions.
     *
     * @param  array  $booleanSchema  Boolean schema with properties and descriptions
     * @return string Formatted instructions for the LLM
     */
    protected function buildBooleanSchemaInstructions(array $booleanSchema): string
    {
        $properties = $booleanSchema['properties'] ?? [];

        if (empty($properties)) {
            return 'Classify this page by setting boolean flags.';
        }

        $instructions = "Classify this page by determining which of the following properties apply:\n\n";

        foreach ($properties as $propertyKey => $propertyDef) {
            $description = $propertyDef['description'] ?? $propertyKey;
            $instructions .= "**{$propertyKey}**: {$description}\n";
        }

        $instructions .= "\nFor each property, return `true` if it applies to this page, `false` otherwise.";

        return $instructions;
    }

    /**
     * Build context artifacts with surrounding pages.
     * Gets pages before and after the current batch for context.
     *
     * @param  TaskRun  $taskRun  The task run to get context from
     * @param  Collection  $artifacts  The artifacts being classified
     * @param  int  $contextBefore  Number of pages before to include
     * @param  int  $contextAfter  Number of pages after to include
     * @return Collection Context artifacts
     */
    public function getContextArtifacts(
        TaskRun $taskRun,
        Collection $artifacts,
        int $contextBefore = 2,
        int $contextAfter = 1
    ): Collection {
        if ($contextBefore === 0 && $contextAfter === 0) {
            return collect();
        }

        // Get position range of current batch
        $minPosition = $artifacts->min('position');
        $maxPosition = $artifacts->max('position');

        // Fetch additional context artifacts from the full taskRun
        $contextArtifacts = $taskRun->inputArtifacts()
            ->where(function ($query) use ($minPosition, $maxPosition, $contextBefore, $contextAfter) {
                $query->whereBetween('position', [$minPosition - $contextBefore, $minPosition - 1])
                    ->orWhereBetween('position', [$maxPosition + 1, $maxPosition + $contextAfter]);
            })
            ->orderBy('position')
            ->get();

        static::logDebug('Retrieved context artifacts', [
            'context_count' => $contextArtifacts->count(),
        ]);

        return $contextArtifacts;
    }

    /**
     * Build classification instructions from schema.
     *
     * @param  array  $classificationSchema  The classification schema
     * @return string Formatted instructions for the LLM
     */
    public function buildClassificationInstructions(array $classificationSchema): string
    {
        $categories     = $classificationSchema['categories']      ?? [];
        $allowMultiple  = $classificationSchema['allow_multiple']  ?? true;
        $includeExclude = $classificationSchema['include_exclude'] ?? true;

        $instructions = "Classify the artifacts according to these categories:\n\n";

        foreach ($categories as $key => $category) {
            $name        = $category['name']        ?? $key;
            $description = $category['description'] ?? '';

            $instructions .= "**$name** ($key): $description\n";
        }

        if ($includeExclude) {
            $instructions .= "\n**Special Category:**\n";
            $instructions .= "- Use '" . self::CATEGORY_EXCLUDE . "' if the artifact contains only redacted content or is completely irrelevant\n";
        }

        if ($allowMultiple) {
            $instructions .= "\nArtifacts can be classified into multiple categories if applicable.\n";
        } else {
            $instructions .= "\nEach artifact should be classified into exactly ONE category.\n";
        }

        return $instructions;
    }

    /**
     * Parse classification results from LLM response.
     * The response should be structured JSON matching the classification schema.
     *
     * @param  string  $response  Raw LLM response
     * @param  array  $classificationSchema  The schema used for classification
     * @return array Parsed classification results
     */
    public function parseClassificationResults(string $response, array $classificationSchema): array
    {
        // For JSON schema responses, this is already handled by the agent thread
        // This method exists for potential future non-JSON-schema classification
        return json_decode($response, true) ?? [];
    }

    /**
     * Store classification results in artifact meta.
     *
     * @param  Collection  $artifacts  Artifacts to update
     * @param  array  $results  Classification results to store
     */
    public function storeClassificationResults(Collection $artifacts, array $results): void
    {
        static::logDebug('Storing classification results on ' . $artifacts->count() . ' artifacts');

        foreach ($artifacts as $artifact) {
            $meta = $artifact->meta;

            $meta['classification'] = $results;

            $artifact->meta = $meta;
            $artifact->save();

            static::logDebug("Updated artifact {$artifact->id} with classification metadata");
        }
    }

    /**
     * Get artifacts classified for a specific category.
     *
     * @param  Collection  $artifacts  Artifacts to filter
     * @param  string  $categoryKey  Category key to filter by
     * @return Collection Artifacts matching the category
     */
    public function getArtifactsForCategory(Collection $artifacts, string $categoryKey): Collection
    {
        static::logDebug('Getting artifacts for category', [
            'category'        => $categoryKey,
            'artifacts_count' => $artifacts->count(),
        ]);

        $filtered = $artifacts->filter(function ($artifact) use ($categoryKey) {
            $classification = $artifact->meta['classification'] ?? null;

            if (!$classification) {
                return false;
            }

            // Check if category exists in classification
            // Handle both simple string values and array values
            if (isset($classification[$categoryKey])) {
                $value = $classification[$categoryKey];

                // If it's an array, check if it contains the category
                if (is_array($value)) {
                    return in_array($categoryKey, $value);
                }

                // If it's a string, check if it matches
                return $value === $categoryKey || str_contains($value, $categoryKey);
            }

            // Check if category is in a 'categories' array
            if (isset($classification['categories']) && is_array($classification['categories'])) {
                return in_array($categoryKey, $classification['categories']);
            }

            return false;
        });

        static::logDebug('Filtered artifacts for category', [
            'category'        => $categoryKey,
            'filtered_count'  => $filtered->count(),
        ]);

        return $filtered;
    }

    /**
     * Build an agent thread for classification.
     *
     * @param  TaskRun  $taskRun  The task run context
     * @param  TaskProcess  $taskProcess  The task process
     * @param  Collection  $artifacts  Primary artifacts to classify
     * @param  Collection  $contextArtifacts  Context artifacts for reference
     * @param  array  $classificationSchema  Classification schema
     * @return AgentThread The configured agent thread
     */
    protected function buildClassificationThread(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        Collection $contextArtifacts,
        array $classificationSchema
    ): AgentThread {
        $taskDefinition = $taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new ValidationError('Agent not found for TaskRun: ' . $taskRun->id);
        }

        // Build the agent thread using the task-specific builder
        $agentThread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun)
            ->withContextArtifacts($artifacts, $contextArtifacts)
            ->includePageNumbers(false)
            ->build();

        // Add classification-specific instructions if we have context
        if (!$contextArtifacts->isEmpty()) {
            static::logDebug('Adding context artifacts to classification thread', [
                'context_count' => $contextArtifacts->count(),
            ]);

            app(ThreadRepository::class)->addMessageToThread($agentThread,
                "IMPORTANT CLASSIFICATION RULES:\n" .
                "1. You are classifying ONLY the PRIMARY ARTIFACTS section\n" .
                "2. Context artifacts are provided to help determine if content flows between pages\n" .
                "3. ONLY use context artifacts for classification if they appear to be part of the same document/flow as the primary artifacts\n" .
                '4. If context appears unrelated, IGNORE it for classification purposes'
            );
        }

        // Add exclusion instructions
        app(ThreadRepository::class)->addMessageToThread($agentThread,
            "If the only content in the artifact is 'Excluded...' or is very obviously all redacted content and there is no other content of interest, " .
            'then set the category values to ' . self::CATEGORY_EXCLUDE . ' so the artifacts will be ignored entirely'
        );

        // Add schema-specific instructions
        $instructions = $this->buildClassificationInstructions($classificationSchema);
        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);

        // Associate thread with task process
        $taskProcess->agentThread()->associate($agentThread)->save();

        return $agentThread;
    }

    /**
     * Run the classification agent thread.
     *
     * @param  AgentThread  $agentThread  The configured agent thread
     * @param  TaskProcess  $taskProcess  The task process
     * @return \App\Models\Task\Artifact|null The response artifact
     */
    protected function runClassificationThread(AgentThread $agentThread, TaskProcess $taskProcess): ?\App\Models\Task\Artifact
    {
        $agent            = $agentThread->agent;
        $schemaDefinition = $taskProcess->taskRun->taskDefinition->schemaDefinition;

        // Use the TaskRunner's runAgentThreadWithSchema method pattern
        // Get timeout from task_runner_config if available
        $timeout = $taskProcess->taskRun->taskDefinition->task_runner_config['timeout'] ?? null;
        if ($timeout !== null) {
            $timeout = (int)$timeout;
            $timeout = max(1, min($timeout, 600)); // Ensure between 1 and 600 seconds
        }

        static::logDebug("Running classification thread with agent: {$agent->name}");

        // Run the thread
        $threadRun = (new \App\Services\AgentThread\AgentThreadService)
            ->withResponseFormat($schemaDefinition)
            ->withTimeout($timeout)
            ->run($agentThread);

        // Create the artifact from the response
        if ($threadRun->lastMessage) {
            $artifact = (new \App\Services\AgentThread\AgentThreadMessageToArtifactMapper)
                ->setThreadRun($threadRun)
                ->setMessage($threadRun->lastMessage)
                ->map();

            if ($artifact && $schemaDefinition) {
                $artifact->schemaDefinition()->associate($schemaDefinition)->save();
            }

            return $artifact;
        }

        return null;
    }

    /**
     * Recursively check if any values in the JSON content indicate exclusion.
     *
     * @param  array  $jsonContent  Classification results
     * @return bool True if artifacts should be excluded
     */
    protected function isExcluded(array $jsonContent): bool
    {
        foreach ($jsonContent as $value) {
            if (is_array($value)) {
                if ($this->isExcluded($value)) {
                    return true;
                }
            } elseif (is_string($value) && str_contains($value, self::CATEGORY_EXCLUDE)) {
                return true;
            }
        }

        return false;
    }
}
