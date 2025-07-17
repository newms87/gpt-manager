<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
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
     * Deduplicate a specific classification property across artifacts
     */
    public function deduplicateClassificationProperty(Collection $artifacts, string $property): void
    {
        static::log("Starting classification deduplication for property '$property' across " . $artifacts->count() . " artifacts");

        $labelsToNormalize = $this->extractClassificationPropertyLabels($artifacts, $property);

        if (empty($labelsToNormalize)) {
            static::log("No classification labels found for property '$property'");

            return;
        }

        static::log("Found " . count($labelsToNormalize) . " unique values for property '$property'");

        foreach($labelsToNormalize as $label) {
            static::log("  - " . (strlen($label) > 80 ? substr($label, 0, 80) . "..." : $label));
        }

        $normalizedMappings = $this->getNormalizedClassificationMappings($labelsToNormalize);

        if (empty($normalizedMappings)) {
            static::log("No normalization mappings generated for property '$property'");

            return;
        }

        static::log("Generated " . count($normalizedMappings) . " normalization mappings for '$property':");
        foreach($normalizedMappings as $original => $normalized) {
            if (strlen($original) > 80) {
                static::log("  '" . substr($original, 0, 80) . "...' => '" . substr($normalized, 0, 80) . "...'");
            } else {
                static::log("  '$original' => '$normalized'");
            }
        }

        $this->applyNormalizedClassificationsToArtifactsProperty($artifacts, $normalizedMappings, $property);

        static::log("Classification deduplication completed for property '$property'");
    }

    /**
     * Create separate TaskProcesses for each classification property in the TaskRun
     *
     * @param TaskRun $taskRun
     * @return void
     */
    public function createDeduplicationProcessesForTaskRun(TaskRun $taskRun): void
    {
        static::log("Creating deduplication processes for TaskRun {$taskRun->id}");

        $artifact = $taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->first();

        if (!$artifact) {
            static::log("No artifacts with classification metadata found");

            return;
        }

        $classificationProperties = $this->extractClassificationProperties($artifact);

        if (empty($classificationProperties)) {
            static::log("No classification properties found to deduplicate");

            return;
        }

        static::log("Found classification properties: " . implode(', ', $classificationProperties));

        foreach($classificationProperties as $property) {
            $taskProcess = app(TaskProcess::class)->create([
                'name' => "Classification Deduplication: $property",
                'meta' => ['classification_property' => $property],
            ]);

            $taskRun->taskProcesses()->save($taskProcess);
        }

        static::log("Created " . count($classificationProperties) . " deduplication processes");
    }

    /**
     * Extract unique classification property names from artifacts
     */
    protected function extractClassificationProperties(Artifact $artifact): array
    {
        return array_keys($artifact->meta['classification'] ?? []);
    }

    /**
     * Extract classification labels for a specific property from artifact metadata
     *
     * @param Collection $artifacts
     * @param string     $property
     * @return array
     */
    protected function extractClassificationPropertyLabels(Collection $artifacts, string $property): array
    {
        $labels = [];

        foreach($artifacts as $artifact) {
            $propertyValue = $artifact->meta['classification'][$property] ?? null;
            if (!$propertyValue) {
                continue;
            }

            $this->extractLabelsFromValue($propertyValue, $labels);
        }

        return array_unique($labels);
    }

    /**
     * Extract labels from a specific value (string, array, or object)
     */
    protected function extractLabelsFromValue($value, array &$labels): void
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value) {
                $labels[] = $value;
            }
        } elseif (is_array($value)) {
            if (is_associative_array($value)) {
                if (!empty($value['id'])) {
                    $labels[] = $value['id'];
                } elseif (!empty($value['name'])) {
                    $labels[] = $value['name'];
                }
            } else {
                foreach($value as $item) {
                    $this->extractLabelsFromValue($item, $labels);
                }
            }
        }
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

            if (preg_match('/```(?:json)?\s*(\{.*?})\s*```/s', $content, $matches)) {
                $content = $matches[1];
            } elseif (preg_match('/\{(?:[^{}]|(?R))*}/s', $content, $matches)) {
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
     * Apply normalized classification mappings to a specific property of artifacts
     *
     * @param Collection $artifacts
     * @param array      $normalizedMappings
     * @param string     $property
     * @return void
     */
    protected function applyNormalizedClassificationsToArtifactsProperty(Collection $artifacts, array $normalizedMappings, string $property): void
    {
        if (empty($normalizedMappings)) {
            static::log("No normalization mappings to apply for property '$property'");

            return;
        }

        $totalUpdates = 0;

        foreach($artifacts as $artifact) {
            $classification = $artifact->meta['classification'] ?? null;
            if (!$classification || !isset($classification[$property])) {
                continue;
            }

            $originalValue = $classification[$property];
            $updatedValue  = $this->updatePropertyValue($originalValue, $normalizedMappings);

            if ($updatedValue !== $originalValue) {
                $meta                              = $artifact->meta;
                $meta['classification'][$property] = $updatedValue;
                $artifact->meta                    = $meta;
                $artifact->save();

                $totalUpdates++;
                static::log("Updated artifact {$artifact->id} property '$property'");
            }
        }

        static::log("Applied $totalUpdates updates for property '$property'");
    }

    /**
     * Update a property value with normalized mappings
     *
     * @param mixed $value
     * @param array $normalizedMappings
     * @return mixed
     */
    protected function updatePropertyValue($value, array $normalizedMappings)
    {
        if (is_string($value)) {
            $trimmedValue = trim($value);
            if (isset($normalizedMappings[$trimmedValue])) {
                static::log("Updated property value: '$trimmedValue' => '{$normalizedMappings[$trimmedValue]}'");

                return $normalizedMappings[$trimmedValue];
            }

            return $value;
        } elseif (is_array($value)) {
            if (is_associative_array($value)) {
                if (isset($value['id']) && isset($normalizedMappings[$value['id']])) {
                    $originalId = $value['id'];
                    $value['id'] = $normalizedMappings[$originalId];
                    static::log("Updated object id: '$originalId' => '{$value['id']}'");
                } elseif (isset($value['name']) && isset($normalizedMappings[$value['name']])) {
                    $originalName = $value['name'];
                    $value['name'] = $normalizedMappings[$originalName];
                    static::log("Updated object name: '$originalName' => '{$value['name']}'");
                }

                return $value;
            } else {
                return array_map(function ($item) use ($normalizedMappings) {
                    return $this->updatePropertyValue($item, $normalizedMappings);
                }, $value);
            }
        }

        return $value;
    }
}
