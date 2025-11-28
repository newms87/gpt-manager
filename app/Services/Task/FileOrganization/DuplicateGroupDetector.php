<?php

namespace App\Services\Task\FileOrganization;

use App\Traits\HasDebugLogging;

/**
 * Detects potential duplicate groups with similar names that should be merged.
 */
class DuplicateGroupDetector
{
    use HasDebugLogging;

    /**
     * Identify potential duplicate groups based on name similarity.
     *
     * This detects groups that are likely the same entity but have variations in naming:
     * - "ME Physical Therapy" vs "ME Physical Therapy (Northglenn)"
     * - "ABC Medical" vs "ABC Medical Center"
     * - "XYZ Clinic" vs "XYZ"
     *
     * @param  array  $finalGroups  Array of groups with names
     * @return array Array of duplicate candidate pairs: [['group1' => string, 'group2' => string, 'similarity' => float], ...]
     */
    public function identifyDuplicateCandidates(array $finalGroups): array
    {
        $candidates = [];
        $groupNames = array_column($finalGroups, 'name');

        static::logDebug('Checking ' . count($groupNames) . ' groups for potential duplicates');

        // Compare each pair of groups
        for ($i = 0; $i < count($groupNames); $i++) {
            for ($j = $i + 1; $j < count($groupNames); $j++) {
                $name1 = $groupNames[$i];
                $name2 = $groupNames[$j];

                // Skip empty names
                if ($name1 === '' || $name2 === '') {
                    continue;
                }

                $similarity = $this->calculateSimilarity($name1, $name2);

                // If similarity is high enough, mark as candidate
                if ($similarity >= 0.7) {
                    $candidates[] = [
                        'group1'     => $name1,
                        'group2'     => $name2,
                        'similarity' => $similarity,
                    ];

                    static::logDebug("Found duplicate candidate: '$name1' <-> '$name2' (similarity: $similarity)");
                }
            }
        }

        static::logDebug('Found ' . count($candidates) . ' duplicate candidates');

        return $candidates;
    }

    /**
     * Calculate similarity between two group names.
     *
     * Uses multiple heuristics:
     * 1. One name is a substring of the other (e.g., "ABC" in "ABC Medical")
     * 2. One name is the other with a location suffix (e.g., "ABC (City)")
     * 3. Levenshtein distance for fuzzy matching
     *
     * @param  string  $name1  First group name
     * @param  string  $name2  Second group name
     * @return float Similarity score between 0.0 and 1.0
     */
    protected function calculateSimilarity(string $name1, string $name2): float
    {
        // Normalize for comparison
        $normalized1 = $this->normalizeName($name1);
        $normalized2 = $this->normalizeName($name2);

        // Exact match after normalization
        if ($normalized1 === $normalized2) {
            return 1.0;
        }

        // Check if one is a substring of the other
        if (str_contains($normalized1, $normalized2) || str_contains($normalized2, $normalized1)) {
            // Calculate how much longer the longer string is
            $shorterLen = min(strlen($normalized1), strlen($normalized2));
            $longerLen  = max(strlen($normalized1), strlen($normalized2));

            // If the longer one is just the shorter one with a location/suffix, score higher
            $lengthRatio = $shorterLen / $longerLen;

            // High score if one is clearly a subset (e.g., "ABC" in "ABC Medical")
            return max(0.85, $lengthRatio);
        }

        // Check for location suffix pattern: "Name" vs "Name (Location)"
        if ($this->isLocationVariant($name1, $name2)) {
            return 0.95;
        }

        // Use Levenshtein distance for fuzzy matching
        $maxLen   = max(strlen($normalized1), strlen($normalized2));
        $distance = levenshtein($normalized1, $normalized2);

        // Convert distance to similarity (0 distance = 1.0 similarity)
        $similarity = 1.0 - ($distance / $maxLen);

        return max(0.0, $similarity);
    }

    /**
     * Normalize a group name for comparison.
     *
     * - Convert to lowercase
     * - Remove extra whitespace
     * - Remove common punctuation
     *
     * @param  string  $name  Group name
     * @return string Normalized name
     */
    protected function normalizeName(string $name): string
    {
        // Lowercase
        $normalized = strtolower($name);

        // Remove common punctuation (but keep spaces and parentheses for location detection)
        $normalized = str_replace([',', '.', ':', ';'], '', $normalized);

        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Check if two names are variants with one having a location suffix.
     *
     * Examples:
     * - "ABC Medical" vs "ABC Medical (Northglenn)"
     * - "XYZ Therapy" vs "XYZ Therapy (Denver)"
     *
     * @param  string  $name1  First name
     * @param  string  $name2  Second name
     * @return bool True if one is a location variant of the other
     */
    protected function isLocationVariant(string $name1, string $name2): bool
    {
        $normalized1 = $this->normalizeName($name1);
        $normalized2 = $this->normalizeName($name2);

        // Check if one has parentheses and the other doesn't
        $hasParens1 = str_contains($normalized1, '(') && str_contains($normalized1, ')');
        $hasParens2 = str_contains($normalized2, '(') && str_contains($normalized2, ')');

        if ($hasParens1 === $hasParens2) {
            return false; // Both have parens or neither does
        }

        // Get the base name (without location suffix)
        $withParens    = $hasParens1 ? $normalized1 : $normalized2;
        $withoutParens = $hasParens1 ? $normalized2 : $normalized1;

        // Extract base name before the parentheses
        $baseName = trim(substr($withParens, 0, strpos($withParens, '(')));

        // Check if the name without parentheses matches the base
        return $baseName === $withoutParens;
    }

    /**
     * Prepare duplicate candidate data for LLM resolution.
     *
     * @param  array  $candidate  Candidate pair from identifyDuplicateCandidates
     * @param  array  $finalGroups  All final groups
     * @param  array  $fileToGroup  File-to-group mapping
     * @return array Data structure for LLM resolution
     */
    public function prepareDuplicateForResolution(array $candidate, array $finalGroups, array $fileToGroup): array
    {
        $group1Name = $candidate['group1'];
        $group2Name = $candidate['group2'];

        // Find group data
        $group1 = $this->findGroup($group1Name, $finalGroups);
        $group2 = $this->findGroup($group2Name, $finalGroups);

        if (!$group1 || !$group2) {
            static::logDebug("Could not find group data for '$group1Name' or '$group2Name'");

            return [];
        }

        return [
            'group1' => [
                'name'         => $group1['name'],
                'description'  => $group1['description'],
                'file_count'   => count($group1['files']),
                'sample_files' => $this->getSampleFiles($group1['files'], $fileToGroup, 3),
                'confidence'   => $group1['confidence_summary'] ?? null,
            ],
            'group2' => [
                'name'         => $group2['name'],
                'description'  => $group2['description'],
                'file_count'   => count($group2['files']),
                'sample_files' => $this->getSampleFiles($group2['files'], $fileToGroup, 3),
                'confidence'   => $group2['confidence_summary'] ?? null,
            ],
            'similarity' => $candidate['similarity'],
        ];
    }

    /**
     * Find a group by name in the final groups array.
     *
     * @param  string  $groupName  Group name to find
     * @param  array  $finalGroups  Array of groups
     * @return array|null Group data or null if not found
     */
    protected function findGroup(string $groupName, array $finalGroups): ?array
    {
        foreach ($finalGroups as $group) {
            if ($group['name'] === $groupName) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Get sample files from a group for LLM context.
     *
     * @param  array  $fileIds  Array of file IDs
     * @param  array  $fileToGroup  File-to-group mapping
     * @param  int  $limit  Maximum number of samples
     * @return array Array of file data
     */
    protected function getSampleFiles(array $fileIds, array $fileToGroup, int $limit = 3): array
    {
        $samples = [];
        $count   = 0;

        foreach ($fileIds as $fileId) {
            if ($count >= $limit) {
                break;
            }

            if (isset($fileToGroup[$fileId])) {
                $samples[] = [
                    'page_number' => $fileToGroup[$fileId]['page_number'],
                    'description' => $fileToGroup[$fileId]['description'] ?? '',
                    'confidence'  => $fileToGroup[$fileId]['confidence'],
                ];
                $count++;
            }
        }

        return $samples;
    }
}
