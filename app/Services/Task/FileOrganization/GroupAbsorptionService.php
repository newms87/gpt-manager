<?php

namespace App\Services\Task\FileOrganization;

use App\Traits\HasDebugLogging;

/**
 * Handles absorption of low-confidence groups into high-confidence groups when they overlap.
 */
class GroupAbsorptionService
{
    use HasDebugLogging;

    /**
     * Absorb low-confidence groups into high-confidence groups when they share overlapping pages.
     *
     * @param  array  $fileToGroup  Map from buildFileToGroupMapping
     * @param  array  $finalGroups  Groups from buildFinalGroups
     * @return array Map of low_group => high_group for absorptions to apply
     */
    public function identifyAbsorptions(array $fileToGroup, array $finalGroups): array
    {
        if (empty($finalGroups)) {
            return [];
        }

        static::logDebug('Checking for low-confidence groups to absorb into high-confidence groups');

        $analyzer = app(GroupConfidenceAnalyzer::class);

        $groupConfidenceSummary = $analyzer->calculateGroupConfidenceSummary($finalGroups, $fileToGroup);
        $lowConfidenceGroups    = $analyzer->identifyLowConfidenceGroups($groupConfidenceSummary);
        $highConfidenceGroups   = $analyzer->identifyHighConfidenceGroups($groupConfidenceSummary);

        if (empty($lowConfidenceGroups) || empty($highConfidenceGroups)) {
            static::logDebug('No absorption needed - no low-confidence or high-confidence groups found');

            return [];
        }

        static::logDebug('Found ' . count($lowConfidenceGroups) . ' low-confidence groups and ' . count($highConfidenceGroups) . ' high-confidence groups');

        return $this->findAbsorptionPairs($lowConfidenceGroups, $highConfidenceGroups, $fileToGroup);
    }

    /**
     * Find which low-confidence groups should be absorbed into which high-confidence groups.
     *
     * @param  array  $lowConfidenceGroups  Array of low-confidence group names
     * @param  array  $highConfidenceGroups  Array of high-confidence group names
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Map of low_group => high_group
     */
    protected function findAbsorptionPairs(array $lowConfidenceGroups, array $highConfidenceGroups, array $fileToGroup): array
    {
        $absorptions = [];
        $analyzer    = app(GroupConfidenceAnalyzer::class);

        foreach ($lowConfidenceGroups as $lowGroup) {
            foreach ($highConfidenceGroups as $highGroup) {
                $overlap = $analyzer->findOverlappingFiles($lowGroup, $highGroup, $fileToGroup);

                if (!empty($overlap)) {
                    static::logDebug("OVERLAP DETECTED: Low-confidence group '$lowGroup' shares " . count($overlap) . " file(s) with high-confidence group '$highGroup'");

                    foreach ($overlap as $fileId) {
                        $pageNumber = $fileToGroup[$fileId]['page_number'] ?? '?';
                        static::logDebug("  Overlapping file: $fileId (page $pageNumber)");
                    }

                    $absorptions[$lowGroup] = $highGroup;
                    static::logDebug("  → Will absorb ALL files from '$lowGroup' into '$highGroup'");
                    break; // Only need one overlap to trigger absorption
                }
            }
        }

        if (empty($absorptions)) {
            static::logDebug('No overlaps found - no absorption needed');
        }

        return $absorptions;
    }

    /**
     * Apply absorptions by reassigning all files from low-confidence groups to high-confidence groups.
     *
     * @param  array  $absorptions  Map of low_group => high_group
     * @param  array  $fileToGroup  Map of file_id => group data (will be modified)
     * @return int Number of files absorbed
     */
    public function applyAbsorptions(array $absorptions, array &$fileToGroup): int
    {
        $totalFilesAbsorbed = 0;

        foreach ($absorptions as $lowGroup => $highGroup) {
            static::logDebug("Absorbing group '$lowGroup' into '$highGroup'");

            $filesAbsorbed = 0;
            foreach ($fileToGroup as $fileId => $data) {
                if ($data['group_name'] === $lowGroup) {
                    $fileToGroup[$fileId]['group_name'] = $highGroup;
                    $filesAbsorbed++;
                    static::logDebug("  Reassigned file $fileId (page {$data['page_number']}) from '$lowGroup' to '$highGroup'");
                }
            }

            static::logDebug("Absorbed $filesAbsorbed files from '$lowGroup' into '$highGroup'");
            $totalFilesAbsorbed += $filesAbsorbed;
        }

        return $totalFilesAbsorbed;
    }
}
