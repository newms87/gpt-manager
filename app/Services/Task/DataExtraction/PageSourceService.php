<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles page-source attribution for data extraction.
 * Splits extracted data by source page and manages page number resolution.
 *
 * Page sources are now returned at the top level of the response:
 * {
 *   "data": { "name": "John", "accident_date": "2024-01-15" },
 *   "page_sources": { "name": 1, "accident_date": 2 }
 * }
 *
 * For array extractions, page_sources uses dot notation:
 * {
 *   "data": { "diagnoses": [{ "name": "Diagnosis A" }, { "name": "Diagnosis B" }] },
 *   "page_sources": { "diagnoses[0].name": 1, "diagnoses[1].name": 2 }
 * }
 */
class PageSourceService
{
    /**
     * Split extracted data by source page.
     *
     * @param  array  $extractedData  The full extracted data
     * @param  array  $pageSources  Map of field name => page number (single integer)
     * @return array<int, array> Data keyed by page number
     */
    public function splitDataByPage(array $extractedData, array $pageSources): array
    {
        $dataByPage = [];

        foreach ($extractedData as $fieldName => $value) {
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
     * Extract page_sources from top-level response data.
     *
     * The LLM response now contains page_sources at the top level:
     * { "data": {...}, "page_sources": { "field": 1, "other_field": 2 } }
     *
     * @param  array  $data  LLM response data containing top-level page_sources
     * @return array Map of field name => page number
     */
    public function extractPageSources(array $data): array
    {
        return $data['page_sources'] ?? [];
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
     * Build instructions for page_sources at the top level.
     *
     * Extracts page numbers from input artifacts and provides guidance for the LLM
     * on how to populate the top-level page_sources object.
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
        $template = file_get_contents(resource_path('prompts/extract-data/page-source-tracking.md'));

        return strtr($template, ['{{page_list}}' => $pageList]);
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
     * Build the page_sources schema for top-level page source tracking.
     *
     * Returns a schema for { field_name: integer (page number) } for each field.
     * For array extractions, field names use dot notation: "field[0].property"
     *
     * @param  array<string>  $fieldNames  Fields that should have page source tracking
     */
    public function buildPageSourcesSchema(array $fieldNames): array
    {
        $properties = [];

        foreach ($fieldNames as $field) {
            $properties[$field] = [
                '$ref' => '#/$defs/pageSource',
            ];
        }

        return [
            'type'                 => 'object',
            'description'          => 'Page numbers where each field value was found. ' .
                                     'Use field names as keys and page numbers (integers) as values. ' .
                                     'For array fields, use dot notation: "field[0].property": 1',
            'properties'           => $properties,
            'additionalProperties' => ['$ref' => '#/$defs/pageSource'],
        ];
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
