<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;

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
     * Check if all processes for a level are complete.
     */
    public function isLevelComplete(TaskRun $taskRun, int $level): bool
    {
        $levelProgress = $this->getLevelProgress($taskRun);
        $progress      = $levelProgress[$level] ?? [];

        $resolutionComplete = $progress['resolution_complete'] ?? false;
        $extractionComplete = $progress['extraction_complete'] ?? false;
        $isComplete         = $resolutionComplete && $extractionComplete;

        static::logDebug('Level completion check', [
            'level'      => $level,
            'resolution' => $resolutionComplete,
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
        $plan         = $this->getPlan($taskRun);
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
     * Create classification process (one-time for all levels).
     */
    public function createClassificationProcess(TaskRun $taskRun, array $plan): TaskProcess
    {
        $process = $taskRun->taskProcesses()->create([
            'name'      => 'Classify Pages',
            'operation' => ExtractDataTaskRunner::OPERATION_CLASSIFY,
            'activity'  => 'Classifying pages into extraction groups across all levels',
            'meta'      => [
                'operation' => 'classify',
            ],
            'is_ready'  => true,
        ]);

        static::logDebug('Created classification process', ['process_id' => $process->id]);

        return $process;
    }

    /**
     * Create classification processes per page.
     * Each page gets its own TaskProcess for parallel classification.
     *
     * @param  TaskRun  $taskRun  The task run to create processes for
     * @param  array  $pages  Array of page data (each with: artifact_id, file_id, page_number)
     * @return array Array of created TaskProcess instances
     */
    public function createClassifyProcessesPerPage(TaskRun $taskRun, array $pages): array
    {
        static::logDebug('Creating classify processes per page', [
            'task_run_id' => $taskRun->id,
            'pages_count' => count($pages),
        ]);

        $processes = [];

        foreach ($pages as $page) {
            $pageNumber = $page['page_number'] ?? null;

            if ($pageNumber === null) {
                static::logDebug('Skipping page without page_number', ['page' => $page]);

                continue;
            }

            $process = $taskRun->taskProcesses()->create([
                'name'      => "Classify Page $pageNumber",
                'operation' => ExtractDataTaskRunner::OPERATION_CLASSIFY,
                'activity'  => "Classifying page $pageNumber",
                'meta'      => [
                    'artifact_id' => $page['artifact_id'] ?? null,
                    'file_id'     => $page['file_id']     ?? null,
                    'page_number' => $pageNumber,
                ],
                'is_ready'  => true,
            ]);

            $processes[] = $process;

            static::logDebug('Created classify process for page', [
                'process_id'  => $process->id,
                'page_number' => $pageNumber,
                'artifact_id' => $page['artifact_id'] ?? null,
                'file_id'     => $page['file_id']     ?? null,
            ]);
        }

        static::logDebug('Created classify processes per page', [
            'processes_count' => count($processes),
        ]);

        return $processes;
    }

    /**
     * Check if classification is complete (all classify processes finished).
     *
     * @param  TaskRun  $taskRun  The task run to check
     * @return bool True if all classify processes are complete, false otherwise
     */
    public function isClassificationComplete(TaskRun $taskRun): bool
    {
        $classifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->get();

        $totalProcesses     = $classifyProcesses->count();
        $completedProcesses = $classifyProcesses->whereNotNull('completed_at')->count();

        static::logDebug('Checking classification completion', [
            'task_run_id'         => $taskRun->id,
            'total_processes'     => $totalProcesses,
            'completed_processes' => $completedProcesses,
        ]);

        // If no classify processes exist, classification is not complete
        if ($totalProcesses === 0) {
            static::logDebug('No classify processes found, classification not complete');

            return false;
        }

        $isComplete = $completedProcesses === $totalProcesses;

        static::logDebug('Classification completion result', [
            'is_complete' => $isComplete,
        ]);

        return $isComplete;
    }

    /**
     * Create resolve objects process for a level.
     */
    public function createResolveObjectsProcess(TaskRun $taskRun, array $plan, int $level): TaskProcess
    {
        $levelData       = $plan['levels'][$level] ?? null;
        $parentObjectIds = $this->getParentObjectIds($taskRun, $level);

        if (!$levelData) {
            throw new \InvalidArgumentException("Level $level not found in extraction plan");
        }

        $process = $taskRun->taskProcesses()->create([
            'name'      => "Resolve Objects - Level $level",
            'operation' => ExtractDataTaskRunner::OPERATION_RESOLVE_OBJECTS,
            'activity'  => "Resolving object identities for level $level",
            'meta'      => [
                'level'             => $level,
                'operation'         => 'resolve_objects',
                'parent_object_ids' => $parentObjectIds,
            ],
            'is_ready'  => true,
        ]);

        static::logDebug("Created resolve objects process for level $level", [
            'process_id'         => $process->id,
            'parent_object_ids'  => $parentObjectIds,
        ]);

        return $process;
    }

    /**
     * Create extract group processes for remaining (non-identification) groups at a level.
     * Returns array of created TaskProcess instances.
     */
    public function createExtractGroupProcesses(TaskRun $taskRun, array $plan, int $level): array
    {
        static::logDebug('Creating extract group processes', [
            'level' => $level,
        ]);

        $levelData       = $plan['levels'][$level] ?? null;
        $resolvedObjects = $this->getResolvedObjectIds($taskRun);
        $processes       = [];

        if (!$levelData) {
            static::logDebug('Level not found in plan', ['level' => $level]);

            return [];
        }

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

            // Create process for each resolved object
            foreach ($objectIds as $objectId) {
                $parentObjectId = $this->getParentObjectIdForLevel($taskRun, $level);

                $process = $taskRun->taskProcesses()->create([
                    'name'      => sprintf(
                        'Extract %s - Level %d - Object %d',
                        $group['name'] ?? "Group $groupIndex",
                        $level,
                        $objectId
                    ),
                    'operation' => ExtractDataTaskRunner::OPERATION_EXTRACT_GROUP,
                    'activity'  => sprintf('Extracting %s data', $group['name'] ?? 'group'),
                    'meta'      => [
                        'level'             => $level,
                        'operation'         => 'extract_group',
                        'extraction_group'  => $group,
                        'object_id'         => $objectId,
                        'parent_object_id'  => $parentObjectId,
                        'search_mode'       => $group['search_mode'] ?? 'exhaustive',
                    ],
                    'is_ready'  => true,
                ]);

                $processes[] = $process;

                static::logDebug('Created extract group process', [
                    'level'      => $level,
                    'group'      => $group['name'] ?? "Group $groupIndex",
                    'object_id'  => $objectId,
                    'process_id' => $process->id,
                ]);
            }
        }

        static::logDebug('Created extract group processes', [
            'level'           => $level,
            'processes_count' => count($processes),
        ]);

        return $processes;
    }

    /**
     * Get all groups at a specific level from the plan.
     * Returns combined identities and remaining groups.
     */
    public function getGroupsAtLevel(array $plan, int $level): array
    {
        $levelData = $plan['levels'][$level] ?? null;

        if (!$levelData) {
            return [];
        }

        // New structure: combine identities and remaining
        if (isset($levelData['identities']) || isset($levelData['remaining'])) {
            $identities = $levelData['identities'] ?? [];
            $remaining  = $levelData['remaining']  ?? [];

            return array_merge($identities, $remaining);
        }

        // Legacy structure fallback
        return $levelData['groups'] ?? [];
    }

    /**
     * Get parent object IDs for a level (from level - 1).
     */
    public function getParentObjectIds(TaskRun $taskRun, int $level): array
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
                $parentObjectIds = array_merge($parentObjectIds, $levelData[$parentLevel]);
            }
        }

        return array_unique($parentObjectIds);
    }

    /**
     * Store resolved object ID in TaskRun.meta.
     */
    public function storeResolvedObjectId(TaskRun $taskRun, string $objectType, int $objectId, int $level): void
    {
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
     */
    public function updateLevelProgress(TaskRun $taskRun, int $level, string $key, bool $value): void
    {
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
    protected function getPlan(TaskRun $taskRun): array
    {
        // Check for cached plan in TaskDefinition.meta
        $taskDefinition = $taskRun->taskDefinition;
        $cachedPlan     = $taskDefinition->meta['extraction_plan'] ?? null;

        return $cachedPlan ?? [];
    }

    /**
     * Check if a group is an identification group.
     * In new structure, identities have identity_fields instead of objects.
     * In legacy structure, check for is_identification flag in objects.
     */
    protected function isIdentificationGroup(array $group): bool
    {
        // New structure: identity groups have identity_fields
        if (isset($group['identity_fields'])) {
            return true;
        }

        // Legacy structure: check objects for is_identification flag
        foreach ($group['objects'] ?? [] as $object) {
            if ($object['is_identification'] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get object IDs for a specific group from resolved objects.
     * Handles both new structure (object_type at group level) and legacy structure (objects array).
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

        // Legacy structure: objects array with object_type for each
        foreach ($group['objects'] ?? [] as $object) {
            $objectType = $object['object_type'] ?? null;

            if (!$objectType) {
                continue;
            }

            $levelObjects = $resolvedObjects[$objectType][$level] ?? [];
            $objectIds    = array_merge($objectIds, $levelObjects);
        }

        return array_unique($objectIds);
    }

    /**
     * Get parent object ID for current level (first parent from previous level).
     */
    protected function getParentObjectIdForLevel(TaskRun $taskRun, int $level): ?int
    {
        $parentObjectIds = $this->getParentObjectIds($taskRun, $level);

        return $parentObjectIds[0] ?? null;
    }

    /**
     * Get groups that should be extracted (non-identification groups).
     * Handles both new structure (remaining array) and legacy structure (groups with is_identification).
     */
    protected function getExtractableGroups(array $levelData): array
    {
        // New structure: use remaining array directly
        if (isset($levelData['remaining'])) {
            return $levelData['remaining'];
        }

        // Legacy structure: filter groups by is_identification
        $allGroups         = $levelData['groups'] ?? [];
        $extractableGroups = [];

        foreach ($allGroups as $group) {
            if (!$this->isIdentificationGroup($group)) {
                $extractableGroups[] = $group;
            }
        }

        return $extractableGroups;
    }
}
