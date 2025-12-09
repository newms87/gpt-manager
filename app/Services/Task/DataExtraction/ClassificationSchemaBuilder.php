<?php

namespace App\Services\Task\DataExtraction;

use App\Traits\HasDebugLogging;
use Illuminate\Support\Str;

/**
 * Converts extraction plan groups into classification schemas for the ClassifierTaskRunner.
 * Each extraction group becomes a classification category so pages can be classified
 * by their relevance to each group.
 */
class ClassificationSchemaBuilder
{
    use HasDebugLogging;

    /**
     * Build classification schema from extraction plan groups for a specific level.
     * Used when classifying pages for a single extraction level.
     */
    public function buildSchemaFromGroups(array $plan, int $level): array
    {
        static::logDebug('Building classification schema from groups', ['level' => $level]);

        $groups = $this->getGroupsAtLevel($plan, $level);

        if (empty($groups)) {
            static::logDebug('No groups found at level', ['level' => $level]);

            return $this->buildEmptySchema();
        }

        $categories = [];

        foreach ($groups as $group) {
            $categoryKey              = $this->generateCategoryKey($group['name'] ?? 'unnamed_group');
            $categories[$categoryKey] = $this->groupToCategory($group);
        }

        static::logDebug('Built classification schema', ['categories_count' => count($categories)]);

        return [
            'categories'      => $categories,
            'allow_multiple'  => true,  // Pages can match multiple extraction groups
            'include_exclude' => true,  // Include __exclude category for irrelevant pages
        ];
    }

    /**
     * Build classification schema from all groups in the plan.
     * Used for initial classification across all extraction levels.
     */
    public function buildSchemaFromAllGroups(array $plan): array
    {
        static::logDebug('Building classification schema from all groups');

        $allGroups = $this->getAllGroups($plan);

        if (empty($allGroups)) {
            static::logDebug('No groups found in plan');

            return $this->buildEmptySchema();
        }

        $categories = [];

        foreach ($allGroups as $group) {
            $categoryKey              = $this->generateCategoryKey($group['name'] ?? 'unnamed_group');
            $categories[$categoryKey] = $this->groupToCategory($group);
        }

        static::logDebug('Built schema from all groups', [
            'categories_count' => count($categories),
            'groups_count'     => count($allGroups),
        ]);

        return [
            'categories'      => $categories,
            'allow_multiple'  => true,  // Pages can match multiple extraction groups
            'include_exclude' => true,  // Include __exclude category for irrelevant pages
        ];
    }

    /**
     * Convert a single group to a classification category.
     */
    protected function groupToCategory(array $group): array
    {
        return [
            'name'        => $group['name']        ?? 'Unnamed Group',
            'description' => $group['description'] ?? '',
        ];
    }

    /**
     * Generate unique category key from group name.
     * Converts "Medical Diagnoses and Complaints" to "medical_diagnoses_and_complaints".
     */
    protected function generateCategoryKey(string $groupName): string
    {
        return Str::snake($groupName);
    }

    /**
     * Get all groups from all levels in the plan.
     * Combines identities and remaining arrays from each level.
     */
    protected function getAllGroups(array $plan): array
    {
        $allGroups = [];

        foreach ($plan['levels'] ?? [] as $level) {
            // Get identity groups
            foreach ($level['identities'] ?? [] as $identity) {
                $allGroups[] = $this->identityToGroup($identity);
            }

            // Get remaining extraction groups
            foreach ($level['remaining'] ?? [] as $group) {
                $allGroups[] = $group;
            }
        }

        return $allGroups;
    }

    /**
     * Get groups at a specific level from the plan.
     * Combines identities and remaining arrays.
     */
    protected function getGroupsAtLevel(array $plan, int $level): array
    {
        $levelData = null;

        foreach ($plan['levels'] ?? [] as $levelInfo) {
            if (($levelInfo['level'] ?? null) === $level) {
                $levelData = $levelInfo;
                break;
            }
        }

        if (!$levelData) {
            return [];
        }

        $groups = [];

        // Get identity groups
        foreach ($levelData['identities'] ?? [] as $identity) {
            $groups[] = $this->identityToGroup($identity);
        }

        // Get remaining extraction groups
        foreach ($levelData['remaining'] ?? [] as $group) {
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Convert an identity item to a group structure for classification.
     */
    protected function identityToGroup(array $identity): array
    {
        $objectType = $identity['object_type'] ?? 'Unknown';

        return [
            'name'        => "{$objectType} Identification",
            'description' => "Pages relevant for identifying {$objectType} objects",
        ];
    }

    /**
     * Build boolean classification schema from all groups in the plan.
     * Returns a JSON schema with boolean properties for each group,
     * compatible with withResponseFormat() structured output.
     *
     * @param  array  $plan  The extraction plan containing groups
     * @return array JSON schema structure with boolean properties
     */
    public function buildBooleanSchema(array $plan): array
    {
        static::logDebug('Building boolean classification schema');

        $allGroups = $this->getAllGroups($plan);

        if (empty($allGroups)) {
            static::logDebug('No groups found in plan for boolean schema');

            return [
                'type'       => 'object',
                'properties' => [],
                'required'   => [],
            ];
        }

        $properties = [];
        $required   = [];

        foreach ($allGroups as $group) {
            $categoryKey = $this->generateCategoryKey($group['name'] ?? 'unnamed_group');
            $description = $group['description'] ?? '';

            $properties[$categoryKey] = [
                'type'        => 'boolean',
                'description' => $description,
            ];

            $required[] = $categoryKey;
        }

        static::logDebug('Built boolean schema', [
            'properties_count' => count($properties),
            'groups_count'     => count($allGroups),
        ]);

        return [
            'type'       => 'object',
            'properties' => $properties,
            'required'   => $required,
        ];
    }

    /**
     * Build an empty schema with just the __exclude category.
     */
    protected function buildEmptySchema(): array
    {
        return [
            'categories'      => [],
            'allow_multiple'  => true,
            'include_exclude' => true,
        ];
    }
}
