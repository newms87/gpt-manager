<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * Service for resolving field conflicts during batch data extraction.
 *
 * When extracting data in batches, different batches may return different meaningful values
 * for the same field. This service detects these conflicts and makes a follow-up LLM call
 * with the relevant source pages to determine the correct value.
 *
 * Example Conflict:
 * - Batch 1 (Page 1): name = "Treatment for headaches, neck, upper back..."
 * - Batch 2 (Page 3): name = "Cervical, thoracic, and lumbar sprains..."
 *
 * Both are meaningful but different - this service asks the LLM which is correct.
 *
 * Usage Example:
 * ```php
 * $conflicts = [
 *     [
 *         'field_path' => 'care_summary.name',
 *         'field_name' => 'name',
 *         'existing_value' => 'Treatment for headaches...',
 *         'existing_page' => 1,
 *         'new_value' => 'Cervical sprains...',
 *         'new_page' => 3,
 *     ],
 * ];
 *
 * $resolution = app(ConflictResolutionService::class)->resolveConflicts(
 *     $taskRun,
 *     $taskProcess,
 *     $conflicts,
 *     $allArtifacts,
 *     $schemaDefinition
 * );
 *
 * // Apply resolved values
 * foreach ($resolution['resolved_data'] as $fieldName => $value) {
 *     $data[$fieldName] = $value;
 * }
 * ```
 */
class ConflictResolutionService
{
    use HasDebugLogging;

    /**
     * Resolve field conflicts by asking the LLM to compare values from source pages.
     *
     * @param  TaskRun  $taskRun  The task run context
     * @param  TaskProcess  $taskProcess  The task process context
     * @param  array  $conflicts  Array of conflicts from mergeExtractionResultsWithConflicts
     * @param  Collection  $allArtifacts  All page artifacts available for context
     * @param  array  $schemaDefinition  The extraction schema (for field descriptions)
     * @return array{resolved_data: array<string, mixed>, resolved_page_sources: array<string, int>}
     */
    public function resolveConflicts(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $conflicts,
        Collection $allArtifacts,
        array $schemaDefinition
    ): array {
        if (empty($conflicts)) {
            return ['resolved_data' => [], 'resolved_page_sources' => []];
        }

        static::logDebug('Resolving conflicts', [
            'conflict_count' => count($conflicts),
            'fields'         => array_column($conflicts, 'field_name'),
        ]);

        // Get unique page numbers involved in conflicts
        $pageNumbers = $this->extractPageNumbers($conflicts);

        // Filter artifacts to only relevant pages
        $relevantArtifacts = $this->filterArtifactsByPages($allArtifacts, $pageNumbers);

        if ($relevantArtifacts->isEmpty()) {
            static::logDebug('No relevant artifacts found for conflict resolution');

            return ['resolved_data' => [], 'resolved_page_sources' => []];
        }

        // Build prompt and schema
        $prompt = $this->buildPrompt($conflicts, $schemaDefinition);
        $schema = $this->buildResponseSchema($conflicts);

        // Run the LLM thread
        $result = $this->runThread($taskRun, $taskProcess, $relevantArtifacts, $prompt, $schema);

        if (empty($result)) {
            static::logDebug('Conflict resolution returned empty result');

            return ['resolved_data' => [], 'resolved_page_sources' => []];
        }

        return $this->parseResult($result, $conflicts);
    }

    /**
     * Extract unique page numbers from conflicts.
     *
     * @return array<int>
     */
    protected function extractPageNumbers(array $conflicts): array
    {
        $pages = [];

        foreach ($conflicts as $conflict) {
            if (!empty($conflict['existing_page'])) {
                $pages[] = (int)$conflict['existing_page'];
            }
            if (!empty($conflict['new_page'])) {
                $pages[] = (int)$conflict['new_page'];
            }
        }

        return array_unique($pages);
    }

    /**
     * Filter artifacts to only those matching the specified page numbers.
     */
    protected function filterArtifactsByPages(Collection $artifacts, array $pageNumbers): Collection
    {
        if (empty($pageNumbers)) {
            return collect();
        }

        return $artifacts->filter(function ($artifact) use ($pageNumbers) {
            $position = $artifact->position ?? null;

            return $position !== null && in_array((int)$position, $pageNumbers, true);
        });
    }

    /**
     * Build the prompt for conflict resolution.
     */
    protected function buildPrompt(array $conflicts, array $schemaDefinition): string
    {
        $conflictDescriptions = [];

        foreach ($conflicts as $conflict) {
            $fieldName        = $conflict['field_name'];
            $fieldDescription = $this->getFieldDescription($fieldName, $schemaDefinition);

            $existingValue = $this->formatValueForPrompt($conflict['existing_value']);
            $newValue      = $this->formatValueForPrompt($conflict['new_value']);

            $conflictDescriptions[] = <<<YAML
- field: {$fieldName}
  description: {$fieldDescription}
  option_a:
    value: "{$existingValue}"
    source_page: {$conflict['existing_page']}
  option_b:
    value: "{$newValue}"
    source_page: {$conflict['new_page']}
YAML;
        }

        $conflictsYaml = implode("\n", $conflictDescriptions);

        // Load prompt template from external file
        $template = file_get_contents(resource_path('prompts/extract-data/conflict-resolution.md'));

        return strtr($template, [
            '{{conflicts_yaml}}' => $conflictsYaml,
        ]);
    }

    /**
     * Get the description for a field from the schema definition.
     */
    protected function getFieldDescription(string $fieldName, array $schemaDefinition): string
    {
        // Navigate schema to find field description
        $properties = $schemaDefinition['properties'] ?? [];

        // Check direct properties first
        if (isset($properties[$fieldName]['description'])) {
            return $properties[$fieldName]['description'];
        }

        // Check nested properties
        foreach ($properties as $prop) {
            if (!is_array($prop)) {
                continue;
            }

            $nestedProps = $prop['properties'] ?? [];

            if (isset($nestedProps[$fieldName]['description'])) {
                return $nestedProps[$fieldName]['description'];
            }

            // Check items for array types
            $itemsProps = $prop['items']['properties'] ?? [];

            if (isset($itemsProps[$fieldName]['description'])) {
                return $itemsProps[$fieldName]['description'];
            }
        }

        return 'No description available';
    }

    /**
     * Format a value for inclusion in the YAML prompt.
     * Escapes quotes and newlines.
     */
    protected function formatValueForPrompt(mixed $value): string
    {
        if (!is_string($value)) {
            $value = json_encode($value);
        }

        // Escape quotes and newlines for YAML
        return str_replace(['"', "\n", "\r"], ['\\"', '\\n', ''], $value);
    }

    /**
     * Build the JSON schema for the LLM response.
     *
     * Loads the base field resolution schema from YAML and builds a dynamic
     * schema with a property for each conflict field.
     */
    protected function buildResponseSchema(array $conflicts): array
    {
        // Load base field resolution schema from YAML
        $schemaTemplate = Yaml::parseFile(resource_path('schemas/extract-data/conflict-resolution-response.yaml'));
        $fieldSchema    = $schemaTemplate['field_resolution'];

        $properties = [];
        $required   = [];

        foreach ($conflicts as $conflict) {
            $fieldName = $conflict['field_name'];

            // Clone the base schema and customize the description
            $fieldResolution                = $fieldSchema;
            $fieldResolution['description'] = "Resolution for the '{$fieldName}' field conflict";

            $properties[$fieldName] = $fieldResolution;
            $required[]             = $fieldName;
        }

        return [
            'type'                 => 'object',
            'properties'           => $properties,
            'required'             => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * Run the LLM thread for conflict resolution.
     */
    protected function runThread(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $artifacts,
        string $prompt,
        array $schema
    ): ?array {
        $taskDefinition = $taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            static::logDebug('No agent available for conflict resolution');

            return null;
        }

        $config  = $taskDefinition->task_runner_config ?? [];
        $timeout = $config['conflict_resolution_timeout'] ?? 120;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Create transient schema definition for response format
        $schemaDefinition         = new SchemaDefinition;
        $schemaDefinition->schema = $schema;
        $schemaDefinition->name   = 'ConflictResolutionResponse';
        $schemaDefinition->type   = SchemaDefinition::TYPE_AGENT_RESPONSE;

        static::logDebug('Building conflict resolution thread', [
            'artifact_count' => $artifacts->count(),
            'timeout'        => $timeout,
        ]);

        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Conflict Resolution')
            ->withArtifacts($artifacts, new ArtifactFilter(
                includeFiles: false,
                includeJson: false,
                includeMeta: false,
                includeTextTranscodes: true
            ))
            ->includePageNumbers()
            ->withMessage($prompt)
            ->withResponseSchema($schemaDefinition)
            ->withTimeout($timeout)
            ->build();

        // Associate thread with task process for debugging
        $taskProcess->agentThread()->associate($thread)->save();

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($schemaDefinition)
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Conflict resolution thread did not complete', [
                'status' => $threadRun->status,
                'error'  => $threadRun->error,
            ]);

            return null;
        }

        return $threadRun->lastMessage?->getJsonContent();
    }

    /**
     * Parse the LLM result into resolved data and page sources.
     *
     * @return array{resolved_data: array<string, mixed>, resolved_page_sources: array<string, int>}
     */
    protected function parseResult(array $result, array $conflicts): array
    {
        $resolvedData        = [];
        $resolvedPageSources = [];

        foreach ($conflicts as $conflict) {
            $fieldName = $conflict['field_name'];

            if (!isset($result[$fieldName])) {
                continue;
            }

            $resolution = $result[$fieldName];

            if (isset($resolution['resolved_value'])) {
                $resolvedData[$fieldName] = $resolution['resolved_value'];

                static::logDebug('Resolved conflict', [
                    'field'          => $fieldName,
                    'resolved_value' => mb_substr((string)$resolution['resolved_value'], 0, 100) . '...',
                    'source_page'    => $resolution['source_page'] ?? null,
                ]);
            }

            if (isset($resolution['source_page'])) {
                $resolvedPageSources[$fieldName] = (int)$resolution['source_page'];
            }
        }

        return [
            'resolved_data'        => $resolvedData,
            'resolved_page_sources' => $resolvedPageSources,
        ];
    }
}
