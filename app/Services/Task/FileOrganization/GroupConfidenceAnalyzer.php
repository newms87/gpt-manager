<?php

namespace App\Services\Task\FileOrganization;

use App\Traits\HasDebugLogging;

/**
 * Analyzes group confidence levels and handles absorption of low-confidence groups.
 */
class GroupConfidenceAnalyzer
{
    use HasDebugLogging;

    /**
     * Calculate confidence summary for each group.
     *
     * @param  array  $finalGroups  Array of groups with file IDs
     * @param  array  $fileToGroup  Map of file_id => group data with confidence
     * @return array Map of group_name => ['avg' => float, 'min' => int, 'max' => int, 'all' => array]
     */
    public function calculateGroupConfidenceSummary(array $finalGroups, array $fileToGroup): array
    {
        $summary = [];

        foreach ($finalGroups as $group) {
            $groupName = $group['name'];
            $fileIds   = $group['files'];

            $confidences = $this->getFileConfidences($fileIds, $fileToGroup);

            if (empty($confidences)) {
                continue;
            }

            $summary[$groupName] = [
                'avg' => array_sum($confidences) / count($confidences),
                'min' => min($confidences),
                'max' => max($confidences),
                'all' => $confidences,
            ];

            static::logDebug("Group '$groupName': avg={$summary[$groupName]['avg']}, min={$summary[$groupName]['min']}, max={$summary[$groupName]['max']}");
        }

        return $summary;
    }

    /**
     * Identify low-confidence groups (ALL files < 3).
     *
     * @param  array  $groupConfidenceSummary  Summary from calculateGroupConfidenceSummary
     * @return array Array of group names
     */
    public function identifyLowConfidenceGroups(array $groupConfidenceSummary): array
    {
        $lowConfidenceGroups = [];

        foreach ($groupConfidenceSummary as $groupName => $summary) {
            if ($summary['max'] < 3) {
                $lowConfidenceGroups[] = $groupName;
                static::logDebug("Identified LOW confidence group: '$groupName' (max={$summary['max']})");
            }
        }

        return $lowConfidenceGroups;
    }

    /**
     * Identify medium-confidence groups (max = 3, not low enough for "low" category).
     * These groups are candidates for absorption into high-confidence groups when
     * there are conflict boundaries or adjacency.
     *
     * @param  array  $groupConfidenceSummary  Summary from calculateGroupConfidenceSummary
     * @return array Array of group names
     */
    public function identifyMediumConfidenceGroups(array $groupConfidenceSummary): array
    {
        $mediumConfidenceGroups = [];

        foreach ($groupConfidenceSummary as $groupName => $summary) {
            // Medium: max is exactly 3 (not low enough for < 3, not high enough for >= 4)
            if ($summary['max'] === 3) {
                $mediumConfidenceGroups[] = $groupName;
                static::logDebug("Identified MEDIUM confidence group: '$groupName' (max={$summary['max']})");
            }
        }

        return $mediumConfidenceGroups;
    }

    /**
     * Identify high-confidence groups (ALL files >= 4).
     *
     * @param  array  $groupConfidenceSummary  Summary from calculateGroupConfidenceSummary
     * @return array Array of group names
     */
    public function identifyHighConfidenceGroups(array $groupConfidenceSummary): array
    {
        $highConfidenceGroups = [];

        foreach ($groupConfidenceSummary as $groupName => $summary) {
            if ($summary['min'] >= 4) {
                $highConfidenceGroups[] = $groupName;
                static::logDebug("Identified HIGH confidence group: '$groupName' (min={$summary['min']})");
            }
        }

        return $highConfidenceGroups;
    }

    /**
     * Find overlapping files between two groups.
     *
     * @param  string  $group1Name  First group name
     * @param  string  $group2Name  Second group name
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Array of file IDs that appear in both groups
     */
    public function findOverlappingFiles(string $group1Name, string $group2Name, array $fileToGroup): array
    {
        $group1Files = $this->getGroupFileIds($group1Name, $fileToGroup);
        $group2Files = $this->getGroupFileIds($group2Name, $fileToGroup);

        return array_intersect($group1Files, $group2Files);
    }

    /**
     * Get all file IDs for a specific group.
     *
     * @param  string  $groupName  Group name
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Array of file IDs
     */
    public function getGroupFileIds(string $groupName, array $fileToGroup): array
    {
        $fileIds = [];

        foreach ($fileToGroup as $fileId => $data) {
            if ($data['group_name'] === $groupName) {
                $fileIds[] = $fileId;
            }
        }

        return $fileIds;
    }

    /**
     * Get confidence scores for a list of file IDs.
     *
     * @param  array  $fileIds  Array of file IDs
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Array of confidence scores
     */
    protected function getFileConfidences(array $fileIds, array $fileToGroup): array
    {
        $confidences = [];

        foreach ($fileIds as $fileId) {
            if (isset($fileToGroup[$fileId])) {
                $confidences[] = $fileToGroup[$fileId]['confidence'];
            }
        }

        return $confidences;
    }
}
