<?php

namespace App\Services\Demand;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Demand\TemplateVariable;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Resources\TeamObject\TeamObjectForAgentsResource;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\AgentThread\ArtifactFilterService;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

class TemplateVariableResolutionService
{
    use HasDebugLogging;

    /**
     * Resolve all variables for a template, separating AI-mapped from pre-resolvable
     *
     * @param Collection<TemplateVariable> $templateVariables
     * @param Collection<Artifact>         $artifacts
     * @param TeamObject|null              $teamObject
     * @param int                          $teamId Team ID for context (required for AI resolution)
     * @return array ['values' => [name => value], 'title' => string]
     */
    public function resolveVariables(
        Collection  $templateVariables,
        Collection  $artifacts,
        ?TeamObject $teamObject = null,
        int         $teamId = null
    ): array
    {
        // Sort artifacts by name for consistent ordering in results
        $artifacts = $artifacts->sortBy('name')->values();

        static::log('Starting variable resolution', [
            'variable_count' => $templateVariables->count(),
            'variable_names' => $templateVariables->pluck('name')->toArray(),
            'artifact_count' => $artifacts->count(),
            'team_object_id' => $teamObject?->id,
            'team_id'        => $teamId,
        ]);

        $aiVariables       = collect();
        $preResolvedValues = [];

        // Separate variables by resolution type
        foreach($templateVariables as $variable) {
            if ($variable->isAiMapped()) {
                $aiVariables->push($variable);
            } else {
                // Pre-resolve artifact and TeamObject variables
                $value                              = $this->resolveVariable($variable, $artifacts, $teamObject);
                $preResolvedValues[$variable->name] = $value;
            }
        }

        static::log('Pre-resolved variables', [
            'pre_resolved_count' => count($preResolvedValues),
            'ai_variable_count'  => $aiVariables->count(),
        ]);

        // If we have AI variables, invoke AI with pre-resolved context
        if ($aiVariables->isNotEmpty()) {
            $aiResults = $this->resolveWithAi($aiVariables, $artifacts, $preResolvedValues, $teamId);

            static::log('AI resolution complete', [
                'ai_resolved_count' => count($aiResults['values'] ?? []),
                'title'             => $aiResults['title'] ?? '',
            ]);

            return [
                'values' => array_merge($preResolvedValues, $aiResults['values']),
                'title'  => $aiResults['title'] ?? '',
            ];
        }

        static::log('Variable resolution complete (no AI variables)');

        return [
            'values' => $preResolvedValues,
            'title'  => '',
        ];
    }

    /**
     * Resolve a single template variable
     */
    public function resolveVariable(
        TemplateVariable $variable,
        Collection       $artifacts,
        ?TeamObject      $teamObject = null
    ): string
    {
        static::log('Resolving single variable', [
            'variable_id'   => $variable->id,
            'variable_name' => $variable->name,
            'mapping_type'  => $variable->mapping_type,
        ]);

        $values = match ($variable->mapping_type) {
            TemplateVariable::MAPPING_TYPE_ARTIFACT => $this->resolveFromArtifacts($variable, $artifacts),
            TemplateVariable::MAPPING_TYPE_TEAM_OBJECT => $this->resolveFromTeamObject($variable, $teamObject),
            TemplateVariable::MAPPING_TYPE_AI => throw new ValidationError(
                'AI-mapped variables must be resolved through resolveVariables() method',
                400
            ),
            default => throw new ValidationError("Unknown mapping type: {$variable->mapping_type}", 400),
        };

        $result = $this->combineValues(
            $values,
            $variable->multi_value_strategy,
            $variable->multi_value_separator
        );

        static::log('Variable resolved', [
            'variable_name'  => $variable->name,
            'result_length'  => strlen($result),
            'result_preview' => substr($result, 0, 200),
        ]);

        return $result;
    }

    /**
     * Resolve variable from artifacts
     */
    protected function resolveFromArtifacts(TemplateVariable $variable, Collection $artifacts): array
    {
        static::log('Resolving from artifacts', [
            'variable_id'                  => $variable->id,
            'variable_name'                => $variable->name,
            'artifact_categories'          => $variable->artifact_categories,
            'has_fragment_selector'        => !empty($variable->artifact_fragment_selector),
            'artifact_count_before_filter' => $artifacts->count(),
        ]);

        // Filter artifacts by categories if specified (use Collection filtering on meta.__category)
        if (!empty($variable->artifact_categories)) {
            $artifacts = $artifacts->filter(function (Artifact $artifact) use ($variable) {
                return in_array($artifact->meta['__category'] ?? null, $variable->artifact_categories);
            });

            static::log('Filtered artifacts by categories using meta.__category', [
                'artifact_count_after_filter' => $artifacts->count(),
                'filtered_by_categories'      => $variable->artifact_categories,
            ]);
        }

        $values = [];

        // If no fragment selector, use text_content (primary) or name (fallback)
        if (empty($variable->artifact_fragment_selector)) {
            $values = $artifacts->map(function (Artifact $artifact) {
                // Use text_content as primary, name as fallback
                $value = $artifact->text_content ?: $artifact->name;
                static::log('Extracted artifact value', [
                    'artifact_id'       => $artifact->id,
                    'used_text_content' => !empty($artifact->text_content),
                    'value_length'      => strlen($value ?? ''),
                ]);

                return $value;
            })->filter()->values()->toArray();

            static::log('Extracted artifact content (no fragment selector)', [
                'values_count'        => count($values),
                'sample_value_length' => isset($values[0]) ? strlen($values[0]) : 0,
            ]);

            return $values;
        }

        // Apply artifact_fragment_selector to extract data
        foreach($artifacts as $artifact) {
            $extractedValues = $this->extractFromArtifact($artifact, $variable->artifact_fragment_selector);
            $values          = array_merge($values, $extractedValues);
        }

        static::log('Extracted artifact content (with fragment selector)', [
            'values_count'        => count($values),
            'sample_value_length' => isset($values[0]) ? strlen((string)$values[0]) : 0,
        ]);

        return $values;
    }

    /**
     * Extract values from artifact using fragment selector
     */
    protected function extractFromArtifact(Artifact $artifact, array $fragmentSelector): array
    {
        $filterService = app(ArtifactFilterService::class)->setArtifact($artifact);

        // Determine what field to extract from
        $field = $fragmentSelector['field'] ?? 'json_content';

        $data = match ($field) {
            'json_content' => $filterService->getFilteredJson(),
            'meta' => $filterService->getFilteredMeta(),
            'text_content' => $filterService->getTextContent(),
            default => null,
        };

        if (!$data) {
            return [];
        }

        // If we have a nested path in the fragment selector, apply it
        if (!empty($fragmentSelector['children'])) {
            $jsonSchemaService = app(JsonSchemaService::class)->useId();
            $data              = $jsonSchemaService->filterDataByFragmentSelector(
                is_string($data) ? [] : $data,
                $fragmentSelector
            );
        }

        return $this->flattenToValues($data);
    }

    /**
     * Resolve variable from TeamObject
     */
    protected function resolveFromTeamObject(TemplateVariable $variable, ?TeamObject $teamObject): array
    {
        if (!$teamObject) {
            return [];
        }

        // Load SchemaAssociation
        $schemaAssociation = $variable->teamObjectSchemaAssociation;
        if (!$schemaAssociation) {
            throw new ValidationError(
                "TeamObject variable '{$variable->name}' has no schema association configured",
                422
            );
        }

        // Load TeamObject using TeamObjectForAgentsResource
        $teamObjectData = TeamObjectForAgentsResource::make($teamObject);

        // Apply schema definition + fragment using JsonSchemaService
        $jsonSchemaService = app(JsonSchemaService::class);
        $fragmentSelector  = $schemaAssociation->schemaFragment?->fragment_selector;

        if ($fragmentSelector) {
            $teamObjectData = $jsonSchemaService->filterDataByFragmentSelector($teamObjectData, $fragmentSelector);
        }

        // Flatten result to scalar values
        return $this->flattenToValues($teamObjectData);
    }

    /**
     * Resolve variables using AI
     *
     * @param Collection<TemplateVariable> $aiVariables
     * @param Collection<Artifact>         $artifacts
     * @param array                        $preResolvedValues
     * @param int                          $teamId Team ID for context
     * @return array ['values' => [name => value], 'title' => string]
     */
    protected function resolveWithAi(
        Collection $aiVariables,
        Collection $artifacts,
        array      $preResolvedValues,
        int        $teamId
    ): array
    {
        $instructions = $this->buildAiInstructions($aiVariables, $preResolvedValues);
        $agent        = $this->findOrCreateVariableExtractorAgent();

        // Get the response schema
        $responseSchema = $this->getVariableResolutionResponseSchema();

        // Create artifact filter
        $artifactFilter = new ArtifactFilter(
            includeText: true,
            includeFiles: false,
            includeJson: true,
            includeMeta: false
        );

        // Build and run the agent thread
        $threadRun = AgentThreadBuilderService::for($agent, $teamId)
            ->named('Template Variable Resolution')
            ->withArtifacts($artifacts, $artifactFilter)
            ->withMessage($instructions)
            ->withResponseSchema($responseSchema)
            ->withTimeout(config('ai.variable_extraction.timeout'))
            ->run();

        if (!$threadRun->isCompleted()) {
            throw new ValidationError('AI variable resolution failed: ' . $threadRun->error_message, 500);
        }

        // Parse response
        $responseData = $threadRun->lastMessage->getJsonContent();
        $variables    = $responseData['variables'] ?? null;
        $title        = $responseData['title'] ?? '';

        if (!$variables) {
            throw new ValidationError('AI variable resolution returned invalid response format', 500);
        }

        static::log('AI returned variable resolution response', [
            'variable_keys' => $variables,
            'title'         => $title,
        ]);

        // Convert array of {name, value} objects to name => value map
        $variableValues = [];
        foreach($variables as $variable) {
            $variableValue = $variable['value'] ?? null;
            if ($variableValue === null) {
                $variableValues[$variable['name']] = '{' . $variable['name'] . '}';
            } else {
                $variableValues[$variable['name']] = $variable['value'];
            }
        }

        return [
            'values' => $variableValues,
            'title'  => $title,
        ];
    }

    /**
     * Build AI instructions for variable extraction
     */
    protected function buildAiInstructions(Collection $aiVariables, array $preResolvedValues): string
    {
        $instructions = "Extract the following variables from the provided artifacts:\n\n";

        foreach($aiVariables as $variable) {
            $instructions .= "**{$variable->name}**";
            if ($variable->description) {
                $instructions .= ": {$variable->description}";
            }
            if ($variable->ai_instructions) {
                $instructions .= "\n  Instructions: {$variable->ai_instructions}";
            }
            $instructions .= "\n\n";
        }

        // Add pre-resolved variables as context
        if (!empty($preResolvedValues)) {
            $instructions .= "\nPre-resolved variables for context:\n";
            foreach($preResolvedValues as $name => $value) {
                $instructions .= "- {$name}: {$value}\n";
            }
            $instructions .= "\n";
        }

        $instructions .= "Also generate an appropriate title for this demand based on the extracted variables. ";
        $instructions .= "Generate appropriate values for all variables and create a descriptive title based on the extracted information.";
        $instructions .= "\nUse human readable formats for humans in the USA (ie: dates like May 25th, 2025, currency like $1,234.56, etc.).\n\n";

        return $instructions;
    }

    /**
     * Find or create the Template Variable Extractor agent
     */
    protected function findOrCreateVariableExtractorAgent(): Agent
    {
        $agent = Agent::where('name', 'Template Variable Extractor')
            ->whereNull('team_id')
            ->first();

        if (!$agent) {
            $config = config('ai.variable_extraction');
            $agent  = Agent::create([
                'name'        => $config['name'],
                'team_id'     => null,
                'model'       => $config['model'],
                'api_options' => $config['api_options'] ?? [],
            ]);
        }

        return $agent;
    }

    /**
     * Combine multiple values into a single string
     */
    protected function combineValues(array $values, string $strategy, string $separator): string
    {
        if (empty($values)) {
            return '';
        }

        return match ($strategy) {
            TemplateVariable::STRATEGY_FIRST => $this->convertToString($values[0] ?? ''),
            TemplateVariable::STRATEGY_UNIQUE => implode($separator, array_unique(array_map([$this, 'convertToString'], $values))),
            TemplateVariable::STRATEGY_JOIN => implode($separator, array_map([$this, 'convertToString'], $values)),
            default => $this->convertToString($values[0] ?? ''),
        };
    }

    /**
     * Flatten nested arrays to extract scalar values only
     */
    protected function flattenToValues(mixed $data): array
    {
        if (is_scalar($data)) {
            return [$data];
        }

        if (!is_array($data)) {
            return [];
        }

        $values = [];
        foreach($data as $value) {
            if (is_scalar($value)) {
                $values[] = $value;
            } elseif (is_array($value)) {
                $values = array_merge($values, $this->flattenToValues($value));
            }
        }

        return $values;
    }

    /**
     * Convert any value to string safely
     */
    protected function convertToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return '';
    }

    /**
     * Get the JSON schema for variable resolution responses
     */
    protected function getVariableResolutionResponseSchema(): SchemaDefinition
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'variables' => [
                    'type'        => 'array',
                    'description' => 'Array of extracted variable values',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'name'  => [
                                'type'        => 'string',
                                'description' => 'The variable name',
                            ],
                            'value' => [
                                'type'        => 'string',
                                'description' => 'The extracted value for the variable',
                            ],
                        ],
                        'required'             => ['name', 'value'],
                        'additionalProperties' => false,
                    ],
                ],
                'title'     => [
                    'type'        => 'string',
                    'description' => 'Generated title for the demand',
                ],
            ],
            'required'             => ['variables', 'title'],
            'additionalProperties' => false,
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => null,
            'name'    => 'Template Variable Resolution Response',
        ], [
            'description' => 'JSON schema for template variable resolution responses',
            'schema'      => $schema,
        ]);
    }
}
