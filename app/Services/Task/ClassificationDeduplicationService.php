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
    protected function getOrCreateClassificationDeduplicationAgent(): Agent
    {
        $config    = config('ai.classification_deduplication');
        $agentName = $config['agent_name'];
        $model     = $config['model'];

        // Find existing agent by name and model (no team context needed)
        $agent = Agent::whereNull('team_id')
            ->where('name', $agentName)
            ->where('model', $model)
            ->first();

        if (!$agent) {
            // Create agent directly with explicit null team_id
            $agent = Agent::create([
                'name'        => $agentName,
                'model'       => $model,
                'description' => 'Automated agent for classification label deduplication',
                'api_options' => ['temperature' => 0],
                'team_id'     => null,
                'retry_count' => 2,
            ]);
            static::log("Created new classification deduplication agent: $agent->name (ID: $agent->id)");
        }

        return $agent;
    }

    /**
     * Categorize labels for better logging display
     */
    protected function categorizeLabelsForLogging(array $labels): array
    {
        $categorized = [
            'Provider Names'     => [],
            'Professional Names' => [],
            'JSON Objects'       => [],
            'Other'              => [],
        ];

        foreach($labels as $label) {
            if (str_starts_with($label, '{')) {
                $categorized['JSON Objects'][] = $label;
            } elseif (preg_match('/\b(Dr\.|MD|DO|Center|Hospital|Care|Clinic|Medical)\b/i', $label)) {
                if (preg_match('/\bDr\./i', $label)) {
                    $categorized['Professional Names'][] = $label;
                } else {
                    $categorized['Provider Names'][] = $label;
                }
            } else {
                $categorized['Other'][] = $label;
            }
        }

        // Remove empty categories
        return array_filter($categorized, fn($items) => !empty($items));
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

        static::log("Found " . count($labelsToNormalize) . " unique classification labels to process");

        // Group labels by apparent category for better logging
        $categorizedLabels = $this->categorizeLabelsForLogging($labelsToNormalize);

        foreach($categorizedLabels as $category => $labels) {
            info("Deduplicating category '$category' with " . count($labels) . " variations");
            foreach($labels as $label) {
                static::log("  - " . (strlen($label) > 80 ? substr($label, 0, 80) . "..." : $label));
            }
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
                    // Treat the whole object as a JSON string for deduplication
                    $jsonString = json_encode($value);
                    if ($jsonString) {
                        $labels[] = $jsonString;
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
        $agentThread      = $threadRepository->create($agent, 'Classification Label Deduplication');

        $systemMessage = 'You are a classification label normalization assistant. Your job is to identify duplicate or similar labels and normalize them to a consistent format. Always respond with valid JSON.';
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
I have classification labels extracted from multiple document processing tasks. Some labels may represent the same concept but with different wording, formatting, or redundancy.

The labels include both simple strings and JSON objects that represent structured data.

Classification labels to normalize:
$labelsJson

Please analyze these labels and create a mapping of original labels to normalized labels. Focus on:

**For String Labels:**
1. **Entity Recognition**: Identify when different strings refer to the same entity (e.g., "CNCC", "Chiropractic Natural Care Center", "CNCC Chiropractic Natural Care Center" all refer to the same provider)
2. **Proper Name Casing**: For proper names (businesses, organizations, people), preserve appropriate title casing:
   - Business names: "Chiropractic Natural Care Center" (not "chiropractic natural care center")
   - Acronyms: Keep as uppercase "CNCC" or "NYC"
   - Person names: "Dr. Smith" (not "dr. smith")
3. **Generic terms**: Convert generic terms to lowercase (e.g., "emergency medicine", "family practice")
4. **Redundancy removal**: Remove duplicate concepts while preserving the most complete/formal version
5. **Address normalization**: Standardize addresses (e.g., "123 Main St" vs "123 main street" → "123 Main Street")

**For JSON Object Labels:**
1. **Name normalization**: Normalize similar names within objects (e.g., "Dan Newman" vs "Danny Newman" → choose one)
2. **Field value normalization**: Normalize similar values (e.g., "Primary" vs "Main" → choose one)
3. **Maintain JSON structure**: Return valid JSON objects with normalized values
4. **Consistent formatting**: Use consistent key-value formatting
5. **Array handling**: For arrays, normalize individual items but keep distinct concepts separate

IMPORTANT RULES:
1. Only include entries where the label actually needs to be changed
2. If a label is already in its best normalized form, don't include it
3. For entity names, choose the most complete and properly formatted version:
   - Prefer full names over acronyms when consolidating (unless acronym is more commonly used)
   - Maintain proper title casing for names of businesses, people, and places
4. For generic descriptive terms (not proper names), use lowercase
5. **CRITICAL**: For arrays, do NOT collapse different concepts into the same value
6. **CRITICAL**: Ensure arrays contain unique values - no duplicates

Example response format:
{
  "CNCC": "Chiropractic Natural Care Center",
  "cncc": "Chiropractic Natural Care Center",
  "emergency medicine": "emergency medicine",
  "EMERGENCY MEDICINE": "emergency medicine",
  "Dr Smith Family Practice": "Dr. Smith Family Practice",
  "DR. SMITH FAMILY PRACTICE": "Dr. Smith Family Practice",
  "123 main street": "123 Main Street",
  "{\"name\":\"Danny Newman\",\"role\":\"Main\"}": "{\"name\":\"Dan Newman\",\"role\":\"Primary\"}"
}

Return only the JSON object with mappings for labels that need normalization.
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
                    static::log("  '{$update['original']}' => '{$update['normalized']}'");
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
                    // Treat the whole object as a JSON string for normalization
                    $jsonString = json_encode($value);
                    if ($jsonString && isset($normalizedMappings[$jsonString])) {
                        $normalizedJson = $normalizedMappings[$jsonString];
                        try {
                            $normalizedObject = json_decode($normalizedJson, true);
                            if (is_array($normalizedObject)) {
                                $value     = $normalizedObject;
                                $updates[] = [
                                    'original'   => $jsonString,
                                    'normalized' => $normalizedJson,
                                ];
                                static::log("Updated classification object: '$jsonString' => '$normalizedJson'");
                            }
                        } catch(\Exception $e) {
                            static::log("Failed to decode normalized object JSON: " . $e->getMessage());
                        }
                    }
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
