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
                'api_options' => ['temperature' => 0],
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
        $prompt = $this->buildClassificationDeduplicationPrompt($labels);

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

                foreach($mapping['incorrect'] as $incorrectValue) {
                    $normalizedMappings[$incorrectValue] = $mapping['correct'];
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
2. If a value is already in its best normalized form, don't include it in the incorrect list
3. Choose the most complete and properly formatted version when consolidating
4. Maintain consistency in formatting and casing
5. **CRITICAL**: Do NOT collapse different concepts into the same value
6. **CRITICAL**: Group all incorrect variations together under a single correct value

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
            $trimmedValue = trim($value);
            if (isset($normalizedMappings[$trimmedValue])) {
                static::log("Updated property value: '$trimmedValue' => '{$normalizedMappings[$trimmedValue]}'");

                return $normalizedMappings[$trimmedValue];
            }

            return $value;
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
        ], [
            'description' => 'JSON schema for classification deduplication responses',
            'schema'      => $schema,
        ]);
    }
}
