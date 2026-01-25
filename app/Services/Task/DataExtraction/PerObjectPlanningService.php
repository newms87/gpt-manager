<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use Newms87\Danx\Traits\HasDebugLogging;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;

/**
 * Service for per-object planning in data extraction.
 *
 * Creates and executes planning processes for each object type:
 * 1. Plan: Identify - Select identity fields and skim fields for each object type
 * 2. Plan: Remaining - Group remaining fields into extraction groups
 *
 * Stores per-object plans in TaskRun.meta and compiles them into final extraction plan.
 */
class PerObjectPlanningService
{
    use HasDebugLogging;

    private const MAX_FIELD_GROUPING_ATTEMPTS = 3;

    /**
     * Create "Plan: Identify" TaskProcess for each object type.
     *
     * @param  array  $objectTypes  Array of object type structures from ObjectTypeExtractor
     * @return array Array of created TaskProcess instances
     */
    public function createIdentityPlanningProcesses(TaskRun $taskRun, array $objectTypes): array
    {
        $processes = [];

        foreach ($objectTypes as $objectType) {
            $process = $taskRun->taskProcesses()->create([
                'name'      => "Plan: Identify {$objectType['name']}",
                'operation' => 'Plan: Identify',
                'activity'  => "Identifying fields for {$objectType['name']}",
                'meta'      => [
                    'object_type'   => $objectType['name'],
                    'object_path'   => $objectType['path'],
                    'level'         => $objectType['level'],
                    'parent_type'   => $objectType['parent_type'],
                    'is_array'      => $objectType['is_array'],
                    'simple_fields' => $objectType['simple_fields'],
                ],
                'is_ready'  => true,
            ]);

            $processes[] = $process;

            static::logDebug("Created Plan: Identify process for {$objectType['name']}", [
                'task_process_id' => $process->id,
                'level'           => $objectType['level'],
            ]);
        }

        return $processes;
    }

    /**
     * Execute identity planning for a single object type.
     *
     * Uses LLM to select identity fields and skim fields.
     * Stores result in TaskRun.meta['per_object_plans'][object_type].
     */
    public function executeIdentityPlanning(TaskRun $taskRun, TaskProcess $taskProcess): void
    {
        $taskDefinition = $taskRun->taskDefinition;
        $objectTypeInfo = $taskProcess->meta;

        if (!$objectTypeInfo || !isset($objectTypeInfo['object_type'])) {
            throw new ValidationError('TaskProcess meta must contain object_type info');
        }

        $objectType = $objectTypeInfo['object_type'];

        static::logDebug("Executing identity planning for $objectType", [
            'task_run_id'     => $taskRun->id,
            'task_process_id' => $taskProcess->id,
        ]);

        // Get config from task runner config
        $config = [
            'group_max_points'   => $taskDefinition->task_runner_config['group_max_points']   ?? 10,
            'global_search_mode' => $taskDefinition->task_runner_config['global_search_mode'] ?? 'intelligent',
        ];

        // Build prompt using IdentityPlanningPromptBuilder
        $promptBuilder = app(IdentityPlanningPromptBuilder::class);
        $prompt        = $promptBuilder->buildIdentityPrompt($objectTypeInfo, $config);

        // Create response schema
        $responseSchema = $promptBuilder->createIdentityResponseSchema();

        // Create agent thread
        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->taskDefinition->team_id)
            ->named("Plan: Identify $objectType")
            ->withSystemMessage($prompt)
            ->withResponseSchema($responseSchema)
            ->build();

        // Run the thread and get response
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($responseSchema)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            throw new ValidationError('Identity planning thread run failed: ' . ($threadRun->error ?? 'Unknown error'));
        }

        // Get JSON content from response
        $response = $threadRun->lastMessage?->getJsonContent() ?? [];

        if (empty($response)) {
            throw new ValidationError('Failed to get identity planning response from LLM');
        }

        static::logDebug("Received identity planning response for $objectType", [
            'identity_fields_count' => count($response['identity_fields'] ?? []),
            'skim_fields_count'     => count($response['skim_fields']     ?? []),
        ]);

        // Calculate remaining fields (fields not in skim_fields)
        $simpleFieldKeys    = array_keys($objectTypeInfo['simple_fields'] ?? []);
        $skimFields         = $response['skim_fields']       ?? [];
        $remainingFields    = array_diff($simpleFieldKeys, $skimFields);
        $hasRemainingFields = !empty($remainingFields);

        // Prepare remaining fields with their metadata
        $remainingFieldsWithMeta = [];
        foreach ($remainingFields as $fieldKey) {
            $remainingFieldsWithMeta[$fieldKey] = $objectTypeInfo['simple_fields'][$fieldKey] ?? [];
        }

        // Store per-object plan
        $planData = [
            'object_type'        => $objectType,
            'path'               => $objectTypeInfo['object_path']   ?? '',
            'level'              => $objectTypeInfo['level']         ?? 0,
            'is_array'           => $objectTypeInfo['is_array']      ?? false,
            'parent_type'        => $objectTypeInfo['parent_type']   ?? null,
            'identity_group'     => [
                'identity_fields' => $response['identity_fields'] ?? [],
                'skim_fields'     => $skimFields,
                'search_mode'     => $response['search_mode']     ?? 'skim',
                'description'     => $response['description']     ?? null,
            ],
            'has_remaining_fields' => $hasRemainingFields,
            'remaining_fields'     => $remainingFieldsWithMeta,
            'reasoning'            => $response['reasoning']       ?? null,
        ];

        $this->storePerObjectPlan($taskRun, $objectType, $planData);

        static::logDebug("Stored identity plan for $objectType", [
            'has_remaining'   => $hasRemainingFields,
            'remaining_count' => count($remainingFields),
        ]);
    }

    /**
     * Create "Plan: Remaining" TaskProcess for each object type with remaining fields.
     *
     * @return array Array of created TaskProcess instances
     */
    public function createRemainingProcesses(TaskRun $taskRun): array
    {
        $perObjectPlans = $this->getPerObjectPlans($taskRun);
        $processes      = [];

        foreach ($perObjectPlans as $objectType => $planData) {
            if (!($planData['has_remaining_fields'] ?? false)) {
                continue;
            }

            $process = $taskRun->taskProcesses()->create([
                'name'      => "Plan: Remaining $objectType",
                'operation' => 'Plan: Remaining',
                'activity'  => "Grouping remaining fields for $objectType",
                'meta'      => [
                    'object_type'      => $objectType,
                    'remaining_fields' => $planData['remaining_fields'] ?? [],
                ],
                'is_ready'  => true,
            ]);

            $processes[] = $process;

            static::logDebug("Created Plan: Remaining process for $objectType", [
                'task_process_id'        => $process->id,
                'remaining_fields_count' => count($planData['remaining_fields'] ?? []),
            ]);
        }

        return $processes;
    }

    /**
     * Validate that all remaining fields are covered in extraction groups.
     *
     * @param  array  $remainingFieldKeys  Field keys that should be covered
     * @param  array  $extractionGroups  LLM response extraction_groups
     * @return array ['covered' => [...], 'missing' => [...], 'duplicates' => [...]]
     */
    protected function validateFieldCoverage(array $remainingFieldKeys, array $extractionGroups): array
    {
        $coveredFields   = [];
        $duplicateFields = [];

        foreach ($extractionGroups as $group) {
            $fields = $group['fields'] ?? [];
            foreach ($fields as $field) {
                if (in_array($field, $coveredFields)) {
                    $duplicateFields[] = $field;
                } else {
                    $coveredFields[] = $field;
                }
            }
        }

        $missingFields = array_diff($remainingFieldKeys, $coveredFields);

        return [
            'covered'    => $coveredFields,
            'missing'    => array_values($missingFields),
            'duplicates' => array_unique($duplicateFields),
        ];
    }

    /**
     * De-duplicate fields across extraction groups (keep first occurrence).
     */
    protected function deduplicateFields(array $extractionGroups): array
    {
        $seenFields   = [];
        $deduplicated = [];

        foreach ($extractionGroups as $group) {
            $cleanedFields = [];

            foreach ($group['fields'] ?? [] as $field) {
                if (!in_array($field, $seenFields)) {
                    $cleanedFields[] = $field;
                    $seenFields[]    = $field;
                }
            }

            if (!empty($cleanedFields)) {
                $group['fields'] = $cleanedFields;
                $deduplicated[]  = $group;
            }
        }

        return $deduplicated;
    }

    /**
     * Build prompt for current attempt (initial or follow-up).
     */
    protected function buildPromptForAttempt(
        IdentityPlanningPromptBuilder $promptBuilder,
        array $objectTypeInfo,
        array $fieldsToGroup,
        array $config,
        int $attempt
    ): string {
        if ($attempt === 1) {
            return $promptBuilder->buildRemainingPrompt($objectTypeInfo, $fieldsToGroup, $config);
        }

        return $promptBuilder->buildRemainingFollowUpPrompt($objectTypeInfo, $fieldsToGroup, $config, $attempt);
    }

    /**
     * Execute remaining fields planning for a single object type.
     *
     * Uses LLM to group remaining fields into extraction groups.
     * Updates stored plan with extraction_groups.
     */
    public function executeRemainingPlanning(TaskRun $taskRun, TaskProcess $taskProcess): void
    {
        $taskDefinition  = $taskRun->taskDefinition;
        $objectType      = $taskProcess->meta['object_type']      ?? null;
        $remainingFields = $taskProcess->meta['remaining_fields'] ?? [];

        if (!$objectType) {
            throw new ValidationError('TaskProcess meta must contain object_type');
        }

        static::logDebug("Executing remaining planning for $objectType", [
            'task_run_id'     => $taskRun->id,
            'task_process_id' => $taskProcess->id,
            'fields_count'    => count($remainingFields),
        ]);

        // Get stored per-object plan to retrieve full object type info
        $perObjectPlans = $this->getPerObjectPlans($taskRun);
        $objectTypeInfo = $perObjectPlans[$objectType] ?? null;

        if (!$objectTypeInfo) {
            throw new ValidationError("No per-object plan found for $objectType");
        }

        // Get config
        $config = [
            'group_max_points' => $taskDefinition->task_runner_config['group_max_points'] ?? 10,
        ];

        // Initialize tracking variables
        $remainingFieldKeys      = array_keys($remainingFields);
        $remainingFieldsToGroup  = $remainingFields;
        $allExtractionGroups     = [];
        $attemptHistory          = [];

        $promptBuilder  = app(IdentityPlanningPromptBuilder::class);
        $responseSchema = $promptBuilder->createRemainingResponseSchema();

        // Multi-attempt loop
        for ($attempt = 1; $attempt <= self::MAX_FIELD_GROUPING_ATTEMPTS; $attempt++) {
            static::logDebug("Attempt $attempt for remaining planning: $objectType", [
                'fields_to_group_count' => count($remainingFieldsToGroup),
            ]);

            // Build prompt for this attempt
            $prompt = $this->buildPromptForAttempt(
                $promptBuilder,
                $objectTypeInfo,
                $remainingFieldsToGroup,
                $config,
                $attempt
            );

            // Create agent thread
            $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->taskDefinition->team_id)
                ->named("Plan: Remaining $objectType (Attempt $attempt)")
                ->withSystemMessage($prompt)
                ->withResponseSchema($responseSchema)
                ->build();

            // Run the thread
            $threadRun = app(AgentThreadService::class)
                ->withResponseFormat($responseSchema)
                ->run($thread);

            if (!$threadRun->isCompleted()) {
                throw new ValidationError('Remaining planning thread run failed: ' . ($threadRun->error ?? 'Unknown error'));
            }

            // Get JSON content from response
            $response = $threadRun->lastMessage?->getJsonContent() ?? [];

            if (empty($response)) {
                throw new ValidationError('Failed to get remaining planning response from LLM');
            }

            $extractionGroups = $response['extraction_groups'] ?? [];

            static::logDebug("Received remaining planning response for $objectType (Attempt $attempt)", [
                'extraction_groups_count' => count($extractionGroups),
            ]);

            // Accumulate groups from this attempt
            $allExtractionGroups = array_merge($allExtractionGroups, $extractionGroups);

            // Validate field coverage
            $fieldsToGroupKeys = array_keys($remainingFieldsToGroup);
            $coverage          = $this->validateFieldCoverage($fieldsToGroupKeys, $extractionGroups);

            // Store attempt info
            $attemptHistory[] = [
                'attempt'          => $attempt,
                'fields_to_group'  => $fieldsToGroupKeys,
                'groups_returned'  => count($extractionGroups),
                'covered_fields'   => $coverage['covered'],
                'missing_fields'   => $coverage['missing'],
                'duplicate_fields' => $coverage['duplicates'],
            ];

            // Log coverage results
            if (!empty($coverage['duplicates'])) {
                static::logDebug("Duplicate fields detected in attempt $attempt", [
                    'duplicates' => $coverage['duplicates'],
                ]);
            }

            if (empty($coverage['missing'])) {
                static::logDebug("All fields covered in attempt $attempt for $objectType");
                break;
            }

            // If we have missing fields and attempts remain, prepare for next attempt
            if ($attempt < self::MAX_FIELD_GROUPING_ATTEMPTS) {
                static::logDebug('Missing fields detected, will retry', [
                    'missing_count'  => count($coverage['missing']),
                    'missing_fields' => $coverage['missing'],
                ]);

                // Prepare remaining fields for next attempt (only missing fields)
                $remainingFieldsToGroup = [];
                foreach ($coverage['missing'] as $missingField) {
                    if (isset($remainingFields[$missingField])) {
                        $remainingFieldsToGroup[$missingField] = $remainingFields[$missingField];
                    }
                }
            } else {
                // Max attempts reached with missing fields
                throw new ValidationError(
                    'Failed to cover all fields after ' . self::MAX_FIELD_GROUPING_ATTEMPTS . " attempts for $objectType. " .
                    'Missing fields: ' . implode(', ', $coverage['missing'])
                );
            }
        }

        // De-duplicate fields across all accumulated groups
        $allExtractionGroups = $this->deduplicateFields($allExtractionGroups);

        static::logDebug("De-duplicated extraction groups for $objectType", [
            'final_groups_count' => count($allExtractionGroups),
        ]);

        // Validate search_mode values on all groups
        $validSearchModes = ['skim', 'exhaustive'];
        foreach ($allExtractionGroups as $group) {
            $searchMode = $group['search_mode'] ?? null;
            if (!in_array($searchMode, $validSearchModes, true)) {
                throw new ValidationError(
                    "Invalid search_mode '$searchMode' in extraction group '{$group['name']}' for $objectType. " .
                    'Must be one of: ' . implode(', ', $validSearchModes)
                );
            }
        }

        // Update TaskProcess meta with attempt history
        $taskProcess->meta = array_merge($taskProcess->meta ?? [], [
            'attempt_history' => $attemptHistory,
            'total_attempts'  => count($attemptHistory),
        ]);
        $taskProcess->save();

        // Update stored plan with extraction_groups
        $objectTypeInfo['extraction_groups'] = $allExtractionGroups;
        $this->storePerObjectPlan($taskRun, $objectType, $objectTypeInfo);

        static::logDebug("Updated plan with extraction groups for $objectType", [
            'groups_count'   => count($allExtractionGroups),
            'total_attempts' => count($attemptHistory),
        ]);
    }

    /**
     * Store per-object plan in TaskRun.meta['per_object_plans'][object_type].
     */
    public function storePerObjectPlan(TaskRun $taskRun, string $objectType, array $planData): void
    {
        LockHelper::acquire($taskRun);

        try {
            // Refresh to get latest data after acquiring lock
            $taskRun->refresh();

            $meta                                  = $taskRun->meta ?? [];
            $meta['per_object_plans'][$objectType] = $planData;
            $taskRun->meta                         = $meta;
            $taskRun->save();

            static::logDebug("Stored per-object plan for $objectType in TaskRun meta", [
                'task_run_id' => $taskRun->id,
            ]);
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Get all per-object plans from TaskRun.meta.
     *
     * @return array Array of per-object plans keyed by object type name
     */
    public function getPerObjectPlans(TaskRun $taskRun): array
    {
        return $taskRun->meta['per_object_plans'] ?? [];
    }

    /**
     * Compile all per-object plans into final extraction plan structure.
     *
     * Returns plan structure with levels, each containing identity groups and remaining groups.
     */
    public function compileFinalPlan(TaskRun $taskRun): array
    {
        $perObjectPlans = $this->getPerObjectPlans($taskRun);
        $schema         = $taskRun->taskDefinition->schemaDefinition?->schema ?? [];

        // Group plans by level
        $levelGroups = [];
        foreach ($perObjectPlans as $objectType => $planData) {
            $level = $planData['level'] ?? 0;
            if (!isset($levelGroups[$level])) {
                $levelGroups[$level] = [];
            }
            $levelGroups[$level][$objectType] = $planData;
        }

        // Sort levels
        ksort($levelGroups);

        $levels = [];
        foreach ($levelGroups as $level => $objectTypePlans) {
            $levelData = [
                'level'      => $level,
                'identities' => [],
                'remaining'  => [],
            ];

            foreach ($objectTypePlans as $objectType => $planData) {
                // Add identity group
                $identityGroup = $planData['identity_group'] ?? [];
                if (!empty($identityGroup['identity_fields'])) {
                    $fragmentSelector = $this->buildFragmentSelectorFromFields(
                        $planData['path']             ?? '',
                        $identityGroup['skim_fields'] ?? [],
                        $schema,
                        $planData['is_array']         ?? false
                    );

                    $levelData['identities'][] = [
                        'object_type'       => $objectType,
                        'identity_fields'   => $identityGroup['identity_fields'] ?? [],
                        'skim_fields'       => $identityGroup['skim_fields']     ?? [],
                        'search_mode'       => ($planData['is_array'] ?? false) ? 'exhaustive' : ($identityGroup['search_mode'] ?? 'skim'),
                        'description'       => $identityGroup['description']     ?? null,
                        'fragment_selector' => $fragmentSelector,
                    ];
                }

                // Add remaining groups
                $extractionGroups = $planData['extraction_groups'] ?? [];
                foreach ($extractionGroups as $group) {
                    $fragmentSelector = $this->buildFragmentSelectorFromFields(
                        $planData['path'] ?? '',
                        $group['fields']  ?? [],
                        $schema,
                        $planData['is_array'] ?? false
                    );

                    $levelData['remaining'][] = [
                        'name'              => $group['name']        ?? 'Unnamed Group',
                        'description'       => $group['description'] ?? null,
                        'fields'            => $group['fields']      ?? [],
                        'search_mode'       => ($planData['is_array'] ?? false) ? 'exhaustive' : ($group['search_mode'] ?? 'exhaustive'),
                        'object_type'       => $objectType,
                        'fragment_selector' => $fragmentSelector,
                    ];
                }
            }

            $levels[] = $levelData;
        }

        static::logDebug('Compiled final plan', [
            'levels_count' => count($levels),
        ]);

        return [
            'levels' => $levels,
        ];
    }

    /**
     * Build fragment selector from field list.
     *
     * Traverses the schema to determine correct types (array vs object) for each path part.
     *
     * @param  string  $objectPath  Dot-notation path to the object (e.g., "provider" or "provider.contacts")
     * @param  array  $fields  Field names to include in the fragment selector
     * @param  array  $schema  The JSON schema to determine types from
     * @param  bool  $leafIsArray  Whether the leaf object type is an array
     */
    protected function buildFragmentSelectorFromFields(string $objectPath, array $fields, array $schema, bool $leafIsArray): array
    {
        // Build the leaf fragment selector for the fields
        $fragmentSelector = [
            'type'     => $leafIsArray ? 'array' : 'object',
            'children' => [],
        ];

        foreach ($fields as $field) {
            $fragmentSelector['children'][$field] = [
                'type' => 'string', // Simplified - could be enhanced to track actual types
            ];
        }

        // If there's no object path, return the fragment selector as-is
        if (!$objectPath) {
            return $fragmentSelector;
        }

        // Build the path type map by traversing the schema
        $pathParts   = explode('.', $objectPath);
        $pathTypeMap = $this->buildPathTypeMap($pathParts, $schema);

        // Build nested structure from path (in reverse order)
        // Each path part needs its type from pathTypeMap explicitly set
        $current = $fragmentSelector;

        foreach (array_reverse($pathParts) as $pathPart) {
            // Get the type for this path part from the schema
            $pathPartType = $pathTypeMap[$pathPart] ?? 'object';

            // Wrap current children under this path part with its correct type
            $current = [
                'type'     => 'object', // Container type for the wrapper
                'children' => [
                    $pathPart => [
                        'type'     => $pathPartType,
                        'children' => $current['children'] ?? [],
                    ],
                ],
            ];
        }

        return $current;
    }

    /**
     * Build a map of path part names to their types by traversing the schema.
     *
     * @param  array  $pathParts  Array of path part names (e.g., ['provider', 'contacts'])
     * @param  array  $schema  The JSON schema
     * @return array Map of path part name to type (e.g., ['provider' => 'array', 'contacts' => 'object'])
     */
    protected function buildPathTypeMap(array $pathParts, array $schema): array
    {
        $typeMap        = [];
        $currentSchema  = $schema;

        foreach ($pathParts as $pathPart) {
            // Get the property definition from the current schema level
            $properties       = $currentSchema['properties'] ?? [];
            $propertySchema   = $properties[$pathPart]       ?? null;

            if (!$propertySchema) {
                // Property not found in schema, default to object
                $typeMap[$pathPart] = 'object';
                break;
            }

            // Determine the type
            $type = $propertySchema['type'] ?? 'object';

            // Handle union types (e.g., ['array', 'null'])
            if (is_array($type)) {
                $type = $this->getPrimaryType($type);
            }

            $typeMap[$pathPart] = $type;

            // Navigate to the next level of the schema
            if ($type === 'array') {
                // For arrays, the child schema is in 'items'
                $currentSchema = $propertySchema['items'] ?? [];
            } elseif ($type === 'object') {
                // For objects, continue with the property itself
                $currentSchema = $propertySchema;
            } else {
                // Scalar type - shouldn't happen for path parts, but handle gracefully
                break;
            }
        }

        return $typeMap;
    }

    /**
     * Get primary type from union type array.
     *
     * Filters out 'null' and returns the first non-null type.
     */
    protected function getPrimaryType(array $types): string
    {
        $filtered = array_filter($types, fn($t) => $t !== 'null');

        return reset($filtered) ?: 'object';
    }
}
