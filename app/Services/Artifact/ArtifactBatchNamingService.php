<?php

namespace App\Services\Artifact;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

class ArtifactBatchNamingService
{
    use HasDebugLogging;

    /**
     * Intelligently name artifacts using LLM with context-aware batch processing
     *
     * @param  Collection|Artifact[]  $artifacts  Collection of artifacts to name
     * @param  string  $contextDescription  Description of the workflow context
     * @return Collection|Artifact[] Same artifacts collection with updated names
     */
    public function nameArtifacts(Collection $artifacts, string $contextDescription = ''): Collection
    {
        // Validate inputs
        if ($artifacts->isEmpty()) {
            static::logDebug('No artifacts to name, skipping');

            return $artifacts;
        }

        static::logDebug('Starting batch naming for ' . $artifacts->count() . " artifacts with context: {$contextDescription}");

        // Get configuration
        $maxBatchSize = config('ai.artifact_naming.max_batch_size');

        // Process in batches if collection is larger than max batch size
        if ($artifacts->count() > $maxBatchSize) {
            return $this->processBatches($artifacts, $contextDescription, $maxBatchSize);
        }

        // Process single batch
        return $this->processSingleBatch($artifacts, $contextDescription);
    }

    /**
     * Process artifacts in multiple batches
     */
    protected function processBatches(Collection $artifacts, string $contextDescription, int $maxBatchSize): Collection
    {
        static::logDebug("Processing in batches of {$maxBatchSize}");

        $processedArtifacts = collect();
        $chunks             = $artifacts->chunk($maxBatchSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            static::logDebug('Processing batch ' . ($chunkIndex + 1) . ' of ' . $chunks->count());
            $namedChunk         = $this->processSingleBatch($chunk, $contextDescription);
            $processedArtifacts = $processedArtifacts->merge($namedChunk);
        }

        return $processedArtifacts;
    }

    /**
     * Process a single batch of artifacts
     */
    protected function processSingleBatch(Collection $artifacts, string $contextDescription): Collection
    {
        try {
            // Build artifact context data
            $artifactData = $this->buildArtifactContextData($artifacts);

            // Generate names via LLM
            $nameMapping = $this->generateNamesViaLLM($artifactData, $contextDescription);

            if (!$nameMapping) {
                static::logDebug('LLM failed to generate names, keeping original names');

                return $artifacts;
            }

            // Apply names to artifacts
            $this->applyNamesToArtifacts($artifacts, $nameMapping);

            static::logDebug('Successfully named ' . count($nameMapping) . ' artifacts in batch');

            return $artifacts;

        } catch (\Exception $e) {
            static::logDebug('Error during batch naming: ' . $e->getMessage());
            static::logDebug('Keeping original artifact names as fallback');

            return $artifacts;
        }
    }

    /**
     * Build context data for artifacts (preview of content)
     */
    protected function buildArtifactContextData(Collection $artifacts): array
    {
        $previewLength = config('ai.artifact_naming.content_preview_length');
        $artifactData  = [];

        foreach ($artifacts as $artifact) {
            $contentPreview = $this->getArtifactContentPreview($artifact, $previewLength);

            $artifactData[] = [
                'artifact_id'     => $artifact->id,
                'current_name'    => $artifact->name,
                'content_preview' => $contentPreview,
                'has_text'        => !empty($artifact->text_content),
                'has_json'        => !empty($artifact->json_content),
                'has_files'       => $artifact->storedFiles()->count() > 0,
                'file_count'      => $artifact->storedFiles()->count(),
            ];
        }

        return $artifactData;
    }

    /**
     * Get a preview of artifact content for context
     */
    protected function getArtifactContentPreview(Artifact $artifact, int $maxLength): string
    {
        $preview = '';

        // Prefer text content
        if ($artifact->text_content) {
            $preview = substr($artifact->text_content, 0, $maxLength);
        } // Fall back to JSON content
        elseif ($artifact->json_content) {
            $jsonString = json_encode($artifact->json_content, JSON_PRETTY_PRINT);
            $preview    = substr($jsonString, 0, $maxLength);
        }

        // Truncate if needed
        if (strlen($preview) >= $maxLength) {
            $preview .= '...';
        }

        return $preview;
    }

    /**
     * Generate artifact names via LLM using JSON schema response
     *
     * @return array|null Mapping of artifact_id => new_name
     */
    protected function generateNamesViaLLM(array $artifactData, string $contextDescription): ?array
    {
        // Get agent configuration
        $agent = $this->getAgent();

        if (!$agent) {
            static::logDebug('No agent available for naming');

            return null;
        }

        // Build the prompt with JSON schema
        $prompt = $this->buildNamingPrompt($artifactData, $contextDescription);

        // System message
        $systemMessage = 'You are an intelligent artifact naming assistant. You generate clear, descriptive, professional names for workflow output artifacts based on their content and context. Always respond with valid JSON matching the specified schema.';

        // Get the response schema
        $responseSchema = $this->getArtifactNamingResponseSchema();

        // Build and run the thread
        try {
            $timeout   = config('ai.artifact_naming.timeout');
            $threadRun = AgentThreadBuilderService::for($agent)
                ->named('Artifact Batch Naming')
                ->withSystemMessage($systemMessage)
                ->withMessage($prompt)
                ->withResponseSchema($responseSchema)
                ->withTimeout($timeout)
                ->run();

            if (!$threadRun->lastMessage) {
                static::logDebug('Failed to get response from LLM');

                return null;
            }

            // Get JSON content from message
            $jsonContent = $threadRun->lastMessage->getJsonContent();

            if (!$jsonContent) {
                static::logDebug('Failed to get JSON content from message');

                return null;
            }

            // Validate response structure
            if (!isset($jsonContent['names']) || !is_array($jsonContent['names'])) {
                static::logDebug("Invalid response structure: missing 'names' array", [
                    'response_keys' => array_keys($jsonContent),
                ]);

                return null;
            }

            // Convert array of {artifact_id, name} objects to artifact_id => name map
            $nameMapping = [];
            foreach ($jsonContent['names'] as $item) {
                if (isset($item['artifact_id']) && isset($item['name'])) {
                    $nameMapping[(string)$item['artifact_id']] = $item['name'];
                }
            }

            return $nameMapping;

        } catch (\Exception $e) {
            static::logDebug('Error running agent thread: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Get or create the system agent for artifact naming
     */
    protected function getAgent(): Agent
    {
        return Agent::updateOrCreate(
            [
                'team_id' => null,
                'name'    => 'Artifact Batch Naming',
            ],
            [
                'model'       => config('ai.artifact_naming.model'),
                'description' => 'System agent for intelligently naming workflow output artifacts',
            ]
        );
    }

    /**
     * Build the naming prompt with JSON schema
     */
    protected function buildNamingPrompt(array $artifactData, string $contextDescription): string
    {
        $artifactsJson = json_encode($artifactData, JSON_PRETTY_PRINT);
        $artifactCount = count($artifactData);

        return <<<PROMPT
Context: {$contextDescription}

I need you to generate clear, descriptive, professional names for {$artifactCount} workflow output artifacts.

Artifacts to name:
{$artifactsJson}

Please analyze each artifact's content preview and generate an appropriate name that:
1. Clearly describes what the artifact contains
2. Is concise but informative (2-6 words ideal)
3. Uses professional, consistent naming conventions
4. Helps users quickly identify the artifact's purpose
5. Differentiates between artifacts when there are multiple of the same type

IMPORTANT NAMING GUIDELINES:
- For documents: Include document type (e.g., "Medical Summary", "Demand Letter", "Case Report")
- For data extracts: Describe the data type (e.g., "Patient Demographics", "Treatment History")
- For lists/collections: Use plural forms (e.g., "Provider Records", "Treatment Dates")
- For specific sections: Be specific (e.g., "Injury Description", "Damages Summary")
- Avoid generic names like "Output 1", "Document", "File"
- Use title case (e.g., "Medical Records Summary" not "medical records summary")

Generate appropriate names for all artifacts based on their content and context.
PROMPT;
    }

    /**
     * Apply generated names to artifacts
     *
     * @param  array  $nameMapping  artifact_id => new_name
     */
    protected function applyNamesToArtifacts(Collection $artifacts, array $nameMapping): void
    {
        $updateCount = 0;

        foreach ($artifacts as $artifact) {
            $artifactId = (string)$artifact->id;

            if (isset($nameMapping[$artifactId])) {
                $newName = $nameMapping[$artifactId];
                $oldName = $artifact->name;

                $artifact->name = $newName;
                $artifact->save();

                static::logDebug("Renamed artifact {$artifactId}: '{$oldName}' => '{$newName}'");
                $updateCount++;
            }
        }

        static::logDebug("Applied names to {$updateCount} artifacts");
    }

    /**
     * Get the JSON schema for artifact naming responses
     */
    protected function getArtifactNamingResponseSchema(): SchemaDefinition
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'names' => [
                    'type'        => 'array',
                    'description' => 'Array of artifact naming assignments',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'artifact_id' => [
                                'type'        => 'integer',
                                'description' => 'The ID of the artifact to name',
                            ],
                            'name'        => [
                                'type'        => 'string',
                                'description' => 'The new descriptive name for the artifact',
                            ],
                        ],
                        'required'             => ['artifact_id', 'name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required'             => ['names'],
            'additionalProperties' => false,
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => null,
            'name'    => 'Artifact Naming Response',
        ], [
            'description' => 'JSON schema for artifact naming responses',
            'schema'      => $schema,
        ]);
    }
}
