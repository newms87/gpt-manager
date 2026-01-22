<?php

namespace App\Traits;

/**
 * Provides smart merging of extraction results that preserves non-null values.
 *
 * Used by extraction services that process data in batches. When merging batch results,
 * later batches should NOT overwrite good data with null/empty values from pages
 * that don't contain the relevant information.
 *
 * Example: Batch 1 extracts "incident_description" from page 1.
 * Batch 2 processes pages 3-4 which don't have incident info, returning "null".
 * Without smart merging, the valid description would be lost.
 */
trait MergesExtractionResults
{
    /**
     * Merge extraction results, preserving non-null values from earlier batches.
     *
     * Only overwrites a value if the new value is meaningful (not null, not "null" string,
     * not empty string, not empty array). For nested arrays/objects, recursively applies
     * the same logic.
     *
     * @param  array  $existing  The cumulative data from previous batches
     * @param  array  $new  The new batch data to merge in
     * @return array The merged result with meaningful values preserved
     */
    protected function mergeExtractionResults(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            // Skip null/empty/meaningless values - don't overwrite good data
            if (!$this->isMeaningfulValue($value)) {
                continue;
            }

            // Handle nested arrays/objects recursively
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                // Check if this is an associative array (object) vs sequential array (list)
                if ($this->isAssociativeArray($value) && $this->isAssociativeArray($existing[$key])) {
                    // Recursively merge associative arrays
                    $existing[$key] = $this->mergeExtractionResults($existing[$key], $value);
                } else {
                    // For sequential arrays (lists), ACCUMULATE items across batches
                    // This prevents losing items when different batches return different subsets
                    $existing[$key] = $this->mergeSequentialArrays($existing[$key], $value);
                }
            } else {
                // Overwrite with meaningful value
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * Check if a value is meaningful and should overwrite existing data.
     *
     * A value is NOT meaningful if it is:
     * - null
     * - An empty string ""
     * - A whitespace-only string
     * - Common LLM placeholder strings for null: "null", "<null>", "N/A", "n/a", "none", "unknown"
     * - An empty array []
     *
     * @param  mixed  $value  The value to check
     */
    protected function isMeaningfulValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $trimmed    = trim($value);
            $normalized = strtolower($trimmed);

            // Empty or whitespace-only strings are not meaningful
            if ($trimmed === '') {
                return false;
            }

            // Common LLM placeholder strings for null/missing values
            $nullPlaceholders = ['null', '<null>', 'n/a', 'na', 'none', 'unknown', '-', '--'];
            if (in_array($normalized, $nullPlaceholders, true)) {
                return false;
            }
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * Check if an array is associative (object-like) vs sequential (list-like).
     *
     * An associative array has string keys or non-sequential integer keys.
     * A sequential array has integer keys 0, 1, 2, ... in order.
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Merge two sequential arrays by accumulating unique items.
     *
     * For arrays of objects, de-duplicates by 'name' or 'id' field if present.
     * This prevents losing items when batches return different subsets of a list
     * (e.g., Batch 1 returns provider A, Batch 2 returns provider B - result should have both).
     *
     * @param  array  $existing  The existing sequential array
     * @param  array  $new  The new sequential array to merge
     * @return array The merged array with unique items accumulated
     */
    protected function mergeSequentialArrays(array $existing, array $new): array
    {
        $result = $existing;

        foreach ($new as $newItem) {
            if (!$this->sequentialArrayContainsItem($result, $newItem)) {
                $result[] = $newItem;
            }
        }

        return array_values($result); // Re-index to ensure sequential
    }

    /**
     * Check if a sequential array already contains an equivalent item.
     *
     * For objects with 'name' or 'id', compares by that field.
     * For scalars, uses strict comparison.
     *
     * @param  array  $haystack  The array to search in
     * @param  mixed  $needle  The item to look for
     */
    protected function sequentialArrayContainsItem(array $haystack, mixed $needle): bool
    {
        // For objects with identifying fields, check for duplicates
        if (is_array($needle)) {
            $needleId   = $needle['id']   ?? null;
            $needleName = $needle['name'] ?? null;

            foreach ($haystack as $item) {
                if (!is_array($item)) {
                    continue;
                }

                // Check by ID first (exact match)
                if ($needleId !== null && isset($item['id']) && $item['id'] === $needleId) {
                    return true;
                }

                // Check by name (case-insensitive)
                if ($needleName !== null && isset($item['name'])) {
                    if (strtolower(trim((string)$item['name'])) === strtolower(trim((string)$needleName))) {
                        return true;
                    }
                }
            }

            return false;
        }

        // For scalars, use strict comparison
        return in_array($needle, $haystack, true);
    }

    /**
     * Merge extraction results and return which fields were actually updated.
     *
     * This method is identical to mergeExtractionResults but also tracks which field paths
     * had their data actually updated (i.e., received a meaningful new value). This is used
     * to coordinate page_sources merging - only update page_sources for fields where the
     * corresponding data was actually updated.
     *
     * @param  array  $existing  The cumulative data from previous batches
     * @param  array  $new  The new batch data to merge in
     * @param  string  $prefix  Internal: current field path prefix for tracking
     * @return array{merged: array, updated_fields: array<string>} The merged result with list of updated field paths
     */
    protected function mergeExtractionResultsWithTracking(array $existing, array $new, string $prefix = ''): array
    {
        $merged        = $existing;
        $updatedFields = [];

        foreach ($new as $key => $value) {
            $fieldPath = $prefix !== '' ? "{$prefix}.{$key}" : $key;

            // Skip null/empty/meaningless values - don't overwrite good data
            if (!$this->isMeaningfulValue($value)) {
                continue;
            }

            // Handle nested arrays/objects recursively
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                // Check if this is an associative array (object) vs sequential array (list)
                if ($this->isAssociativeArray($value) && $this->isAssociativeArray($existing[$key])) {
                    // Recursively merge associative arrays and track updated fields
                    $subResult     = $this->mergeExtractionResultsWithTracking($existing[$key], $value, $fieldPath);
                    $merged[$key]  = $subResult['merged'];
                    $updatedFields = array_merge($updatedFields, $subResult['updated_fields']);
                } else {
                    // For sequential arrays (lists), ACCUMULATE items across batches
                    // This prevents losing items when different batches return different subsets
                    $merged[$key]    = $this->mergeSequentialArrays($merged[$key], $value);
                    $updatedFields[] = $fieldPath;
                }
            } else {
                // Overwrite with meaningful value and track the update
                $merged[$key]    = $value;
                $updatedFields[] = $fieldPath;
            }
        }

        return ['merged' => $merged, 'updated_fields' => $updatedFields];
    }

    /**
     * Merge page sources based on which fields were actually updated.
     *
     * Only merges page_sources for fields that had their data updated during the extraction
     * merge. This prevents later batches from overwriting page_sources when they return
     * empty/null data that doesn't update the actual extracted values.
     *
     * @param  array  $existingPageSources  Cumulative page sources from previous batches
     * @param  array  $newPageSources  Page sources from the current batch
     * @param  array<string>  $updatedFields  List of field paths that were actually updated
     * @return array The merged page sources
     */
    protected function mergePageSourcesForUpdatedFields(
        array $existingPageSources,
        array $newPageSources,
        array $updatedFields
    ): array {
        $merged = $existingPageSources;

        foreach ($updatedFields as $fieldPath) {
            // Extract the field name from the path (e.g., "care_summary.name" -> "name")
            // Page sources are typically keyed by field name, not full path
            $fieldName = $this->extractFieldNameFromPath($fieldPath);

            // Check both the full path and just the field name in page sources
            if (isset($newPageSources[$fieldPath])) {
                $merged[$fieldPath] = $newPageSources[$fieldPath];
            } elseif (isset($newPageSources[$fieldName])) {
                $merged[$fieldName] = $newPageSources[$fieldName];
            }
        }

        return $merged;
    }

    /**
     * Extract the field name from a dot-notation path.
     *
     * Examples:
     * - "incident_description" -> "incident_description"
     * - "care_summary.name" -> "name"
     * - "providers[0].name" -> "name"
     */
    protected function extractFieldNameFromPath(string $fieldPath): string
    {
        // Handle array notation first (e.g., "providers[0].name" -> "providers.name")
        $normalized = preg_replace('/\[\d+\]/', '', $fieldPath);

        // Get the last segment after the dot
        $parts = explode('.', $normalized);

        return end($parts);
    }

    /**
     * Look up a page source by trying both the full path and the field name.
     *
     * Page sources can be keyed in different ways:
     * - Full path: "care_summary.name" => 1
     * - Field name only: "name" => 1
     * - Array notation: "diagnoses[0].name" => 1
     *
     * This method tries the full path first (more specific), then falls back to field name.
     */
    protected function lookupPageSource(array $pageSources, string $fieldPath, string $fieldName): ?int
    {
        // Try full path first (most specific)
        if (isset($pageSources[$fieldPath])) {
            return (int)$pageSources[$fieldPath];
        }

        // Try field name (less specific)
        if (isset($pageSources[$fieldName])) {
            return (int)$pageSources[$fieldName];
        }

        return null;
    }

    /**
     * Merge extraction results, track updates, AND detect conflicts.
     *
     * A conflict occurs when both existing and new values are meaningful but DIFFERENT.
     * This is used for batch extraction where different batches may return different
     * meaningful values for the same field, requiring LLM resolution.
     *
     * @param  array  $existing  The cumulative data from previous batches
     * @param  array  $new  The new batch data to merge in
     * @param  array  $existingPageSources  Page sources from previous batches (keyed by field name)
     * @param  array  $newPageSources  Page sources from the current batch (keyed by field name)
     * @param  string  $prefix  Internal: current field path prefix for tracking
     * @return array{
     *   merged: array,
     *   updated_fields: array<string>,
     *   conflicts: array<array{field_path: string, field_name: string, existing_value: mixed, existing_page: int|null, new_value: mixed, new_page: int|null}>
     * }
     */
    protected function mergeExtractionResultsWithConflicts(
        array $existing,
        array $new,
        array $existingPageSources = [],
        array $newPageSources = [],
        string $prefix = ''
    ): array {
        $merged        = $existing;
        $updatedFields = [];
        $conflicts     = [];

        foreach ($new as $key => $value) {
            $fieldPath = $prefix !== '' ? "{$prefix}.{$key}" : $key;
            $fieldName = $this->extractFieldNameFromPath($fieldPath);

            $existingValue = $existing[$key] ?? null;

            // Look up page sources by full path first, then by field name
            // Page sources can be keyed as "care_summary.name" (full path) or "name" (field name)
            $existingPage  = $this->lookupPageSource($existingPageSources, $fieldPath, $fieldName);
            $newPage       = $this->lookupPageSource($newPageSources, $fieldPath, $fieldName);

            $existingMeaningful = $this->isMeaningfulValue($existingValue);
            $newMeaningful      = $this->isMeaningfulValue($value);

            // Skip null/empty/meaningless new values - don't overwrite good data
            if (!$newMeaningful) {
                continue;
            }

            // Handle nested arrays/objects recursively BEFORE checking for conflicts
            // Conflicts only apply to leaf values (scalars), not parent objects
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                // Check if this is an associative array (object) vs sequential array (list)
                if ($this->isAssociativeArray($value) && $this->isAssociativeArray($existing[$key])) {
                    // Recursively merge associative arrays and track updated fields + conflicts
                    $subResult     = $this->mergeExtractionResultsWithConflicts(
                        $existing[$key],
                        $value,
                        $existingPageSources,
                        $newPageSources,
                        $fieldPath
                    );
                    $merged[$key]  = $subResult['merged'];
                    $updatedFields = array_merge($updatedFields, $subResult['updated_fields']);
                    $conflicts     = array_merge($conflicts, $subResult['conflicts']);
                } else {
                    // For sequential arrays (lists), ACCUMULATE items across batches
                    // This prevents losing items when different batches return different subsets
                    $merged[$key]    = $this->mergeSequentialArrays($merged[$key], $value);
                    $updatedFields[] = $fieldPath;
                }

                continue;
            }

            // Check for conflict: both meaningful but different (scalar values only)
            if ($existingMeaningful && $this->valuesAreDifferent($existingValue, $value)) {
                $conflicts[] = [
                    'field_path'     => $fieldPath,
                    'field_name'     => $fieldName,
                    'existing_value' => $existingValue,
                    'existing_page'  => $existingPage,
                    'new_value'      => $value,
                    'new_page'       => $newPage,
                ];
                // Keep existing value for now - will be resolved later by ConflictResolutionService
                continue;
            }

            // Overwrite with meaningful value and track the update
            $merged[$key]    = $value;
            $updatedFields[] = $fieldPath;
        }

        return [
            'merged'         => $merged,
            'updated_fields' => $updatedFields,
            'conflicts'      => $conflicts,
        ];
    }

    /**
     * Check if two values are meaningfully different.
     *
     * Normalizes strings for comparison (trim, lowercase).
     * For arrays, compares JSON representations.
     */
    protected function valuesAreDifferent(mixed $a, mixed $b): bool
    {
        // Normalize strings for comparison
        if (is_string($a) && is_string($b)) {
            return strtolower(trim($a)) !== strtolower(trim($b));
        }

        // For arrays, compare JSON representations
        if (is_array($a) && is_array($b)) {
            return json_encode($a) !== json_encode($b);
        }

        // Direct comparison for other types
        return $a !== $b;
    }
}
