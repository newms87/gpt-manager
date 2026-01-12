<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles page-source attribution for data extraction.
 * Splits extracted data by source page and manages page number resolution.
 */
class PageSourceService
{
    /**
     * Split extracted data by source page.
     *
     * @param  array  $extractedData  The full extracted data (without __source__ fields)
     * @param  array  $pageSources  Map of field name => page number (single integer)
     * @return array<int, array> Data keyed by page number
     */
    public function splitDataByPage(array $extractedData, array $pageSources): array
    {
        $dataByPage = [];

        foreach ($extractedData as $fieldName => $value) {
            // Skip __source__ fields (they should already be extracted)
            if (str_starts_with($fieldName, '__source__')) {
                continue;
            }

            // Get the page number for this field (default to 1 if not found)
            $pageNumber = $pageSources[$fieldName] ?? 1;

            // Initialize page array if not exists
            if (!isset($dataByPage[$pageNumber])) {
                $dataByPage[$pageNumber] = [];
            }

            $dataByPage[$pageNumber][$fieldName] = $value;
        }

        // Sort by page number
        ksort($dataByPage);

        return $dataByPage;
    }

    /**
     * Extract __source__ values from LLM response data.
     *
     * Extracts page source annotations from LLM response that uses the format:
     * {"field_name": "value", "__source__field_name": 1}
     *
     * @param  array  $data  LLM response data containing __source__ fields
     * @param  string  $prefix  Current key prefix for recursive calls (internal use)
     * @return array Map of field name => page number
     */
    public function extractPageSources(array $data, string $prefix = ''): array
    {
        $pageSources = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            // Check if this is a __source__ field
            if (str_starts_with($key, '__source__')) {
                $fieldName = substr($key, 10); // Remove '__source__' prefix
                $sourceKey = $prefix ? "{$prefix}.{$fieldName}" : $fieldName;

                // Value should be an integer page number
                if (is_int($value)) {
                    $pageSources[$sourceKey] = $value;
                }
            } elseif (is_array($value)) {
                // Check if this is an associative array (object) or indexed array
                if ($this->isAssociativeArray($value)) {
                    // Recursively extract from nested objects
                    $nestedSources = $this->extractPageSources($value, $fullKey);
                    $pageSources   = array_merge($pageSources, $nestedSources);
                } else {
                    // Handle indexed arrays - each element may have sources
                    foreach ($value as $index => $element) {
                        if (is_array($element) && $this->isAssociativeArray($element)) {
                            $arrayKey      = "{$fullKey}[{$index}]";
                            $nestedSources = $this->extractPageSources($element, $arrayKey);
                            $pageSources   = array_merge($pageSources, $nestedSources);
                        }
                    }
                }
            }
        }

        return $pageSources;
    }

    /**
     * Get page numbers from input artifacts.
     *
     * @param  Collection<Artifact>  $artifacts  Collection of artifacts
     * @return array<int> Sorted array of unique page numbers
     */
    public function getPageNumbersFromArtifacts(Collection $artifacts): array
    {
        $pageNumbers = [];

        foreach ($artifacts as $artifact) {
            // Use position field which stores the page number
            if ($artifact->position !== null) {
                $pageNumbers[] = (int)$artifact->position;
            }
        }

        $pageNumbers = array_unique($pageNumbers);
        sort($pageNumbers);

        return array_values($pageNumbers);
    }

    /**
     * Find artifact by page number.
     *
     * @param  Collection<Artifact>  $artifacts  Collection of artifacts to search
     * @param  int  $pageNumber  Page number to find
     * @return Artifact|null The matching artifact or null if not found
     */
    public function findArtifactByPage(Collection $artifacts, int $pageNumber): ?Artifact
    {
        return $artifacts->first(fn(Artifact $artifact) => (int)$artifact->position === $pageNumber);
    }

    /**
     * Remove __source__ fields from extracted data.
     *
     * @param  array  $data  Data containing __source__ fields
     * @return array Data with __source__ fields removed
     */
    public function removeSourceFields(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            // Skip __source__ fields
            if (str_starts_with($key, '__source__')) {
                continue;
            }

            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    // Recursively clean nested objects
                    $cleaned[$key] = $this->removeSourceFields($value);
                } else {
                    // Handle indexed arrays
                    $cleaned[$key] = array_map(
                        fn($element) => is_array($element) && $this->isAssociativeArray($element)
                            ? $this->removeSourceFields($element)
                            : $element,
                        $value
                    );
                }
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }

    /**
     * Build instructions for __source__ fields based on available page numbers.
     *
     * Extracts page numbers from input artifacts and provides guidance for the LLM
     * on how to populate the __source__{field} properties.
     */
    public function buildPageSourceInstructions(Collection $artifacts): string
    {
        // Extract page numbers from artifacts
        $pageNumbers = [];
        foreach ($artifacts as $artifact) {
            $pageNumber = $artifact->storedFiles()
                ->whereNotNull('meta->page_number')
                ->pluck('meta->page_number')
                ->unique()
                ->toArray();

            $pageNumbers = array_merge($pageNumbers, $pageNumber);
        }

        // Also check artifact meta directly for page_number
        foreach ($artifacts as $artifact) {
            if (isset($artifact->meta['page_number'])) {
                $pageNumbers[] = $artifact->meta['page_number'];
            }
        }

        // Also check artifact position (used for page artifacts)
        foreach ($artifacts as $artifact) {
            if ($artifact->position !== null) {
                $pageNumbers[] = (int)$artifact->position;
            }
        }

        $pageNumbers = array_unique(array_filter($pageNumbers));
        sort($pageNumbers);

        if (empty($pageNumbers)) {
            return '';
        }

        $pageList = implode(', ', $pageNumbers);

        return <<<EOT
PAGE SOURCE TRACKING:
Available pages: [{$pageList}]

For each field you extract, populate the corresponding __source__{field} property with the PRIMARY page number where you found that value.

Guidelines:
- Choose the page with the MOST CONTEXT about the value
- If context is roughly equal across pages, use the FIRST page
- Page numbers are 1-indexed (start at 1, not 0)
- Only use page numbers from the available list above
EOT;
    }

    /**
     * Load the pageSource schema definition from yaml.
     *
     * @return array The pageSource JSON schema definition
     */
    public function getPageSourceDef(): array
    {
        $path = app_path('Services/JsonSchema/page_source.def.yaml');

        return Yaml::parseFile($path);
    }

    /**
     * Inject __source__ properties for each field in the schema.
     *
     * For each field in fieldNames, adds __source__{field} => {"$ref": "#/$defs/pageSource"}
     * Properties are inserted after the original field to maintain visual grouping.
     *
     * @param  array<string, array>  $properties  Schema properties to inject into
     * @param  array<string>  $fieldNames  Fields that should have corresponding __source__ properties
     * @return array<string, array> Properties with __source__ fields added
     */
    public function injectPageSourceProperties(array $properties, array $fieldNames): array
    {
        $result = [];

        foreach ($properties as $key => $value) {
            $result[$key] = $value;

            // If this is a field that should have a source, add it after
            if (in_array($key, $fieldNames, true)) {
                $result['__source__' . $key] = ['$ref' => '#/$defs/pageSource'];
            }
        }

        return $result;
    }

    /**
     * Check if array is associative (object-like) vs indexed (list-like).
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
