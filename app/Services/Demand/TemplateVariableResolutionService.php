<?php

namespace App\Services\Demand;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Demand\TemplateVariable;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Resources\TeamObject\TeamObjectForAgentsResource;
use App\Services\AgentThread\AgentThreadService;
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
        $agent        = $this->findOrCreateVariableExtractorAgent($teamId);
        $thread       = $this->createAgentThread($agent, $teamId, $instructions, $artifacts);

        // Get the response schema
        $responseSchema = $this->getVariableResolutionResponseSchema();

        // Run the agent
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($responseSchema)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            throw new ValidationError('AI variable resolution failed: ' . $threadRun->error_message, 500);
        }

        // Parse response
        $response     = $this->getAssistantResponse($thread);
        $responseData = $this->parseAiResponse($response);

        // Convert array of {name, value} objects to name => value map
        $variableValues = [];
        if (isset($responseData['variables']) && is_array($responseData['variables'])) {
            foreach ($responseData['variables'] as $variable) {
                if (isset($variable['name']) && isset($variable['value'])) {
                    $variableValues[$variable['name']] = $variable['value'];
                }
            }
        }

        return [
            'values' => $variableValues,
            'title'  => $responseData['title'] ?? '',
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

        return $instructions;
    }

    /**
     * Find or create the Template Variable Extractor agent
     */
    protected function findOrCreateVariableExtractorAgent(int $teamId): Agent
    {
        $agent = Agent::where('name', 'Template Variable Extractor')
            ->where('team_id', $teamId)
            ->first();

        if (!$agent) {
            $agent = Agent::create([
                'name'         => 'Template Variable Extractor',
                'team_id'      => $teamId,
                'model'        => 'gpt-4o',
                'instructions' => 'You are an expert at extracting structured data from documents.',
                'api_options'  => [],
            ]);
        }

        return $agent;
    }

    /**
     * Create agent thread with instructions and artifacts
     */
    protected function createAgentThread(
        Agent      $agent,
        int        $teamId,
        string     $instructions,
        Collection $artifacts
    ): AgentThread
    {
        $thread = AgentThread::create([
            'name'     => 'Template Variable Resolution',
            'team_id'  => $teamId,
            'agent_id' => $agent->id,
        ]);

        // Add instruction message
        AgentThreadMessage::create([
            'agent_thread_id' => $thread->id,
            'role'            => 'user',
            'content'         => $instructions,
        ]);

        // Add artifacts as input
        foreach($artifacts as $artifact) {
            AgentThreadMessage::create([
                'agent_thread_id' => $thread->id,
                'role'            => 'user',
                'content'         => $artifact->text_content ?: "Artifact: {$artifact->name}",
                'data'            => [
                    'artifact_id' => $artifact->id,
                ],
            ]);
        }

        return $thread;
    }

    /**
     * Get assistant response from thread
     */
    protected function getAssistantResponse(AgentThread $thread): string
    {
        $assistantMessage = $thread->messages()
            ->where('role', 'assistant')
            ->orderBy('id')
            ->first();

        $response = $assistantMessage?->content;
        if (!$response) {
            throw new ValidationError('AI returned empty response for variable resolution', 500);
        }

        return $response;
    }

    /**
     * Parse AI response to extract variables and title
     */
    protected function parseAiResponse(string $response): array
    {
        // Try to parse as JSON first
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Try to extract JSON from markdown code block
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Try to extract any JSON object
        if (preg_match('/(\{.*\})/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return ['variables' => [], 'title' => ''];
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
            'type' => 'object',
            'properties' => [
                'variables' => [
                    'type' => 'array',
                    'description' => 'Array of extracted variable values',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The variable name',
                            ],
                            'value' => [
                                'type' => 'string',
                                'description' => 'The extracted value for the variable',
                            ],
                        ],
                        'required' => ['name', 'value'],
                        'additionalProperties' => false,
                    ],
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Generated title for the demand'
                ]
            ],
            'required' => ['variables', 'title'],
            'additionalProperties' => false,
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => null,
            'name' => 'Template Variable Resolution Response',
        ], [
            'description' => 'JSON schema for template variable resolution responses',
            'schema' => $schema,
        ]);
    }
}
