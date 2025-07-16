<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

class ClassificationDeduplicationService
{
    use HasDebugLogging;

    /**
     * Get or create the classification deduplication agent based on config
     */
    public function getOrCreateClassificationDeduplicationAgent(): Agent
    {
        $config    = config('ai.classification_deduplication');
        $agentName = $config['agent_name'];
        $model     = $config['model'];

        // Find existing agent by name and model (no team context needed)
        $agent = Agent::whereNull('team_id')
            ->where('name', $agentName)
            ->first();

        if (!$agent) {
            // Create agent directly with explicit null team_id
            $agent = Agent::create([
                'name'        => $agentName,
                'model'       => $model,
                'description' => 'Automated agent for data value deduplication and normalization',
                'api_options' => ['temperature' => 0],
                'team_id'     => null,
                'retry_count' => 2,
            ]);
            static::log("Created new classification deduplication agent: $agent->name (ID: $agent->id)");
        }

        return $agent;
    }


    /**
     * Deduplicate classification labels across artifacts
     *
     * @param Collection|Artifact[] $artifacts
     * @return void
     */
    public function deduplicateClassificationLabels(Collection $artifacts): void
    {
        static::log("Starting classification deduplication for " . $artifacts->count() . " artifacts");

        $labelsToNormalize = $this->extractClassificationLabels($artifacts);

        if (empty($labelsToNormalize)) {
            static::log("No classification labels found for deduplication");

            return;
        }

        static::log("Found " . count($labelsToNormalize) . " unique values to process");

        // Log the values for debugging
        foreach($labelsToNormalize as $label) {
            static::log("  - " . (strlen($label) > 80 ? substr($label, 0, 80) . "..." : $label));
        }

        $normalizedMappings = $this->getNormalizedClassificationMappings($labelsToNormalize);

        if (empty($normalizedMappings)) {
            static::log("No normalization mappings generated");

            return;
        }

        static::log("Generated " . count($normalizedMappings) . " normalization mappings:");
        foreach($normalizedMappings as $original => $normalized) {
            if (strlen($original) > 80) {
                static::log("  '" . substr($original, 0, 80) . "...' => '" . substr($normalized, 0, 80) . "...'");
            } else {
                static::log("  '$original' => '$normalized'");
            }
        }

        $this->applyNormalizedClassificationsToArtifacts($artifacts, $normalizedMappings);

        // Post-process to ensure array uniqueness and proper formatting
        $this->postProcessArrays($artifacts);

        static::log("Classification deduplication completed");
    }

    /**
     * Extract all classification labels from artifact metadata
     *
     * @param Collection $artifacts
     * @return array
     */
    protected function extractClassificationLabels(Collection $artifacts): array
    {
        $labels = [];

        foreach($artifacts as $artifact) {
            $classification = $artifact->meta['classification'] ?? null;
            if (!$classification) {
                continue;
            }

            $this->extractLabelsFromClassification($classification, $labels);
        }

        return array_unique($labels);
    }

    /**
     * Recursively extract labels from classification data
     *
     * @param array  $classification
     * @param array &$labels
     * @return void
     */
    protected function extractLabelsFromClassification(array $classification, array &$labels): void
    {
        foreach($classification as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value && !empty($value)) {
                    $labels[] = $value;
                }
            } elseif (is_array($value)) {
                // Check if this is an associative array (object) with mixed types
                if ($this->isAssociativeArray($value)) {
                    // Only process objects that have an id or name property
                    if ($this->shouldDeduplicateObject($value)) {
                        // Extract the id or name value for deduplication
                        if (isset($value['id']) && $value['id'] !== null) {
                            $labels[] = $value['id'];
                        } elseif (isset($value['name']) && $value['name'] !== null) {
                            $labels[] = $value['name'];
                        }
                    }
                } else {
                    // Regular array - recurse into it
                    $this->extractLabelsFromClassification($value, $labels);
                }
            }
            // Ignore boolean and numeric values
        }
    }

    /**
     * Check if array is associative (object-like) vs indexed array
     *
     * @param array $array
     * @return bool
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        // If keys are not sequential integers starting from 0, it's associative
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Check if an object should be deduplicated based on id/name properties
     *
     * @param array $object
     * @return bool
     */
    protected function shouldDeduplicateObject(array $object): bool
    {
        // Check for id property first
        if (isset($object['id']) && $object['id'] !== null) {
            return true;
        }

        // Check for name property second
        if (isset($object['name']) && $object['name'] !== null) {
            return true;
        }

        // Object has neither id nor name (or they're null), don't deduplicate
        return false;
    }

    /**
     * Get normalized classification mappings from AI agent
     *
     * @param array $labels
     * @return array|null
     */
    protected function getNormalizedClassificationMappings(array $labels): ?array
    {
        $prompt = $this->buildClassificationDeduplicationPrompt($labels);

        $threadRepository = app(ThreadRepository::class);
        $agent            = $this->getOrCreateClassificationDeduplicationAgent();
        $agentThread      = $threadRepository->create($agent, 'Data Value Deduplication');

        $systemMessage = 'You are a data normalization assistant. Your job is to identify duplicate or similar values and normalize them to a consistent format. Always respond with valid JSON.';
        $threadRepository->addMessageToThread($agentThread, $systemMessage);

        $threadRepository->addMessageToThread($agentThread, $prompt);

        $threadRun = (new AgentThreadService())->run($agentThread);

        if (!$threadRun->lastMessage || !$threadRun->lastMessage->content) {
            static::log("Failed to get response from AI agent for classification deduplication");

            return null;
        }

        try {
            $content = $threadRun->lastMessage->content;
            static::log("Raw AI response: " . substr($content, 0, 500) . (strlen($content) > 500 ? '...' : ''));

            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
                $content = $matches[1];
            } elseif (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $matches)) {
                $content = $matches[0];
            }

            $jsonContent = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                static::log("Failed to parse JSON response: " . json_last_error_msg());
                static::log("Content that failed to parse: " . $content);

                return null;
            }

            return $jsonContent;
        } catch(\Exception $e) {
            static::log("Error parsing JSON response: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Build the classification deduplication prompt
     *
     * @param array $labels
     * @return string
     */
    protected function buildClassificationDeduplicationPrompt(array $labels): string
    {
        $labelsJson = json_encode(array_values($labels), JSON_PRETTY_PRINT);

        return <<<PROMPT
I have data values extracted from structured data. Some values may represent the same concept but with different wording, formatting, casing, or redundancy.

IMPORTANT: The values are individual string values, NOT full JSON objects. These strings represent any kind of data that may have variations in formatting, casing, or representation.

Data values to normalize:
$labelsJson

Please analyze these values and create a mapping of original values to normalized values. Focus on:

**For String Values:**
1. **Entity Recognition**: Identify when different strings refer to the same entity or concept
   - Be AGGRESSIVE in identifying entities that are clearly the same organization/person with location suffixes
   - Example: "University Orthopedic Care", "University Orthopedic Care, Palm Springs", "University Orthopedic Care, Tamarac" should ALL map to "University Orthopedic Care"
   - Example: "Dr. John Smith", "Dr John Smith", "John Smith, MD" should ALL map to "Dr. John Smith"
2. **Location Suffix Removal**: Remove location suffixes from organization names when they clearly refer to the same entity
   - "Company Name, City" → "Company Name"
   - "Practice Name, Location" → "Practice Name"
3. **Consistent Casing**: Normalize casing to be consistent:
   - Proper nouns: Use appropriate title casing
   - Acronyms: Keep as uppercase
   - Common terms: Use lowercase unless they are proper nouns
4. **Standardization**: Normalize similar formats to a consistent standard
5. **Redundancy removal**: Remove duplicate concepts while preserving the most complete/formal version
6. **Formatting consistency**: Standardize punctuation, spacing, and formatting

**Important**: You are normalizing individual string values. These strings represent any kind of data that may have variations in formatting, casing, or representation.

IMPORTANT RULES:
1. Only include entries where the value actually needs to be changed
2. If a value is already in its best normalized form, don't include it
3. Choose the most complete and properly formatted version when consolidating
4. Maintain consistency in formatting and casing
5. **CRITICAL**: Do NOT collapse different concepts into the same value
6. **CRITICAL**: Ensure arrays contain unique values - no duplicates

Example response format:
{
  "APPLE": "Apple",
  "apple inc": "Apple Inc",
  "University Orthopedic Care, Palm Springs": "University Orthopedic Care",
  "University Orthopedic Care, Tamarac": "University Orthopedic Care",
  "Dr John Smith": "Dr. John Smith",
  "John Smith, MD": "Dr. John Smith",
  "red": "red",
  "RED": "red",
  "New York City": "New York City",
  "new york city": "New York City",
  "NYC": "New York City",
  "user-123": "user-123",
  "USER_456": "user-456"
}

Return only the JSON object with mappings for values that need normalization.
PROMPT;
    }

    /**
     * Apply normalized classifications to all artifacts
     *
     * @param Collection $artifacts
     * @param array      $normalizedMappings
     * @return void
     */
    protected function applyNormalizedClassificationsToArtifacts(Collection $artifacts, array $normalizedMappings): void
    {
        if (empty($normalizedMappings)) {
            static::log("No normalization mappings to apply");

            return;
        }

        $totalUpdates = 0;

        foreach($artifacts as $artifact) {
            $classification = $artifact->meta['classification'] ?? null;
            if (!$classification) {
                continue;
            }

            $updates = [];
            $this->updateClassificationLabels($classification, $normalizedMappings, $updates);

            if (!empty($updates)) {
                $meta                   = $artifact->meta;
                $meta['classification'] = $classification;
                $artifact->meta         = $meta;
                $artifact->save();

                static::log("=== Updated artifact {$artifact->id} ===");
                foreach($updates as $update) {
                    $path = isset($update['path']) ? " at '{$update['path']}'" : "";
                    static::log("  '{$update['original']}' => '{$update['normalized']}'$path");
                    $totalUpdates++;
                }
            }
        }

        static::log("Total classification updates across all artifacts: $totalUpdates");
    }

    /**
     * Recursively update classification labels
     *
     * @param array &$classification
     * @param array  $normalizedMappings
     * @param array &$updates
     * @return void
     */
    protected function updateClassificationLabels(array &$classification, array $normalizedMappings, array &$updates): void
    {
        foreach($classification as $key => &$value) {
            if (is_string($value)) {
                $trimmedValue = trim($value);
                if (isset($normalizedMappings[$trimmedValue])) {
                    $normalizedValue = $normalizedMappings[$trimmedValue];
                    $value           = $normalizedValue;
                    $updates[]       = [
                        'original'   => $trimmedValue,
                        'normalized' => $normalizedValue,
                    ];
                    static::log("Updated classification label: '$trimmedValue' => '$normalizedValue'");
                }
            } elseif (is_array($value)) {
                // Check if this is an associative array (object)
                if ($this->isAssociativeArray($value)) {
                    // Only process objects that have an id or name property
                    if ($this->shouldDeduplicateObject($value)) {
                        // Check if we need to update the id
                        if (isset($value['id']) && $value['id'] !== null && isset($normalizedMappings[$value['id']])) {
                            $originalId  = $value['id'];
                            $value['id'] = $normalizedMappings[$value['id']];
                            $updates[]   = [
                                'original'   => $originalId,
                                'normalized' => $value['id'],
                                'path'       => $key . '.id',
                            ];
                            static::log("Updated object id at '$key.id': '$originalId' => '{$value['id']}'");
                        } // Check if we need to update the name (only if no id was present)
                        elseif (isset($value['name']) && $value['name'] !== null && isset($normalizedMappings[$value['name']])) {
                            $originalName  = $value['name'];
                            $value['name'] = $normalizedMappings[$value['name']];
                            $updates[]     = [
                                'original'   => $originalName,
                                'normalized' => $value['name'],
                                'path'       => $key . '.name',
                            ];
                            static::log("Updated object name at '$key.name': '$originalName' => '{$value['name']}'");
                        }
                    }
                    // Still recurse into the object for nested values
                    $this->updateClassificationLabels($value, $normalizedMappings, $updates);
                } else {
                    // Regular array - recurse into it
                    $this->updateClassificationLabels($value, $normalizedMappings, $updates);
                }
            }
            // Ignore boolean and numeric values - they don't need normalization
        }
    }

    /**
     * Post-process arrays to ensure uniqueness and proper formatting
     *
     * @param Collection $artifacts
     * @return void
     */
    protected function postProcessArrays(Collection $artifacts): void
    {
        static::log("Post-processing arrays for uniqueness and proper formatting");

        $totalFixes = 0;

        foreach($artifacts as $artifact) {
            $classification = $artifact->meta['classification'] ?? null;
            if (!$classification) {
                continue;
            }

            $fixes = [];
            $this->processArraysInClassification($classification, $fixes);

            if (!empty($fixes)) {
                $meta                   = $artifact->meta;
                $meta['classification'] = $classification;
                $artifact->meta         = $meta;
                $artifact->save();

                static::log("=== Array fixes for artifact {$artifact->id} ===");
                foreach($fixes as $fix) {
                    static::log("  Fixed array at '{$fix['path']}': {$fix['before']} => {$fix['after']}");
                    $totalFixes++;
                }
            }
        }

        if ($totalFixes > 0) {
            static::log("Applied $totalFixes array fixes across all artifacts");
        } else {
            static::log("No array fixes needed");
        }
    }

    /**
     * Recursively process arrays in classification data
     *
     * @param array &$classification
     * @param array &$fixes
     * @param string $path
     * @return void
     */
    protected function processArraysInClassification(array &$classification, array &$fixes, string $path = ''): void
    {
        foreach($classification as $key => &$value) {
            $currentPath = $path ? "$path.$key" : $key;

            if (is_array($value)) {
                // Check if this is an indexed array (list)
                if (!$this->isAssociativeArray($value)) {
                    // This is a list - ensure unique values (handle complex values)
                    $originalArray = $value;
                    $uniqueArray   = $this->getUniqueArrayValues($value);

                    if (count($originalArray) !== count($uniqueArray) || $originalArray !== $uniqueArray) {
                        $value   = $uniqueArray;
                        $fixes[] = [
                            'path'   => $currentPath,
                            'before' => json_encode($originalArray),
                            'after'  => json_encode($uniqueArray),
                        ];
                    }
                } else {
                    // This is an associative array (object) - recurse into it
                    $this->processArraysInClassification($value, $fixes, $currentPath);
                }
            }
        }
    }

    /**
     * Get unique values from array, handling complex data types
     */
    protected function getUniqueArrayValues(array $array): array
    {
        $unique = [];
        $seen   = [];

        foreach($array as $item) {
            // Serialize complex values for comparison
            $serialized = is_array($item) || is_object($item) ? json_encode($item) : $item;

            if (!in_array($serialized, $seen, true)) {
                $seen[]   = $serialized;
                $unique[] = $item;
            }
        }

        return array_values($unique);
    }
}
