<?php

namespace App\Services\Template;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Models\Template\TemplateVariable;
use App\Resources\TeamObject\TeamObjectForAgentsResource;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\ArrayAggregationService;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\ValueFormattingService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

/**
 * Resolves template variables from TeamObject data, combining fragment data with artifact content.
 *
 * This service handles variables that pull data from:
 * 1. TeamObject attributes (via schema association and fragment selector)
 * 2. TeamObject artifacts (via artifact categories)
 * 3. Both sources combined
 *
 * Processing modes:
 * - 'ai': Use AI to transform/summarize the combined data
 * - 'verbatim': Join artifact text directly without AI processing
 */
class TemplateVariableResolver
{
    use HasDebugLogging;

    /**
     * Resolve a template variable from a TeamObject.
     *
     * @param  TemplateVariable  $variable  The variable definition to resolve
     * @param  TeamObject  $teamObject  The TeamObject to extract data from
     * @param  int|null  $teamId  Team ID for AI operations (required for AI mapping)
     * @return string The resolved variable value
     */
    public function resolve(TemplateVariable $variable, TeamObject $teamObject, ?int $teamId = null): string
    {
        static::logDebug('Resolving template variable from TeamObject', [
            'variable_id'            => $variable->id,
            'variable_name'          => $variable->name,
            'mapping_type'           => $variable->mapping_type,
            'team_object_id'         => $teamObject->id,
            'has_schema_association' => (bool)$variable->team_object_schema_association_id,
            'artifact_categories'    => $variable->artifact_categories,
        ]);

        // Collect data from both sources
        $fragmentData = $this->resolveFragmentData($variable, $teamObject);
        $artifactData = $this->resolveArtifactData($variable, $teamObject);

        // Combine the data sources
        $combinedData = $this->combineDataSources($fragmentData, $artifactData);

        if (empty($combinedData)) {
            static::logDebug('No data resolved for variable', ['variable_name' => $variable->name]);

            return '';
        }

        // Apply processing based on mapping type
        $result = match ($variable->mapping_type) {
            TemplateVariable::MAPPING_TYPE_AI      => $this->processWithAi($variable, $combinedData, $teamId),
            TemplateVariable::MAPPING_TYPE_VERBATIM,
            TemplateVariable::MAPPING_TYPE_ARTIFACT,
            TemplateVariable::MAPPING_TYPE_TEAM_OBJECT => $this->processVerbatim($variable, $combinedData),
            default                                    => $this->processVerbatim($variable, $combinedData),
        };

        // Apply value formatting
        $formatted = $this->formatValue($result, $variable);

        static::logDebug('Variable resolved', [
            'variable_name'  => $variable->name,
            'result_length'  => strlen($formatted),
            'result_preview' => substr($formatted, 0, 200),
        ]);

        return $formatted;
    }

    /**
     * Resolve fragment data from TeamObject attributes using the schema association.
     *
     * @return array Array of scalar values extracted from the fragment
     */
    protected function resolveFragmentData(TemplateVariable $variable, TeamObject $teamObject): array
    {
        if (!$variable->team_object_schema_association_id) {
            return [];
        }

        $schemaAssociation = $variable->teamObjectSchemaAssociation;
        if (!$schemaAssociation) {
            static::logDebug('Schema association not found', [
                'association_id' => $variable->team_object_schema_association_id,
            ]);

            return [];
        }

        // Load TeamObject data using resource (includes attributes)
        $teamObjectData = TeamObjectForAgentsResource::make($teamObject);

        // Get the fragment selector from the schema fragment
        $fragmentSelector = $schemaAssociation->schemaFragment?->fragment_selector;

        if ($fragmentSelector) {
            $teamObjectData = app(JsonSchemaService::class)->filterDataByFragmentSelector(
                $teamObjectData,
                $fragmentSelector
            );
        }

        $values = $this->flattenToValues($teamObjectData);

        static::logDebug('Resolved fragment data', [
            'variable_name'     => $variable->name,
            'fragment_id'       => $schemaAssociation->schemaFragment?->id,
            'has_selector'      => (bool)$fragmentSelector,
            'extracted_count'   => count($values),
        ]);

        return $values;
    }

    /**
     * Resolve artifact data from the TeamObject by category.
     *
     * @return array Array of text content from artifacts
     */
    protected function resolveArtifactData(TemplateVariable $variable, TeamObject $teamObject): array
    {
        $categories = $variable->artifact_categories;
        if (empty($categories)) {
            return [];
        }

        $values = [];

        foreach ($categories as $category) {
            $artifacts = $teamObject->getArtifactsByCategory($category);

            foreach ($artifacts as $artifact) {
                $content = $this->extractArtifactContent($artifact, $variable);
                if ($content !== null && $content !== '') {
                    $values[] = $content;
                }
            }
        }

        static::logDebug('Resolved artifact data', [
            'variable_name'    => $variable->name,
            'categories'       => $categories,
            'extracted_count'  => count($values),
        ]);

        return $values;
    }

    /**
     * Extract content from a single artifact.
     *
     * @param  Artifact  $artifact  The artifact to extract from
     * @param  TemplateVariable  $variable  The variable with optional fragment selector
     * @return string|null The extracted content
     */
    protected function extractArtifactContent(Artifact $artifact, TemplateVariable $variable): ?string
    {
        $fragmentSelector = $variable->artifact_fragment_selector;

        // If no fragment selector, use text_content as primary source
        if (empty($fragmentSelector)) {
            return $artifact->text_content ?: $artifact->name;
        }

        // Apply fragment selector to extract specific data
        $extractedValues = $artifact->getFlattenedJsonFragmentValues($fragmentSelector);

        if (empty($extractedValues)) {
            $extractedValues = $artifact->getFlattenedMetaFragmentValues($fragmentSelector);
        }

        return !empty($extractedValues) ? implode(' ', $extractedValues) : null;
    }

    /**
     * Combine fragment data and artifact data into a single array.
     */
    protected function combineDataSources(array $fragmentData, array $artifactData): array
    {
        return array_merge($fragmentData, $artifactData);
    }

    /**
     * Process combined data using AI transformation.
     *
     * @param  TemplateVariable  $variable  The variable definition
     * @param  array  $data  Combined data to process
     * @param  int|null  $teamId  Team ID for AI operations
     * @return string Transformed result
     */
    protected function processWithAi(TemplateVariable $variable, array $data, ?int $teamId): string
    {
        $teamId ??= team()?->id;
        if (!$teamId) {
            static::logDebug('No team ID for AI processing, falling back to verbatim');

            return $this->processVerbatim($variable, $data);
        }

        $agent = $this->findOrCreateTransformAgent();

        // Build prompt for transformation
        $prompt = $this->buildAiTransformPrompt($variable, $data);

        // Create response schema
        $responseSchema = $this->createTransformResponseSchema();

        $threadRun = AgentThreadBuilderService::for($agent, $teamId)
            ->named('Variable Transform: ' . $variable->name)
            ->withMessage($prompt)
            ->withResponseSchema($responseSchema)
            ->withTimeout(config('ai.variable_transform.timeout', 60))
            ->run();

        if (!$threadRun->isCompleted()) {
            static::logDebug('AI transform failed, falling back to verbatim', [
                'status' => $threadRun->status,
                'error'  => $threadRun->error_message,
            ]);

            return $this->processVerbatim($variable, $data);
        }

        $responseData = $threadRun->lastMessage?->getJsonContent();

        return $responseData['value'] ?? $this->processVerbatim($variable, $data);
    }

    /**
     * Build the prompt for AI transformation.
     */
    protected function buildAiTransformPrompt(TemplateVariable $variable, array $data): string
    {
        $dataContent = implode("\n---\n", array_map(fn($v) => (string)$v, $data));

        $prompt = "Transform the following data into a value for the variable '{$variable->name}'.";

        if ($variable->description) {
            $prompt .= "\n\nVariable description: {$variable->description}";
        }

        if ($variable->ai_instructions) {
            $prompt .= "\n\nInstructions: {$variable->ai_instructions}";
        }

        $prompt .= "\n\nData to transform:\n{$dataContent}";
        $prompt .= "\n\nProvide a concise, well-formatted value based on the data above.";

        return $prompt;
    }

    /**
     * Create the response schema for AI transformation.
     */
    protected function createTransformResponseSchema(): SchemaDefinition
    {
        return SchemaDefinition::firstOrCreate([
            'team_id' => null,
            'name'    => 'Variable Transform Response',
        ], [
            'description' => 'JSON schema for variable transformation responses',
            'schema'      => [
                'type'                 => 'object',
                'properties'           => [
                    'value' => [
                        'type'        => 'string',
                        'description' => 'The transformed value for the variable',
                    ],
                ],
                'required'             => ['value'],
                'additionalProperties' => false,
            ],
        ]);
    }

    /**
     * Find or create the variable transform agent.
     */
    protected function findOrCreateTransformAgent(): Agent
    {
        $agentName = 'Variable Transform Agent';

        $agent = Agent::where('name', $agentName)
            ->whereNull('team_id')
            ->first();

        if (!$agent) {
            $agent = Agent::create([
                'name'        => $agentName,
                'team_id'     => null,
                'model'       => config('ai.variable_transform.model', 'gpt-4o-mini'),
                'description' => 'AI agent that transforms combined data into formatted variable values.',
                'api_options' => [
                    'instructions' => 'You are a data transformation assistant. Your role is to take raw data and transform it into clean, well-formatted values suitable for use in templates. Be concise and accurate.',
                ],
            ]);
        }

        return $agent;
    }

    /**
     * Process combined data verbatim (direct join without AI).
     *
     * @param  TemplateVariable  $variable  The variable definition
     * @param  array  $data  Combined data to process
     * @return string Joined result
     */
    protected function processVerbatim(TemplateVariable $variable, array $data): string
    {
        return $this->combineValues(
            $data,
            $variable->multi_value_strategy,
            $variable->multi_value_separator
        );
    }

    /**
     * Combine multiple values using the specified strategy.
     */
    protected function combineValues(array $values, string $strategy, string $separator): string
    {
        if (empty($values)) {
            return '';
        }

        $stringValues = array_map([$this, 'convertToString'], $values);

        return match ($strategy) {
            TemplateVariable::STRATEGY_FIRST  => $stringValues[0] ?? '',
            TemplateVariable::STRATEGY_UNIQUE => implode($separator, array_unique($stringValues)),
            TemplateVariable::STRATEGY_JOIN   => implode($separator, $stringValues),
            TemplateVariable::STRATEGY_MAX    => app(ArrayAggregationService::class)->max($values),
            TemplateVariable::STRATEGY_MIN    => app(ArrayAggregationService::class)->min($values),
            TemplateVariable::STRATEGY_AVG    => app(ArrayAggregationService::class)->avg($values),
            TemplateVariable::STRATEGY_SUM    => app(ArrayAggregationService::class)->sum($values),
            default                           => $stringValues[0] ?? '',
        };
    }

    /**
     * Format the resolved value based on variable settings.
     */
    protected function formatValue(string $value, TemplateVariable $variable): string
    {
        if ($value === '') {
            return $value;
        }

        $options = [
            'decimals'     => $variable->decimal_places ?? 2,
            'currencyCode' => $variable->currency_code  ?? 'USD',
        ];

        return app(ValueFormattingService::class)->format(
            $value,
            $variable->value_format_type ?? TemplateVariable::FORMAT_TEXT,
            $options
        );
    }

    /**
     * Flatten nested arrays to extract scalar values only.
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
        foreach ($data as $value) {
            if (is_scalar($value)) {
                $values[] = $value;
            } elseif (is_array($value)) {
                $values = array_merge($values, $this->flattenToValues($value));
            }
        }

        return $values;
    }

    /**
     * Convert any value to string safely.
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
}
