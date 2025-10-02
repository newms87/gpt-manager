<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Helpers\LockHelper;

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

        return Agent::updateOrCreate(
            [
                'team_id' => null, // System-level agent, not team-specific
                'name'    => $agentName,
            ],
            [
                'model'       => $model,
                'description' => 'Automated agent for data value deduplication and normalization',
                'api_options' => [
                    'reasoning' => [
                        'effort' => 'medium',
                    ],
                ],
                'retry_count' => 2,
            ]
        );
    }

    /**
     * Deduplicate a specific classification property across artifacts
     */
    public function deduplicateClassificationProperty(Collection $artifacts, string $property): void
    {
        static::log("Starting classification deduplication for property '$property' across " . $artifacts->count() . ' artifacts');

        $labelsToNormalize = $this->extractClassificationPropertyLabels($artifacts, $property);

        if (empty($labelsToNormalize)) {
            static::log("No classification labels found for property '$property'");

            return;
        }

        static::log('Found ' . count($labelsToNormalize) . " unique values for property '$property'");

        foreach($labelsToNormalize as $label) {
            static::log('  - ' . (strlen($label) > 80 ? substr($label, 0, 80) . '...' : $label));
        }

        $normalizedMappings = $this->getNormalizedClassificationMappings($labelsToNormalize);

        if (empty($normalizedMappings)) {
            static::log("No normalization mappings generated for property '$property'");

            return;
        }

        static::log('Generated ' . count($normalizedMappings) . " normalization mappings for '$property':");
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
     */
    public function createDeduplicationProcessesForTaskRun(TaskRun $taskRun): void
    {
        static::log("Creating deduplication processes for TaskRun {$taskRun->id}");

        $artifacts = $taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->get();

        if ($artifacts->isEmpty()) {
            static::log('No artifacts with classification metadata found');

            return;
        }

        $classificationProperties = $this->extractClassificationProperties($artifacts->first());

        if (empty($classificationProperties)) {
            static::log('No classification properties found to deduplicate');

            return;
        }

        static::log('Found classification properties: ' . implode(', ', $classificationProperties));

        $processesCreated = 0;
        foreach($classificationProperties as $property) {
            // Check if this property actually has labels to deduplicate
            $labels = $this->extractClassificationPropertyLabels($artifacts, $property);

            if (empty($labels)) {
                static::log("Skipping property '$property' - no classification labels found");
                continue;
            }

            static::log("Property '$property' has " . count($labels) . " labels - creating deduplication process");

            $taskRun->taskProcesses()->create([
                'activity' => "Classification Deduplication for $property w/ " . count($labels) . ' values',
                'name'     => "Classification Deduplication: $property",
                'meta'     => ['classification_property' => $property],
                'is_ready' => true,
            ]);

            $processesCreated++;
        }

        if ($processesCreated > 0) {
            $taskRun->updateRelationCounter('taskProcesses');
        }

        static::log("Created $processesCreated deduplication processes");
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
     */
    protected function getNormalizedClassificationMappings(array $labels): ?array
    {
        // Clean labels to prevent JSON parsing issues
        $cleanedLabels = array_map([$this, 'cleanLabelForLLM'], $labels);

        $prompt = $this->buildClassificationDeduplicationPrompt($cleanedLabels);

        $threadRepository = app(ThreadRepository::class);
        $agent            = $this->getOrCreateClassificationDeduplicationAgent();
        $agentThread      = $threadRepository->create($agent, 'Data Value Deduplication');

        $systemMessage = 'You are a data normalization assistant. Your job is to identify duplicate or similar values and normalize them to a consistent format.';
        $threadRepository->addMessageToThread($agentThread, $systemMessage);

        $threadRepository->addMessageToThread($agentThread, $prompt);

        // Get the response schema for deduplication
        $responseSchema = $this->getDeduplicationResponseSchema();

        // Run the thread with JSON schema response format
        $threadRun = (new AgentThreadService())
            ->withResponseFormat($responseSchema)
            ->withTimeout(config('ai.classification_deduplication.timeout'))
            ->run($agentThread);

        if (!$threadRun->lastMessage || !$threadRun->lastMessage->content) {
            static::log('Failed to get response from AI agent for classification deduplication');

            return null;
        }

        try {
            $jsonContent = $threadRun->lastMessage->getJsonContent();

            // JSON schema enforces the format, so mappings must exist
            if (!isset($jsonContent['mappings']) || !is_array($jsonContent['mappings'])) {
                throw new \Exception("Invalid response format: 'mappings' array is required");
            }

            $mappingsArray = $jsonContent['mappings'];

            // Convert new format to old format for compatibility
            $normalizedMappings = [];
            foreach($mappingsArray as $mapping) {
                if (!isset($mapping['correct']) || !isset($mapping['incorrect']) || !is_array($mapping['incorrect'])) {
                    throw new \Exception("Invalid mapping format: 'correct' and 'incorrect' fields are required");
                }

                // Clean the correct value returned by the LLM
                $correctValue = $this->cleanLabelForLLM($mapping['correct']);

                foreach($mapping['incorrect'] as $incorrectValue) {
                    // Clean the incorrect value returned by the LLM
                    $cleanIncorrectValue                      = $this->cleanLabelForLLM($incorrectValue);
                    $normalizedMappings[$cleanIncorrectValue] = $correctValue;
                }
            }

            return $normalizedMappings;
        } catch(\Exception $e) {
            static::log('Error parsing JSON response: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Build the classification deduplication prompt
     */
    protected function buildClassificationDeduplicationPrompt(array $labels): string
    {
        $labelsJson = json_encode(array_values($labels), JSON_PRETTY_PRINT);

        return <<<PROMPT
I have data values extracted from structured data. Some values may represent the same concept but with different wording, formatting, casing, or redundancy.

IMPORTANT: The values are individual string values, NOT full JSON objects. These strings represent any kind of data that may have variations in formatting, casing, or representation.

Please analyze these values and create a mapping of original values to normalized values. Focus on:

**For String Values:**
1. **Entity Recognition**: Identify when different strings refer to the same entity or concept
   - Be AGGRESSIVE in identifying entities that are clearly the same (organizations, people, places, etc.)
   - Look for core identifiers that remain consistent across variations (names, IDs, key attributes)
   - Example: "Microsoft Corp", "Microsoft Corporation", "MICROSOFT", "microsoft inc" should ALL map to the most complete version
   - Example: "Tokyo Station", "Tokyo Stn", "TOKYO STATION" should ALL map to "Tokyo Station"
2. **Identifier-Based Consolidation**:
   - If records contain the same core identifiers or unique attributes, consolidate them aggressively
   - Choose the most complete version that includes the most relevant information
   - Different formatting of the same information should be consolidated (dates, phone numbers, addresses, etc.)
3. **Location/Suffix Handling**: Handle location suffixes and organizational designations appropriately
   - Remove redundant location suffixes when they clearly refer to the same core entity
   - Preserve meaningful distinctions between actually different entities
4. **Consistent Formatting**: Normalize formatting to be consistent:
   - Proper nouns: Use appropriate title casing
   - Acronyms: Keep as uppercase when appropriate
   - Standardize punctuation, spacing, and formatting
5. **Completeness Priority**: When consolidating variations, choose the version with the most complete and useful information
6. **Aggressive Consolidation**: When strings clearly refer to the same underlying entity (same core name/identifier), consolidate them even if some details vary

**Important**: You are normalizing individual string values that could represent ANY type of data - people, places, organizations, products, categories, etc.

IMPORTANT RULES:
1. Only include entries where the value actually needs to be changed
2. If a value is already in its best normalized form, don't include it in the incorrect list
3. Choose the most complete and properly formatted version when consolidating
4. **BE AGGRESSIVE**: When strings clearly refer to the same underlying entity, consolidate them into ONE normalized value
5. **CRITICAL**: Group all incorrect variations together under a single correct value
6. Look for shared core identifiers (names, IDs, key attributes) as the basis for consolidation
7. When in doubt about whether records refer to the same entity, err on the side of consolidation if they share key identifying characteristics

Example response format:
{
  "mappings": [
    {
      "correct": "Apple Inc",
      "incorrect": ["APPLE", "apple inc", "Apple, Inc.", "Apple Inc."]
    },
    {
      "correct": "University Orthopedic Care",
      "incorrect": ["University Orthopedic Care, Palm Springs", "University Orthopedic Care, Tamarac", "University Ortho Care"]
    }
  ]
}

Do not include values that are already in their correct form and have no incorrect variations.

Data values to normalize:
$labelsJson
PROMPT;
    }

    /**
     * Apply normalized classification mappings to a specific property of artifacts
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
            $updatedValue  = $this->getPropertyValueUpdate($originalValue, $normalizedMappings);

            if ($updatedValue !== $originalValue) {
                LockHelper::acquire($artifact);
                try {
                    $meta                              = $artifact->meta;
                    $meta['classification'][$property] = $updatedValue;
                    $artifact->meta                    = $meta;
                    $artifact->save();
                } finally {
                    LockHelper::release($artifact);
                }

                $totalUpdates++;
                static::log("Updated artifact {$artifact->id} property '$property'");
            }
        }

        static::log("Applied $totalUpdates updates for property '$property'");
    }

    /**
     * Update a property value with normalized mappings
     */
    protected function getPropertyValueUpdate($value, array $normalizedMappings)
    {
        if (is_string($value)) {
            // Clean the original value for proper matching with cleaned mapping keys
            $cleanedValue = $this->cleanLabelForLLM(trim($value));

            if (isset($normalizedMappings[$cleanedValue])) {
                static::log("Updated property value: '$cleanedValue' => '{$normalizedMappings[$cleanedValue]}'");

                return $normalizedMappings[$cleanedValue];
            }

            // If no mapping found, return the cleaned version to ensure consistency
            return $cleanedValue;
        } elseif (is_array($value)) {
            if (is_associative_array($value)) {
                if (isset($value['id']) && isset($normalizedMappings[$value['id']])) {
                    $originalId  = $value['id'];
                    $value['id'] = $normalizedMappings[$originalId];
                    static::log("Updated object id: '$originalId' => '{$value['id']}'");
                } elseif (isset($value['name']) && isset($normalizedMappings[$value['name']])) {
                    $originalName  = $value['name'];
                    $value['name'] = $normalizedMappings[$originalName];
                    static::log("Updated object name: '$originalName' => '{$value['name']}'");
                }

                return $value;
            } else {
                return array_map(function ($item) use ($normalizedMappings) {
                    return $this->getPropertyValueUpdate($item, $normalizedMappings);
                }, $value);
            }
        }

        return $value;
    }

    /**
     * Clean a label to prevent JSON parsing issues when processed by the LLM
     */
    protected function cleanLabelForLLM(string $label): string
    {
        // CRITICAL: First unescape forward slashes that cause API timeouts
        // Convert escaped forward slashes back to normal forward slashes
        $label = str_replace('\\/', '/', $label);

        // Remove control characters and normalize whitespace
        $label = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $label);

        // Replace other problematic characters that could break JSON parsing
        // Note: We handle forward slashes separately above, so don't replace them here
        $label = str_replace(['\\', '"', "'", "\r", "\n", "\t"], [' ', ' ', ' ', ' ', ' ', ' '], $label);

        // Collapse multiple spaces into single space
        $label = preg_replace('/\s+/', ' ', $label);

        // Trim whitespace
        $label = trim($label);

        // If label is empty after cleaning, return a placeholder
        if (empty($label)) {
            return '[empty]';
        }

        // Limit length to prevent extremely long values
        if (strlen($label) > 250) {
            $label = substr($label, 0, 247) . '...';
        }

        return $label;
    }

    /**
     * Get the JSON schema for deduplication responses
     */
    protected function getDeduplicationResponseSchema(): SchemaDefinition
    {
        // JsonSchemaService expects object-based schemas, so wrap our array in an object
        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'mappings' => [
                    'type'        => 'array',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'correct'   => [
                                'type'        => 'string',
                                'description' => 'The normalized/correct value',
                            ],
                            'incorrect' => [
                                'type'        => 'array',
                                'items'       => [
                                    'type' => 'string',
                                ],
                                'description' => 'Array of incorrect variations that should map to the correct value',
                            ],
                        ],
                        'required'             => ['correct', 'incorrect'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Array of deduplication mappings',
                ],
            ],
            'required'             => ['mappings'],
            'additionalProperties' => false,
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => null, // System-level schema, not team-specific
            'name'    => 'Classification Deduplication Response',
            'type'    => 'DeduplicationResponse',
        ], [
            'description'   => 'JSON schema for classification deduplication responses',
            'schema'        => $schema,
            'schema_format' => SchemaDefinition::FORMAT_JSON,
        ]);
    }
}
