<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Repositories\AgentRepository;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

class ClassificationDeduplicationService
{
    use HasDebugLogging;

    protected Agent $agent;

    public function __construct()
    {
        $this->agent = $this->getOrCreateClassificationDeduplicationAgent();
    }

    /**
     * Get or create the classification deduplication agent based on config
     */
    protected function getOrCreateClassificationDeduplicationAgent(): Agent
    {
        $config = config('ai.classification_deduplication');
        $agentName = $config['agent_name'];
        $model = $config['model'];
        
        // Find existing agent by name and model
        $agent = Agent::where('team_id', team()->id)
            ->where('name', $agentName)
            ->where('model', $model)
            ->first();
            
        if (!$agent) {
            // Create new agent using repository pattern
            $agentRepo = app(AgentRepository::class);
            $agent = $agentRepo->createAgent([
                'name' => $agentName,
                'model' => $model,
                'description' => 'Automated agent for classification label deduplication',
                'api_options' => [
                    'temperature' => 0.3, // Lower temperature for more consistent results
                ],
            ]);
            
            static::log("Created new classification deduplication agent: {$agent->name} (ID: {$agent->id})");
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

        static::log("Found " . count($labelsToNormalize) . " unique classification labels to process");

        $normalizedMappings = $this->getNormalizedClassificationMappings($labelsToNormalize);

        if (empty($normalizedMappings)) {
            static::log("No normalization mappings generated");
            return;
        }

        $this->applyNormalizedClassificationsToArtifacts($artifacts, $normalizedMappings);

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

        foreach ($artifacts as $artifact) {
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
     * @param array $classification
     * @param array &$labels
     * @return void
     */
    protected function extractLabelsFromClassification(array $classification, array &$labels): void
    {
        foreach ($classification as $key => $value) {
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
        $agentThread = $threadRepository->create($this->agent, 'Classification Label Deduplication');
        
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
        } catch (\Exception $e) {
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
1. **Case normalization**: Convert to lowercase consistently
2. **Redundancy removal**: Remove duplicate concepts (e.g., "Dairy, milk, cream, eggs, meat, vegetables" → "dairy, eggs, meat, vegetables")
3. **Formatting consistency**: Standardize punctuation and spacing
4. **Concept consolidation**: Group similar concepts under one label

**For JSON Object Labels:**
1. **Name normalization**: Normalize similar names within objects (e.g., "Dan Newman" vs "Danny Newman" → choose one)
2. **Field value normalization**: Normalize similar values (e.g., "Primary" vs "Main" → choose one)
3. **Maintain JSON structure**: Return valid JSON objects with normalized values
4. **Consistent formatting**: Use consistent key-value formatting

IMPORTANT RULES:
1. Only include entries where the label actually needs to be changed
2. If a label is already in its best normalized form, don't include it
3. For string labels: prefer lowercase, concise, descriptive versions
4. For JSON objects: maintain the same structure but normalize the values within
5. Remove redundant items from string lists (e.g., if "dairy" is present, remove "milk" and "cream")
6. For objects with similar people/entities, choose the most complete name consistently

Example response format:
{
  "Dairy, milk, cream, eggs, meat, vegetables": "dairy, eggs, meat, vegetables",
  "FOOD CATEGORY": "food category",
  "Medical - Primary Care": "medical primary care",
  "{\"name\":\"Dan Newman\",\"role\":\"Primary\"}": "{\"name\":\"Dan Newman\",\"role\":\"Primary\"}",
  "{\"name\":\"Danny Newman\",\"role\":\"Main\"}": "{\"name\":\"Dan Newman\",\"role\":\"Primary\"}",
  "{\"professional\":{\"name\":\"Dr. Smith\",\"role\":\"Primary Care\"}}": "{\"professional\":{\"name\":\"Dr. Smith\",\"role\":\"Primary Care\"}}"
}

Return only the JSON object with mappings for labels that need normalization.
PROMPT;
    }

    /**
     * Apply normalized classifications to all artifacts
     *
     * @param Collection $artifacts
     * @param array $normalizedMappings
     * @return void
     */
    protected function applyNormalizedClassificationsToArtifacts(Collection $artifacts, array $normalizedMappings): void
    {
        if (empty($normalizedMappings)) {
            static::log("No normalization mappings to apply");
            return;
        }

        $totalUpdates = 0;

        foreach ($artifacts as $artifact) {
            $classification = $artifact->meta['classification'] ?? null;
            if (!$classification) {
                continue;
            }

            $updates = [];
            $this->updateClassificationLabels($classification, $normalizedMappings, $updates);

            if (!empty($updates)) {
                $meta = $artifact->meta;
                $meta['classification'] = $classification;
                $artifact->meta = $meta;
                $artifact->save();

                static::log("=== Updated artifact {$artifact->id} ===");
                foreach ($updates as $update) {
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
     * @param array $normalizedMappings
     * @param array &$updates
     * @return void
     */
    protected function updateClassificationLabels(array &$classification, array $normalizedMappings, array &$updates): void
    {
        foreach ($classification as $key => &$value) {
            if (is_string($value)) {
                $trimmedValue = trim($value);
                if (isset($normalizedMappings[$trimmedValue])) {
                    $normalizedValue = $normalizedMappings[$trimmedValue];
                    $value = $normalizedValue;
                    $updates[] = [
                        'original' => $trimmedValue,
                        'normalized' => $normalizedValue
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
                                $value = $normalizedObject;
                                $updates[] = [
                                    'original' => $jsonString,
                                    'normalized' => $normalizedJson
                                ];
                                static::log("Updated classification object: '$jsonString' => '$normalizedJson'");
                            }
                        } catch (\Exception $e) {
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
}