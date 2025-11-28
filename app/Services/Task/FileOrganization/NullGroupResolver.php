<?php

namespace App\Services\Task\FileOrganization;

use App\Traits\HasDebugLogging;

/**
 * Resolves null groups (empty string names) by assigning to adjacent non-null groups.
 */
class NullGroupResolver
{
    use HasDebugLogging;

    /**
     * Resolve null groups by auto-assignment or flagging for LLM resolution.
     *
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array ['auto_assignments' => [...], 'needs_llm_resolution' => [...]]
     */
    public function resolveNullGroups(array $fileToGroup): array
    {
        $nullGroupFiles = $this->identifyNullGroupFiles($fileToGroup);

        if (empty($nullGroupFiles)) {
            static::logDebug('No null groups found');

            return [
                'auto_assignments'      => [],
                'needs_llm_resolution'  => [],
            ];
        }

        static::logDebug('Found ' . count($nullGroupFiles) . ' files with null group assignments');

        // Build page mapping structures
        $pageToFileId   = $this->buildPageToFileIdMap($fileToGroup);
        $allPageNumbers = array_keys($pageToFileId);
        sort($allPageNumbers);

        $autoAssignments    = [];
        $needsLlmResolution = [];

        foreach ($nullGroupFiles as $nullFile) {
            $decision = $this->decideNullGroupAssignment(
                $nullFile,
                $allPageNumbers,
                $pageToFileId,
                $fileToGroup
            );

            if ($decision['type'] === 'auto') {
                $autoAssignments[$nullFile['file_id']] = $decision['group_name'];
            } elseif ($decision['type'] === 'llm') {
                $needsLlmResolution[] = [
                    'file_id'        => $nullFile['file_id'],
                    'page_number'    => $nullFile['page_number'],
                    'previous_group' => $decision['previous_group'],
                    'next_group'     => $decision['next_group'],
                    'description'    => $nullFile['description'],
                    'confidence'     => $nullFile['confidence'],
                ];
            }
        }

        static::logDebug('Null group resolution: ' . count($autoAssignments) . ' auto-assigned, ' . count($needsLlmResolution) . ' need LLM');

        return [
            'auto_assignments'      => $autoAssignments,
            'needs_llm_resolution'  => $needsLlmResolution,
        ];
    }

    /**
     * Identify files with empty string group names.
     *
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Array of file data with null group names
     */
    protected function identifyNullGroupFiles(array $fileToGroup): array
    {
        $nullGroupFiles = [];

        foreach ($fileToGroup as $fileId => $data) {
            if ($data['group_name'] === '') {
                $nullGroupFiles[] = [
                    'file_id'     => $fileId,
                    'page_number' => $data['page_number'],
                    'description' => $data['description'],
                    'confidence'  => $data['confidence'],
                ];
            }
        }

        return $nullGroupFiles;
    }

    /**
     * Build mapping of page_number to file_id.
     *
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array Map of page_number => file_id
     */
    protected function buildPageToFileIdMap(array $fileToGroup): array
    {
        $pageToFileId = [];

        foreach ($fileToGroup as $fileId => $data) {
            $pageToFileId[$data['page_number']] = $fileId;
        }

        return $pageToFileId;
    }

    /**
     * Decide how to assign a null group file.
     *
     * @param  array  $nullFile  File data
     * @param  array  $allPageNumbers  Sorted array of all page numbers
     * @param  array  $pageToFileId  Map of page_number => file_id
     * @param  array  $fileToGroup  Map of file_id => group data
     * @return array ['type' => 'auto'|'llm'|'keep', 'group_name' => string, 'previous_group' => string, 'next_group' => string]
     */
    protected function decideNullGroupAssignment(
        array $nullFile,
        array $allPageNumbers,
        array $pageToFileId,
        array $fileToGroup
    ): array {
        $pageNumber = $nullFile['page_number'];
        $fileId     = $nullFile['file_id'];

        $prevGroup = $this->findAdjacentGroup($pageNumber, $allPageNumbers, $pageToFileId, $fileToGroup, 'previous');
        $nextGroup = $this->findAdjacentGroup($pageNumber, $allPageNumbers, $pageToFileId, $fileToGroup, 'next');

        static::logDebug("Null group file page $pageNumber: prev='{$prevGroup}', next='{$nextGroup}'");

        // Decision logic
        if ($prevGroup && $nextGroup && $prevGroup !== $nextGroup) {
            // Both adjacent groups exist and are different - needs LLM resolution
            static::logDebug('  → Needs LLM resolution (both prev and next exist)');

            return [
                'type'           => 'llm',
                'previous_group' => $prevGroup,
                'next_group'     => $nextGroup,
            ];
        }

        if ($prevGroup && (!$nextGroup || $prevGroup === $nextGroup)) {
            // Only previous group exists, or both are the same - auto-append to previous
            static::logDebug("  → Auto-assigning to previous group '$prevGroup'");

            return [
                'type'       => 'auto',
                'group_name' => $prevGroup,
            ];
        }

        if ($nextGroup) {
            // Only next group exists - auto-append to next
            static::logDebug("  → Auto-assigning to next group '$nextGroup'");

            return [
                'type'       => 'auto',
                'group_name' => $nextGroup,
            ];
        }

        // No adjacent groups - keep as null (edge case)
        static::logDebug('  → No adjacent groups found, keeping as null');

        return ['type' => 'keep'];
    }

    /**
     * Find the adjacent non-null group for a given page number.
     *
     * @param  int  $pageNumber  The page number to find adjacent group for
     * @param  array  $allPageNumbers  Sorted array of all page numbers
     * @param  array  $pageToFileId  Map of page_number => file_id
     * @param  array  $fileToGroup  Map of file_id => group data
     * @param  string  $direction  'previous' or 'next'
     * @return string|null The adjacent group name, or null if none found
     */
    protected function findAdjacentGroup(
        int $pageNumber,
        array $allPageNumbers,
        array $pageToFileId,
        array $fileToGroup,
        string $direction
    ): ?string {
        $currentIndex = array_search($pageNumber, $allPageNumbers);

        if ($currentIndex === false) {
            return null;
        }

        $step  = ($direction === 'previous') ? -1 : 1;
        $index = $currentIndex + $step;

        // Search in the specified direction for a non-null group
        while ($index >= 0 && $index < count($allPageNumbers)) {
            $adjacentPage   = $allPageNumbers[$index];
            $adjacentFileId = $pageToFileId[$adjacentPage] ?? null;

            if ($adjacentFileId && isset($fileToGroup[$adjacentFileId])) {
                $adjacentGroupName = $fileToGroup[$adjacentFileId]['group_name'];

                // Return first non-empty group name we find
                if ($adjacentGroupName !== '') {
                    return $adjacentGroupName;
                }
            }

            $index += $step;
        }

        return null;
    }
}
