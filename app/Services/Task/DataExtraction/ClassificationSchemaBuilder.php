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
     * Convert an identity item to a group structure for classification.
     */
    protected function identityToGroup(array $identity): array
    {
        $objectType = $identity['object_type'] ?? 'Unknown';

        return [
            'name'        => "{$objectType} Identification",
            'description' => $identity['description'] ?? "Pages relevant for identifying {$objectType} objects",
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
}
