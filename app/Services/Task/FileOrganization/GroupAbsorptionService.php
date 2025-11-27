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
     * Absorb groups based on relative confidence when they share conflict boundaries.
     *
     * This works based on RELATIVE confidence (not absolute thresholds), so even
     * high-confidence groups can be absorbed if they conflict with HIGHER-confidence groups.
     *
     * Strategy 1: Direct overlap - groups share the same file (windowing overlap)
     * Strategy 2: Conflict boundary - files grouped together follow the winner
     *
     * @param  array  $fileToGroup  Map from buildFileToGroupMapping
     * @param  array  $finalGroups  Groups from buildFinalGroups
     * @return array Map of losing_group => winning_group for absorptions to apply
     */
    public function identifyAbsorptions(array $fileToGroup, array $finalGroups): array
    {
        if (empty($finalGroups)) {
            return [];
        }

        static::logDebug('Checking for groups to absorb based on relative confidence');

        $analyzer = app(GroupConfidenceAnalyzer::class);

        $groupConfidenceSummary = $analyzer->calculateGroupConfidenceSummary($finalGroups, $fileToGroup);
        $lowConfidenceGroups    = $analyzer->identifyLowConfidenceGroups($groupConfidenceSummary);
        $mediumConfidenceGroups = $analyzer->identifyMediumConfidenceGroups($groupConfidenceSummary);
        $highConfidenceGroups   = $analyzer->identifyHighConfidenceGroups($groupConfidenceSummary);

        static::logDebug('Found ' . count($lowConfidenceGroups) . ' low-confidence groups, ' .
                        count($mediumConfidenceGroups) . ' medium-confidence groups, and ' .
                        count($highConfidenceGroups) . ' high-confidence groups');

        // Check for conflict boundary absorptions across ALL groups
        // (not limited to low/medium, because relative confidence matters)
        // Pass the group confidence summary so we can compare GROUP-level confidences
        return $this->findConflictBoundaryAbsorptions(array_keys($groupConfidenceSummary), $fileToGroup, $groupConfidenceSummary);
    }

    /**
     * Find absorptions based on conflict boundaries where a higher-confidence GROUP
     * won a conflict and the losing group has adjacent files from the same window.
     *
     * IMPORTANT: Compares GROUP-level confidence (max confidence in group), not individual file confidence.
     * This ensures we only absorb when the ENTIRE winning group is more confident than the ENTIRE losing group.
     *
     * This works BIDIRECTIONALLY:
     *
     * FORWARD absorption example:
     * - Window A: Pages 109-112 grouped as "Ivo DPT" (group conf 3)
     * - Window B: Page 109 gets reassigned to "ME PT" (group conf 5)
     * - Result: Absorb pages 110-112 from "Ivo DPT" into "ME PT" (forward from boundary)
     *
     * BACKWARD absorption example:
     * - Window A: Pages 93-95 in group "X" (group conf 4)
     * - Window B: Pages 95-97 in group "Y" (group conf 5)
     * - Page 95 gets absorbed into "Y"
     * - Result: Absorb pages 93-94 from "X" into "Y" (backward from boundary)
     *
     * @param  array  $allGroups  Array of ALL group names to check
     * @param  array  $fileToGroup  Map of file_id => group data
     * @param  array  $groupConfidenceSummary  Map of group_name => ['max' => int, ...]
     * @return array Map of losing_group => winning_group
     */
    protected function findConflictBoundaryAbsorptions(array $allGroups, array $fileToGroup, array $groupConfidenceSummary): array
    {
        $absorptions = [];

        // Find files where a higher-confidence GROUP assignment won
        foreach ($fileToGroup as $fileId => $data) {
            $winningGroup      = $data['group_name'];
            $allExplanations   = $data['all_explanations'] ?? [];

            // Get the GROUP-level confidence (max confidence in the winning group)
            $winningGroupConfidence = $groupConfidenceSummary[$winningGroup]['max'] ?? 3;

            // Check ALL alternative assignments this file had (both forward and backward)
            foreach ($allExplanations as $explanation) {
                $losingGroup      = $explanation['group_name'];
                $losingFileConf   = $explanation['confidence']; // The ACTUAL confidence this file had in the losing group
                $winningFileConf  = $data['confidence'];        // The ACTUAL confidence this file has in the winning group

                // Skip if the losing assignment is the same as current (not a conflict)
                if ($losingGroup === $winningGroup) {
                    continue;
                }

                // Skip if the losing group isn't in our group list
                if (!in_array($losingGroup, $allGroups)) {
                    continue;
                }

                // Get the GROUP-level confidence (max confidence in each group)
                $losingGroupConfidence = $groupConfidenceSummary[$losingGroup]['max'] ?? 3;

                // CRITICAL FIX: We need to check BOTH:
                // 1. Group-level confidence comparison (prevents absorbing high-conf groups into low-conf groups)
                // 2. File-level confidence at the boundary (handles cases where groups have equal max but different boundary conf)
                //
                // Example where file-level matters:
                // - ME PT group: max=5 (from other files)
                // - Mountain View group: max=5 (from other files)
                // - But at page 73 boundary: ME PT has conf 5, Mountain View has conf 4
                // - Group max is equal (5=5), but boundary shows ME PT won (5>4)
                // - So we SHOULD absorb Mountain View's window into ME PT
                //
                // Priority order:
                // 1. If winning group max > losing group max: Always absorb (higher confidence group wins)
                // 2. If winning group max == losing group max: Check file-level boundary confidence
                // 3. If winning group max < losing group max: Never absorb (would be wrong direction)

                $shouldAbsorb = false;

                if ($winningGroupConfidence > $losingGroupConfidence) {
                    // Case 1: Winning group has higher max confidence - always absorb
                    $shouldAbsorb = true;
                    static::logDebug("CONFLICT BOUNDARY DETECTED: File $fileId (page {$data['page_number']}) - '$winningGroup' (group max $winningGroupConfidence) > '$losingGroup' (group max $losingGroupConfidence)");
                } elseif ($winningGroupConfidence === $losingGroupConfidence && $winningFileConf > $losingFileConf) {
                    // Case 2: Groups have equal max, but winning file has higher confidence at boundary
                    $shouldAbsorb = true;
                    static::logDebug("CONFLICT BOUNDARY DETECTED: File $fileId (page {$data['page_number']}) - Groups have equal max ($winningGroupConfidence), but '$winningGroup' file conf ($winningFileConf) > '$losingGroup' file conf ($losingFileConf) at boundary");
                }

                if (!$shouldAbsorb) {
                    continue;
                }

                // Before adding absorption, check for circular conflict
                // If the winning group is already set to absorb into the losing group, this is a bidirectional conflict
                // Both groups have winning boundaries over each other, so they should remain separate
                if (isset($absorptions[$winningGroup]) && $absorptions[$winningGroup] === $losingGroup) {
                    // Circular absorption detected - both groups have winning boundaries
                    // Remove the existing absorption and don't add this one
                    unset($absorptions[$winningGroup]);
                    static::logDebug("  → CIRCULAR ABSORPTION DETECTED: '$losingGroup' ↔ '$winningGroup' - keeping both groups separate");

                    continue; // Skip to next explanation
                }

                // FORWARD absorption: Check if the losing group has adjacent files from the same window
                // (files that appear AFTER this boundary file in the losing group's window)
                if ($this->hasAdjacentFilesFromSameWindow($fileId, $losingGroup, $explanation['window_id'], $fileToGroup)) {
                    $absorptions[$losingGroup] = $winningGroup;
                    static::logDebug("  → Will absorb '$losingGroup' into '$winningGroup' (FORWARD: has adjacent files from same window)");
                }

                // BACKWARD absorption: Check if this file (now in winning group) was grouped
                // with other files in the LOSING group's window, and those files are still in the losing group
                // This handles cases where the boundary file got absorbed but should pull in earlier files too
                if ($this->shouldAbsorbBackward($fileId, $losingGroup, $explanation['window_id'], $winningGroup, $winningGroupConfidence, $fileToGroup)) {
                    $absorptions[$losingGroup] = $winningGroup;
                    static::logDebug("  → Will absorb '$losingGroup' into '$winningGroup' (BACKWARD: boundary file connects them)");
                }
            }
        }

        return $absorptions;
    }

    /**
     * Check if we should absorb a group backward through a boundary file.
     *
     * Example:
     * - Window A: Pages 93-95 in group "X" (conf 4)
     * - Window B: Pages 95-97 in group "Y", page 97 has conf 5
     * - Page 95 got absorbed into "Y" with conf 5
     * - Page 95 was also in "X" with 93-94 → absorb "X" into "Y"
     *
     * @param  int  $boundaryFileId  The file that won the conflict
     * @param  string  $losingGroup  The group that lost the conflict
     * @param  int  $windowId  The window where the losing assignment occurred
     * @param  string  $winningGroup  The current winning group
     * @param  int  $winningConfidence  The winning confidence level
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return bool True if we should absorb the losing group backward
     */
    protected function shouldAbsorbBackward(
        int $boundaryFileId,
        string $losingGroup,
        int $windowId,
        string $winningGroup,
        int $winningConfidence,
        array $fileToGroup
    ): bool {
        // Check if the losing group still exists (has files currently assigned to it)
        $losingGroupFiles = $this->getGroupFiles($losingGroup, $fileToGroup);

        if (empty($losingGroupFiles)) {
            // Losing group already absorbed, nothing to do
            return false;
        }

        // Check if any of those files were in the SAME window as the boundary file
        // (meaning they were grouped together with the boundary file in the losing group's window)
        foreach ($losingGroupFiles as $otherFileId => $otherData) {
            // Skip the boundary file itself
            if ($otherFileId === $boundaryFileId) {
                continue;
            }

            // Check if this file was assigned to the losing group in the same window as the boundary file
            $otherExplanations = $otherData['all_explanations'] ?? [];
            foreach ($otherExplanations as $exp) {
                if ($exp['group_name'] === $losingGroup && $exp['window_id'] === $windowId) {
                    // This file was grouped with the boundary file in the losing group's window
                    // and is currently still in the losing group → should absorb
                    static::logDebug("    Found backward absorption candidate: file $otherFileId (page {$otherData['page_number']}) was in '$losingGroup' with boundary file in window $windowId");

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all files currently assigned to a specific group.
     *
     * @param  string  $groupName  Group name
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Map of file_id => file data for files in this group
     */
    protected function getGroupFiles(string $groupName, array $fileToGroup): array
    {
        $groupFiles = [];

        foreach ($fileToGroup as $fileId => $data) {
            if ($data['group_name'] === $groupName) {
                $groupFiles[$fileId] = $data;
            }
        }

        return $groupFiles;
    }

    /**
     * Check if a group has files adjacent to a given file that were grouped together
     * in the SAME WINDOW and have no competing assignments.
     *
     * This ensures we only absorb files that were part of the same original grouping
     * that lost the conflict, not just any adjacent files.
     *
     * @param  int  $fileId  The boundary file ID
     * @param  string  $groupName  Group name to check for adjacent files
     * @param  int  $windowId  The window ID where the conflict occurred
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return bool True if group has uncontested adjacent files from the same window
     */
    protected function hasAdjacentFilesFromSameWindow(int $fileId, string $groupName, int $windowId, array $fileToGroup): bool
    {
        $boundaryPageNumber = $fileToGroup[$fileId]['page_number'];

        foreach ($fileToGroup as $otherFileId => $data) {
            // Skip the boundary file itself
            if ($otherFileId === $fileId) {
                continue;
            }

            if ($data['group_name'] === $groupName) {
                $pageNumber = $data['page_number'];
                // Check if this file is adjacent (within 3 pages to allow for small gaps)
                if (abs($pageNumber - $boundaryPageNumber) <= 3) {
                    // Check if this file had multiple group assignments
                    $allExplanations = $data['all_explanations'] ?? [];
                    $uniqueGroups    = array_unique(array_column($allExplanations, 'group_name'));

                    // Only absorb if this file ONLY appeared in this one group (no conflicts of its own)
                    if (count($uniqueGroups) === 1) {
                        // Check if this file was assigned to this group in the SAME window as the conflict
                        $wasInSameWindow = false;
                        foreach ($allExplanations as $exp) {
                            if ($exp['group_name'] === $groupName && $exp['window_id'] === $windowId) {
                                $wasInSameWindow = true;
                                break;
                            }
                        }

                        if ($wasInSameWindow) {
                            static::logDebug("    Found adjacent file in '$groupName' from same window: file $otherFileId (page $pageNumber) near boundary page $boundaryPageNumber (window $windowId)");

                            return true;
                        } else {
                            static::logDebug("    Skipping adjacent file $otherFileId (page $pageNumber) - not from same window (window $windowId)");
                        }
                    } else {
                        static::logDebug("    Skipping adjacent file $otherFileId (page $pageNumber) - had multiple assignments: " . implode(', ', $uniqueGroups));
                    }
                }
            }
        }

        return false;
    }

    /**
     * Apply absorptions by reassigning all files from low-confidence groups to high-confidence groups.
     * Absorbed files inherit the winning group's confidence level to enable cascade absorption.
     *
     * @param  array  $absorptions  Map of low_group => high_group (or low_group => ['group' => high_group, 'confidence' => int])
     * @param  array  $fileToGroup  Map of file_id => group data (will be modified)
     * @return int Number of files absorbed
     */
    public function applyAbsorptions(array $absorptions, array &$fileToGroup): int
    {
        $totalFilesAbsorbed = 0;

        foreach ($absorptions as $lowGroup => $absorptionData) {
            // Support both old format (string) and new format (array with confidence)
            if (is_string($absorptionData)) {
                $highGroup         = $absorptionData;
                $inheritConfidence = null; // Will calculate from group max
            } else {
                $highGroup         = $absorptionData['group'];
                $inheritConfidence = $absorptionData['confidence'];
            }

            // Calculate the confidence to inherit if not provided
            if ($inheritConfidence === null) {
                $inheritConfidence = $this->calculateGroupMaxConfidence($highGroup, $fileToGroup);
            }

            static::logDebug("Absorbing group '$lowGroup' into '$highGroup' (inherit confidence: $inheritConfidence)");

            $filesAbsorbed = 0;
            foreach ($fileToGroup as $fileId => $data) {
                if ($data['group_name'] === $lowGroup) {
                    $oldConfidence                      = $data['confidence'];
                    $fileToGroup[$fileId]['group_name'] = $highGroup;
                    $fileToGroup[$fileId]['confidence'] = $inheritConfidence;
                    $filesAbsorbed++;
                    static::logDebug("  Reassigned file $fileId (page {$data['page_number']}) from '$lowGroup' (conf $oldConfidence) to '$highGroup' (conf $inheritConfidence)");
                }
            }

            static::logDebug("Absorbed $filesAbsorbed files from '$lowGroup' into '$highGroup'");
            $totalFilesAbsorbed += $filesAbsorbed;
        }

        return $totalFilesAbsorbed;
    }

    /**
     * Calculate the maximum confidence level in a group.
     *
     * @param  string  $groupName  Group name
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return int Maximum confidence in the group
     */
    protected function calculateGroupMaxConfidence(string $groupName, array $fileToGroup): int
    {
        $maxConfidence = 0;

        foreach ($fileToGroup as $data) {
            if ($data['group_name'] === $groupName) {
                $maxConfidence = max($maxConfidence, $data['confidence']);
            }
        }

        return $maxConfidence ?: 3; // Default to 3 if group has no files
    }
}
