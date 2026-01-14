<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Str;
use Newms87\Danx\Helpers\LockHelper;

/**
 * Orchestrates creation and progression of extraction processes across hierarchical levels.
 * Manages level-by-level extraction with classification, object resolution, and data extraction phases.
 */
class ExtractionProcessOrchestrator
{
    use HasDebugLogging;

    /**
     * Get or initialize level progress from TaskRun.meta.
     */
    public function getLevelProgress(TaskRun $taskRun): array
    {
        $meta = $taskRun->meta ?? [];

        return $meta['level_progress'] ?? [];
    }

    /**
     * Get the parent output artifact for a task run.
     * Returns the most recently created parent artifact (no parent_artifact_id).
     */
    public function getParentOutputArtifact(TaskRun $taskRun): ?Artifact
    {
        return $taskRun->outputArtifacts()
            ->whereNull('parent_artifact_id')
            ->latest('id')
            ->first();
    }

    /**
     * Get all page artifacts (children of the parent output artifact) for a task run.
     * These are all page artifacts regardless of classification, used for context expansion.
     *
     * @return \Illuminate\Support\Collection<Artifact>
     */
    public function getAllPageArtifacts(TaskRun $taskRun): \Illuminate\Support\Collection
    {
        $parentArtifact = $this->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            return collect();
        }

        return $parentArtifact->children()->orderBy('position')->get();
    }

    /**
     * Check if all processes for a level are complete.
     */
    public function isLevelComplete(TaskRun $taskRun, int $level): bool
    {
        $levelProgress = $this->getLevelProgress($taskRun);
        $progress      = $levelProgress[$level] ?? [];

        $identityComplete   = $progress['identity_complete']   ?? false;
        $extractionComplete = $progress['extraction_complete'] ?? false;
        $isComplete         = $identityComplete && $extractionComplete;

        static::logDebug('Level completion check', [
            'level'      => $level,
            'identity'   => $identityComplete,
            'extraction' => $extractionComplete,
            'complete'   => $isComplete,
        ]);

        return $isComplete;
    }

    /**
     * Get current level from TaskRun.meta.
     */
    public function getCurrentLevel(TaskRun $taskRun): int
    {
        $meta = $taskRun->meta ?? [];

        return $meta['current_level'] ?? 0;
    }

    /**
     * Advance to next level if current level is complete.
     * Returns true if advanced to next level.
     */
    public function advanceToNextLevel(TaskRun $taskRun): bool
    {
        $currentLevel = $this->getCurrentLevel($taskRun);
        $plan         = $this->getExtractionPlan($taskRun);
        $maxLevel     = $this->getMaxLevel($plan);

        if ($currentLevel >= $maxLevel) {
            static::logDebug('Already at max level', ['current_level' => $currentLevel, 'max_level' => $maxLevel]);

            return false;
        }

        if (!$this->isLevelComplete($taskRun, $currentLevel)) {
            static::logDebug('Current level not complete', ['current_level' => $currentLevel]);

            return false;
        }

        $nextLevel = $currentLevel + 1;
        $meta      = $taskRun->meta ?? [];

        $meta['current_level'] = $nextLevel;
        $taskRun->meta         = $meta;
        $taskRun->save();

        static::logDebug('Advanced to next level', ['next_level' => $nextLevel]);

        return true;
    }

    /**
     * Create extract identity processes for a level.
     * Returns array of created TaskProcess instances.
     */
    public function createExtractIdentityProcesses(TaskRun $taskRun, array $plan, int $level): array
    {
        $levelData = $plan['levels'][$level] ?? null;
        $processes = [];

        if (!$levelData) {
            static::logDebug('No level data for identity processes', ['level' => $level]);

            return [];
        }

        // Get parent output artifact and its children (already classified)
        $parentArtifact = $this->getParentOutputArtifact($taskRun);
        if (!$parentArtifact) {
            static::logDebug('No parent output artifact found');

            return [];
        }

        $allChildren           = $parentArtifact->children;
        $classificationService = app(ClassificationExecutorService::class);

        $identities = $levelData['identities'] ?? [];

        foreach ($identities as $index => $identity) {
            // Filter children by this group's classification
            $groupKey       = Str::snake("{$identity['object_type']} Identification");
            $groupArtifacts = $classificationService->getArtifactsForCategory($allChildren, $groupKey);

            // Skip if no artifacts match this group's classification
            if ($groupArtifacts->isEmpty()) {
                static::logDebug('No classified artifacts for identity group, skipping', [
                    'level'     => $level,
                    'group'     => $identity['name'] ?? "Identity $index",
                    'group_key' => $groupKey,
                ]);

                continue;
            }

            // Resolve search mode with global override
            $resolvedSearchMode = $this->resolveSearchMode($taskRun, $identity);

            // Get expected parent type from fragment_selector to filter parent object IDs
            $fragmentSelector   = $identity['fragment_selector'] ?? [];
            $expectedParentType = app(FragmentSelectorService::class)->getParentType($fragmentSelector);

            $process = $taskRun->taskProcesses()->create([
                'name'      => $this->buildProcessName(
                    'Identity',
                    $level,
                    $identity['object_type'] ?? 'Unknown',
                    $identity['skim_fields'] ?? []
                ),
                'operation' => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
                'activity'  => sprintf('Extracting identity fields for %s', $identity['name'] ?? 'object'),
                'meta'      => [
                    'level'             => $level,
                    'identity_group'    => $identity,
                    'parent_object_ids' => $this->getParentObjectIds($taskRun, $level, $expectedParentType),
                    'search_mode'       => $resolvedSearchMode,
                ],
                'is_ready' => true,
            ]);

            // Attach filtered children as input artifacts
            $process->inputArtifacts()->attach($groupArtifacts->pluck('id')->toArray());
            $process->updateRelationCounter('inputArtifacts');

            $processes[] = $process;

            static::logDebug('Created Extract Identity process with input artifacts', [
                'level'                => $level,
                'group'                => $identity['name'] ?? "Identity $index",
                'process_id'           => $process->id,
                'input_artifacts'      => $groupArtifacts->count(),
                'expected_parent_type' => $expectedParentType,
                'parent_object_ids'    => $process->meta['parent_object_ids'] ?? [],
            ]);
        }

        return $processes;
    }

    /**
     * Create extract remaining processes for remaining (non-identification) groups at a level.
     * Returns array of created TaskProcess instances.
     */
    public function createExtractRemainingProcesses(TaskRun $taskRun, array $plan, int $level): array
    {
        static::logDebug('Creating extract remaining processes', [
            'level' => $level,
        ]);

        $levelData       = $plan['levels'][$level] ?? null;
        $resolvedObjects = $this->getResolvedObjectIds($taskRun);
        $processes       = [];

        if (!$levelData) {
            static::logDebug('Level not found in plan', ['level' => $level]);

            return [];
        }

        // Get parent output artifact and its children (already classified)
        // If no parent artifact exists, we still create processes but without input artifacts
        $parentArtifact        = $this->getParentOutputArtifact($taskRun);
        $allChildren           = $parentArtifact?->children()->get();
        $classificationService = app(ClassificationExecutorService::class);

        static::logDebug('Parent artifact status for extract remaining', [
            'has_parent'     => $parentArtifact !== null,
            'children_count' => $allChildren?->count() ?? 0,
        ]);

        // Get groups based on structure
        $groups = $this->getExtractableGroups($levelData);

        foreach ($groups as $groupIndex => $group) {
            // Get object IDs for this group from resolved objects
            $objectIds = $this->getObjectIdsForGroup($group, $resolvedObjects, $level);

            if (empty($objectIds)) {
                static::logDebug('No resolved objects for group, skipping', [
                    'level' => $level,
                    'group' => $group['name'] ?? "Group $groupIndex",
                ]);

                continue;
            }

            // Filter children by this group's classification key (only if we have children)
            $groupKey       = Str::snake($group['name']);
            $groupArtifacts = $allChildren
                ? $classificationService->getArtifactsForCategory($allChildren, $groupKey)
                : collect();

            // Skip if no artifacts match this group's classification
            if ($groupArtifacts->isEmpty()) {
                static::logDebug('No classified artifacts for remaining group, skipping process creation', [
                    'level'     => $level,
                    'group'     => $group['name'] ?? "Group $groupIndex",
                    'group_key' => $groupKey,
                ]);

                continue;
            }

            static::logDebug('Filtered artifacts for remaining group', [
                'level'           => $level,
                'group'           => $group['name'] ?? "Group $groupIndex",
                'group_key'       => $groupKey,
                'artifact_count'  => $groupArtifacts->count(),
            ]);

            // Resolve search mode with global override
            $resolvedSearchMode = $this->resolveSearchMode($taskRun, $group);

            // Create process for each resolved object
            foreach ($objectIds as $objectId) {
                $process = $taskRun->taskProcesses()->create([
                    'name'      => $this->buildProcessName(
                        'Remaining',
                        $level,
                        $group['object_type'] ?? 'Unknown',
                        $group['fields']      ?? []
                    ),
                    'operation' => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
                    'activity'  => sprintf('Extracting %s data', $group['name'] ?? 'group'),
                    'meta'      => [
                        'level'            => $level,
                        'operation'        => 'extract_remaining',
                        'extraction_group' => $group,
                        'object_id'        => $objectId,
                        'search_mode'      => $resolvedSearchMode,
                    ],
                    'is_ready'  => true,
                ]);

                // Attach filtered children as input artifacts
                $process->inputArtifacts()->attach($groupArtifacts->pluck('id')->toArray());
                $process->updateRelationCounter('inputArtifacts');

                $processes[] = $process;

                static::logDebug('Created extract remaining process with input artifacts', [
                    'level'           => $level,
                    'group'           => $group['name'] ?? "Group $groupIndex",
                    'group_key'       => $groupKey,
                    'object_id'       => $objectId,
                    'process_id'      => $process->id,
                    'input_artifacts' => $groupArtifacts->count(),
                ]);
            }
        }

        static::logDebug('Created extract remaining processes', [
            'level'           => $level,
            'processes_count' => count($processes),
        ]);

        return $processes;
    }

    /**
     * Check if identity extraction is complete for a level.
     */
    public function isIdentityCompleteForLevel(TaskRun $taskRun, int $level): bool
    {
        $identityProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->where('meta->level', $level)
            ->get();

        if ($identityProcesses->isEmpty()) {
            return true; // No identity processes for this level
        }

        return $identityProcesses->every(fn($p) => $p->completed_at !== null);
    }

    /**
     * Get parent object IDs for a level (from level - 1).
     * Optionally filter by expected parent type from fragment_selector.
     *
     * @param  ?string  $expectedParentType  Expected parent type (Title Case) from FragmentSelectorService::getParentType()
     */
    public function getParentObjectIds(TaskRun $taskRun, int $level, ?string $expectedParentType = null): array
    {
        if ($level === 0) {
            return [];
        }

        $resolvedObjects = $this->getResolvedObjectIds($taskRun);
        $parentLevel     = $level - 1;

        // Get object IDs from parent level
        $parentObjectIds = [];
        foreach ($resolvedObjects as $objectType => $levelData) {
            if (isset($levelData[$parentLevel])) {
                // If expected parent type is specified, only include matching types
                if ($expectedParentType !== null && $objectType !== $expectedParentType) {
                    continue;
                }

                $parentObjectIds = array_merge($parentObjectIds, $levelData[$parentLevel]);
            }
        }

        return array_unique($parentObjectIds);
    }

    /**
     * Store resolved object ID in TaskRun.meta.
     * Uses locking to prevent race conditions when multiple processes complete concurrently.
     */
    public function storeResolvedObjectId(TaskRun $taskRun, string $objectType, int $objectId, int $level): void
    {
        LockHelper::acquire($taskRun);

        try {
            $meta = $taskRun->meta ?? [];

            $meta['resolved_objects']                      ??= [];
            $meta['resolved_objects'][$objectType]         ??= [];
            $meta['resolved_objects'][$objectType][$level] ??= [];

            if (!in_array($objectId, $meta['resolved_objects'][$objectType][$level])) {
                $meta['resolved_objects'][$objectType][$level][] = $objectId;
            }

            $taskRun->meta = $meta;
            $taskRun->save();

            static::logDebug('Stored resolved object ID', [
                'object_type' => $objectType,
                'object_id'   => $objectId,
                'level'       => $level,
            ]);
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Get resolved object IDs from TaskRun.meta.
     */
    public function getResolvedObjectIds(TaskRun $taskRun): array
    {
        $meta = $taskRun->meta ?? [];

        return $meta['resolved_objects'] ?? [];
    }

    /**
     * Update level progress in TaskRun.meta.
     * Uses locking to prevent race conditions when multiple processes complete concurrently.
     */
    public function updateLevelProgress(TaskRun $taskRun, int $level, string $key, bool $value): void
    {
        LockHelper::acquire($taskRun);

        try {
            $meta = $taskRun->meta ?? [];

            $meta['level_progress']         ??= [];
            $meta['level_progress'][$level] ??= [];
            $meta['level_progress'][$level][$key] = $value;

            $taskRun->meta = $meta;
            $taskRun->save();

            static::logDebug('Updated level progress', [
                'level' => $level,
                'key'   => $key,
                'value' => $value,
            ]);
        } finally {
            LockHelper::release($taskRun);
        }
    }

    /**
     * Get the maximum level in the plan.
     */
    public function getMaxLevel(array $plan): int
    {
        $levels = $plan['levels'] ?? [];

        if (empty($levels)) {
            return 0;
        }

        return count($levels) - 1;
    }

    /**
     * Check if all levels are complete.
     */
    public function isAllLevelsComplete(TaskRun $taskRun, array $plan): bool
    {
        static::logDebug('Checking if all levels are complete');

        $maxLevel = $this->getMaxLevel($plan);

        for ($level = 0; $level <= $maxLevel; $level++) {
            if (!$this->isLevelComplete($taskRun, $level)) {
                static::logDebug('Not all levels complete', ['incomplete_level' => $level]);

                return false;
            }
        }

        static::logDebug('All levels complete', ['max_level' => $maxLevel]);

        return true;
    }

    /**
     * Get the extraction plan from TaskDefinition only.
     */
    public function getExtractionPlan(TaskRun $taskRun): array
    {
        return $taskRun->taskDefinition->meta['extraction_plan'] ?? [];
    }

    /**
     * Get object IDs for a specific group from resolved objects.
     * Handles new structure (object_type at group level).
     */
    protected function getObjectIdsForGroup(array $group, array $resolvedObjects, int $level): array
    {
        $objectIds = [];

        // New structure: object_type is directly on the group
        if (isset($group['object_type'])) {
            $objectType   = $group['object_type'];
            $levelObjects = $resolvedObjects[$objectType][$level] ?? [];
            $objectIds    = array_merge($objectIds, $levelObjects);

            return array_unique($objectIds);
        }

        return array_unique($objectIds);
    }

    /**
     * Get groups that should be extracted (non-identification groups).
     * Uses the new structure (remaining array).
     */
    protected function getExtractableGroups(array $levelData): array
    {
        // New structure: use remaining array directly
        if (isset($levelData['remaining'])) {
            return $levelData['remaining'];
        }

        return [];
    }

    /**
     * Resolve the search mode for a group, respecting global override.
     *
     * | global_search_mode | Per-group setting | Resolved mode |
     * |-------------------|-------------------|---------------|
     * | `intelligent`     | `skim`            | `skim`        |
     * | `intelligent`     | `exhaustive`      | `exhaustive`  |
     * | `skim_only`       | `skim`            | `skim`        |
     * | `skim_only`       | `exhaustive`      | `skim`        |
     * | `exhaustive_only` | `skim`            | `exhaustive`  |
     * | `exhaustive_only` | `exhaustive`      | `exhaustive`  |
     */
    public function resolveSearchMode(TaskRun $taskRun, array $group): string
    {
        $config     = $taskRun->taskDefinition->task_runner_config ?? [];
        $globalMode = $config['global_search_mode']                ?? 'intelligent';

        // Global overrides
        if ($globalMode === 'skim_only') {
            return 'skim';
        }

        if ($globalMode === 'exhaustive_only') {
            return 'exhaustive';
        }

        // Intelligent mode: use per-group setting from planning
        return $group['search_mode'] ?? 'skim';
    }

    /**
     * Build a descriptive process name with object type and fields.
     *
     * @param  string  $prefix  "Identity" or "Remaining"
     * @param  int  $level  The extraction level
     * @param  string  $objectType  The object type being extracted
     * @param  array  $fields  Field names being extracted (snake_case)
     * @param  int  $maxLength  Maximum name length (default 255)
     */
    protected function buildProcessName(string $prefix, int $level, string $objectType, array $fields, int $maxLength = 255): string
    {
        // Convert snake_case field names to Title Case
        $formattedFields = array_map(fn($field) => Str::title(str_replace('_', ' ', $field)), $fields);

        // Start building the name
        $baseName = sprintf('%s L%d: %s', $prefix, $level, $objectType);

        // If no fields, just return base name
        if (empty($formattedFields)) {
            return $baseName;
        }

        // Calculate available space for fields (account for " (" and ")")
        $availableLength = $maxLength - strlen($baseName) - 3; // 3 for " ()"

        // Build fields string, truncating with "..." if needed
        $fieldsStr = '';
        foreach ($formattedFields as $i => $field) {
            $separator       = $i > 0 ? ', ' : '';
            $potentialLength = strlen($fieldsStr) + strlen($separator) + strlen($field);

            if ($potentialLength > $availableLength - 3) { // -3 for "..."
                $fieldsStr .= '...';
                break;
            }

            $fieldsStr .= $separator . $field;
        }

        return sprintf('%s (%s)', $baseName, $fieldsStr);
    }
}
