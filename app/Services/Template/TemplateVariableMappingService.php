<?php

namespace App\Services\Template;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Schema\SchemaDefinition;
use App\Models\Template\TemplateDefinition;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Traits\HasDebugLogging;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Symfony\Component\Yaml\Yaml;

/**
 * Service for AI-powered variable mapping suggestions.
 *
 * Handles matching template variables to schema fragments using LLM-based semantic analysis.
 */
class TemplateVariableMappingService
{
    use HasDebugLogging;

    /**
     * Suggest variable mappings using AI to match template variables to schema fragments.
     *
     * @return array{suggestions: array, unmapped_count: int, matched_count: int}
     */
    public function suggestVariableMappings(TemplateDefinition $template): array
    {
        $this->validateOwnership($template);

        // Validate template has a locked schema
        if (!$template->schema_definition_id) {
            throw new ValidationError('Template must have a locked schema before suggesting mappings', 400);
        }

        $lockedSchema = $template->schemaDefinition;
        if (!$lockedSchema) {
            throw new ValidationError('Locked schema not found', 400);
        }

        // Get unmapped variables (those without schema association)
        $unmappedVariables = $template->templateVariables()
            ->whereNull('team_object_schema_association_id')
            ->get();

        if ($unmappedVariables->isEmpty()) {
            return [
                'suggestions'    => [],
                'unmapped_count' => 0,
                'matched_count'  => 0,
            ];
        }

        // Get schema fragments from the locked schema
        $fragments = $lockedSchema->fragments()->get();

        if ($fragments->isEmpty()) {
            return [
                'suggestions'    => [],
                'unmapped_count' => $unmappedVariables->count(),
                'matched_count'  => 0,
            ];
        }

        // Use AI to suggest mappings
        $suggestions = $this->getAiMappingSuggestions($unmappedVariables, $fragments);

        $matchedCount = collect($suggestions)->filter(fn($s) => $s['suggested_fragment_id'] !== null)->count();

        return [
            'suggestions'    => $suggestions,
            'unmapped_count' => $unmappedVariables->count(),
            'matched_count'  => $matchedCount,
        ];
    }

    /**
     * Validate that the current team owns the template.
     */
    protected function validateOwnership(TemplateDefinition $template): void
    {
        $currentTeam = team();
        if (!$currentTeam || $template->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this template definition', 403);
        }
    }

    /**
     * Get AI-powered mapping suggestions for variables.
     */
    protected function getAiMappingSuggestions(Collection $variables, Collection $fragments): array
    {
        $agent = $this->findOrCreateMappingAgent();

        // Build the prompt with variable and fragment information
        $variablesInfo = $variables->map(fn($v) => [
            'id'          => $v->id,
            'name'        => $v->name,
            'description' => $v->description,
        ])->values()->toArray();

        $fragmentsInfo = $fragments->map(fn($f) => [
            'id'                => $f->id,
            'name'              => $f->name,
            'fragment_selector' => $f->fragment_selector,
        ])->values()->toArray();

        $prompt = $this->buildMappingPrompt($variablesInfo, $fragmentsInfo);

        // Create response schema for structured output
        $responseSchema = $this->createMappingResponseSchema();

        $threadRun = AgentThreadBuilderService::for($agent, team()->id)
            ->named('Variable Mapping Suggestions')
            ->withMessage($prompt)
            ->withResponseSchema($responseSchema)
            ->withTimeout($this->getMappingTimeout())
            ->run();

        if (!$threadRun->isCompleted()) {
            static::logDebug('Variable mapping AI request did not complete', [
                'status' => $threadRun->status,
            ]);

            return $this->buildEmptySuggestions($variables);
        }

        return $this->parseMappingResponse($threadRun->lastMessage, $variables, $fragments);
    }

    /**
     * Build the prompt for variable-to-fragment mapping.
     */
    protected function buildMappingPrompt(array $variables, array $fragments): string
    {
        $template = file_get_contents(resource_path('prompts/template/variable-mapping-suggestions.md'));

        return strtr($template, [
            '{{variables_json}}' => json_encode($variables, JSON_PRETTY_PRINT),
            '{{fragments_json}}' => json_encode($fragments, JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * Create the response schema for mapping suggestions.
     */
    protected function createMappingResponseSchema(): SchemaDefinition
    {
        $schemaYaml = file_get_contents(resource_path('schemas/template/variable-mapping-response.yaml'));
        $schema     = Yaml::parse($schemaYaml);

        return new SchemaDefinition([
            'name'   => 'variable-mapping-suggestions',
            'schema' => $schema,
        ]);
    }

    /**
     * Parse the AI response into mapping suggestions.
     */
    protected function parseMappingResponse(?AgentThreadMessage $message, Collection $variables, Collection $fragments): array
    {
        if (!$message) {
            return $this->buildEmptySuggestions($variables);
        }

        $responseData = $message->getJsonContent();

        if (!$responseData || !isset($responseData['mappings']) || !is_array($responseData['mappings'])) {
            static::logDebug('Invalid mapping response format', [
                'response' => $responseData,
            ]);

            return $this->buildEmptySuggestions($variables);
        }

        $suggestions   = [];
        $variablesById = $variables->keyBy('id');
        $fragmentsById = $fragments->keyBy('id');

        foreach ($responseData['mappings'] as $mapping) {
            $variableId = $mapping['variable_id']           ?? null;
            $fragmentId = $mapping['suggested_fragment_id'] ?? null;

            $variable = $variablesById->get($variableId);
            if (!$variable) {
                continue;
            }

            $fragment = $fragmentId ? $fragmentsById->get($fragmentId) : null;

            $suggestions[] = [
                'variable_id'             => $variableId,
                'variable_name'           => $variable->name,
                'suggested_fragment_id'   => $fragmentId,
                'suggested_fragment_name' => $fragment?->name,
                'confidence'              => min(1.0, max(0.0, (float)($mapping['confidence'] ?? 0))),
                'reasoning'               => $mapping['reasoning'] ?? '',
            ];
        }

        // Ensure all variables are represented in the response
        foreach ($variables as $variable) {
            $found = collect($suggestions)->firstWhere('variable_id', $variable->id);
            if (!$found) {
                $suggestions[] = [
                    'variable_id'             => $variable->id,
                    'variable_name'           => $variable->name,
                    'suggested_fragment_id'   => null,
                    'suggested_fragment_name' => null,
                    'confidence'              => 0.0,
                    'reasoning'               => 'No suitable match found',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Build empty suggestions for all variables (used when AI fails).
     */
    protected function buildEmptySuggestions(Collection $variables): array
    {
        return $variables->map(fn($v) => [
            'variable_id'             => $v->id,
            'variable_name'           => $v->name,
            'suggested_fragment_id'   => null,
            'suggested_fragment_name' => null,
            'confidence'              => 0.0,
            'reasoning'               => 'Unable to generate suggestion',
        ])->values()->toArray();
    }

    /**
     * Find or create the mapping suggestion agent.
     */
    protected function findOrCreateMappingAgent(): Agent
    {
        $agentName  = 'Variable Mapping Suggestion Agent';
        $model      = config('ai.variable_mapping_suggestions.model', 'gpt-5-mini');
        $apiOptions = config('ai.variable_mapping_suggestions.api_options', []);

        $agent = Agent::where('name', $agentName)
            ->whereNull('team_id')
            ->first();

        $instructions = $this->getMappingAgentInstructions();

        if (!$agent) {
            $agent = Agent::create([
                'name'        => $agentName,
                'team_id'     => null,
                'model'       => $model,
                'description' => 'AI agent that suggests mappings between template variables and schema fragments based on semantic analysis.',
                'api_options' => array_merge($apiOptions, [
                    'instructions' => $instructions,
                ]),
            ]);

            static::logDebug('Created Variable Mapping Agent', [
                'agent_id' => $agent->id,
                'model'    => $model,
            ]);
        } else {
            // Update model and instructions if they differ from config
            $currentInstructions = $agent->api_options['instructions'] ?? null;
            $needsUpdate         = false;
            $updates             = [];

            if ($agent->model !== $model) {
                $updates['model'] = $model;
                $needsUpdate      = true;
            }

            if ($currentInstructions !== $instructions) {
                $updates['api_options'] = array_merge(
                    $apiOptions,
                    $agent->api_options ?? [],
                    ['instructions' => $instructions]
                );
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $agent->update($updates);

                static::logDebug('Updated Variable Mapping Agent', [
                    'agent_id'             => $agent->id,
                    'model_updated'        => isset($updates['model']),
                    'instructions_updated' => isset($updates['api_options']),
                ]);
            }
        }

        return $agent;
    }

    /**
     * Get instructions for the mapping agent.
     */
    protected function getMappingAgentInstructions(): string
    {
        return file_get_contents(resource_path('prompts/template/variable-mapping-agent.md'));
    }

    /**
     * Get timeout for mapping requests from config.
     */
    protected function getMappingTimeout(): int
    {
        return (int)config('ai.variable_mapping_suggestions.timeout', 120);
    }
}
