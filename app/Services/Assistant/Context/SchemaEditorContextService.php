<?php

namespace App\Services\Assistant\Context;

use App\Models\Assistant\AssistantAction;
use App\Models\Schema\SchemaDefinition;
use App\Repositories\SchemaDefinitionRepository;
use Illuminate\Support\Facades\Log;

class SchemaEditorContextService implements ContextServiceInterface
{

    public function buildSystemPrompt(array $contextData = []): string
    {
        $currentSchema = '';
        $schemaName = '';
        
        // Check for object in contextData first (from component-based context)
        if (isset($contextData['object']) && $contextData['object'] instanceof SchemaDefinition) {
            $schema = $contextData['object'];
            if ($schema->team_id === team()->id) {
                $schemaName = $schema->name;
                $currentSchema = "\n\nCurrent Schema ({$schemaName}):\n" . json_encode($schema->schema, JSON_PRETTY_PRINT);
            }
        }
        // Fall back to schema_id if object not provided
        elseif (isset($contextData['schema_id'])) {
            $schema = SchemaDefinition::find($contextData['schema_id']);
            if ($schema && $schema->team_id === team()->id) {
                $schemaName = $schema->name;
                $currentSchema = "\n\nCurrent Schema ({$schemaName}):\n" . json_encode($schema->schema, JSON_PRETTY_PRINT);
            }
        }

        return "You are a JSON Schema design expert assistant working within a schema editor context.

Your role is to help the user design, modify, and improve JSON schemas. You have access to tools that can make specific modifications to schemas.

Key capabilities:
- Analyze existing schemas and suggest improvements
- Help design new schema structures based on requirements
- Validate schema completeness and best practices
- Make specific modifications to schema properties
- Add appropriate validation rules and constraints

When the user describes what they want to model or asks for schema changes:
1. Ask clarifying questions if needed to understand the data structure
2. Suggest specific schema modifications using the suggest_schema_modification tool
3. Explain the reasoning behind your suggestions
4. Always provide preview data showing exactly what will change

Guidelines:
- Focus on practical, actionable schema improvements
- Consider data validation, type safety, and API usability
- Suggest appropriate data types, validation rules, and constraints
- Ask for confirmation before making significant changes
- Be collaborative and educational in your approach{$currentSchema}";
    }

    public function getCapabilities(array $contextData = []): array
    {
        return [
            'analyze_schema' => 'Analyze current schema structure and suggest improvements',
            'add_properties' => 'Add new properties to the schema',
            'modify_properties' => 'Modify existing schema properties and their validation rules',
            'remove_properties' => 'Remove unnecessary properties from the schema',
            'restructure_schema' => 'Reorganize schema structure for better design',
            'validate_schema' => 'Validate schema completeness and best practices',
            'suggest_types' => 'Recommend appropriate data types and constraints',
            'generate_examples' => 'Generate example data based on the schema',
        ];
    }

    public function executeAction(AssistantAction $action): array
    {
        try {
            if (!$this->canExecuteAction($action)) {
                return [
                    'success' => false,
                    'error' => 'Action not supported in schema editor context',
                ];
            }

            switch ($action->action_type) {
                case 'modify_schema':
                    return $this->executeSchemaModification($action);
                
                case 'validate_schema':
                    return $this->executeSchemaValidation($action);
                
                case 'generate_example':
                    return $this->executeExampleGeneration($action);
                
                default:
                    return [
                        'success' => false,
                        'error' => "Unknown action type: {$action->action_type}",
                    ];
            }

        } catch (\Exception $e) {
            Log::error('Schema action execution failed', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function canExecuteAction(AssistantAction $action): bool
    {
        $supportedActions = [
            'modify_schema',
            'validate_schema',
            'generate_example',
        ];

        return in_array($action->action_type, $supportedActions) &&
               $action->target_type === 'schema';
    }

    protected function executeSchemaModification(AssistantAction $action): array
    {
        $payload = $action->payload;
        $schemaId = $action->target_id;

        if (!$schemaId) {
            return [
                'success' => false,
                'error' => 'Schema ID is required for modification',
            ];
        }

        $schema = SchemaDefinition::find($schemaId);
        if (!$schema || $schema->team_id !== team()->id) {
            return [
                'success' => false,
                'error' => 'Schema not found or access denied',
            ];
        }

        // Apply the modification based on the payload
        $currentSchema = $schema->schema;
        $modifiedSchema = $this->applySchemaModification($currentSchema, $payload);

        // Update the schema
        $schema->update(['schema' => $modifiedSchema]);

        return [
            'success' => true,
            'data' => [
                'schema_id' => $schema->id,
                'previous_schema' => $currentSchema,
                'updated_schema' => $modifiedSchema,
                'modification_type' => $payload['modification_type'] ?? null,
            ],
            'message' => 'Schema modified successfully',
        ];
    }

    protected function executeSchemaValidation(AssistantAction $action): array
    {
        $schemaId = $action->target_id;

        if (!$schemaId) {
            return [
                'success' => false,
                'error' => 'Schema ID is required for validation',
            ];
        }

        $schema = SchemaDefinition::find($schemaId);
        if (!$schema || $schema->team_id !== team()->id) {
            return [
                'success' => false,
                'error' => 'Schema not found or access denied',
            ];
        }

        // Perform schema validation
        $validation = $this->validateSchemaStructure($schema->schema);

        return [
            'success' => true,
            'data' => [
                'schema_id' => $schema->id,
                'validation_results' => $validation,
                'is_valid' => empty($validation['errors']),
            ],
            'message' => empty($validation['errors']) ? 
                'Schema validation passed' : 
                'Schema validation found issues',
        ];
    }

    protected function executeExampleGeneration(AssistantAction $action): array
    {
        $schemaId = $action->target_id;

        if (!$schemaId) {
            return [
                'success' => false,
                'error' => 'Schema ID is required for example generation',
            ];
        }

        $result = app(SchemaDefinitionRepository::class)->action('generate-example', [
            'id' => $schemaId,
        ]);

        return [
            'success' => true,
            'data' => [
                'schema_id' => $schemaId,
                'generated_example' => $result['response_example'] ?? null,
            ],
            'message' => 'Example generated successfully',
        ];
    }

    protected function applySchemaModification(array $schema, array $modification): array
    {
        $modificationType = $modification['modification_type'] ?? '';
        $targetPath = $modification['target_path'] ?? '';
        $modificationData = $modification['modification_data'] ?? [];

        switch ($modificationType) {
            case 'add_property':
                return $this->addPropertyToSchema($schema, $targetPath, $modificationData);
            
            case 'modify_property':
                return $this->modifyPropertyInSchema($schema, $targetPath, $modificationData);
            
            case 'remove_property':
                return $this->removePropertyFromSchema($schema, $targetPath);
            
            case 'restructure':
                return $this->restructureSchema($schema, $modificationData);
            
            default:
                throw new \Exception("Unsupported modification type: {$modificationType}");
        }
    }

    protected function addPropertyToSchema(array $schema, string $path, array $propertyData): array
    {
        $pathParts = explode('.', $path);
        $current = &$schema;

        // Navigate to the target location
        foreach (array_slice($pathParts, 0, -1) as $part) {
            if (!isset($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }

        // Add the new property
        $propertyName = end($pathParts);
        if ($path === 'properties') {
            if (!isset($current['properties'])) {
                $current['properties'] = [];
            }
            $current['properties'][$propertyData['name']] = $propertyData['definition'];
        } else {
            $current[$propertyName] = $propertyData;
        }

        return $schema;
    }

    protected function modifyPropertyInSchema(array $schema, string $path, array $propertyData): array
    {
        $pathParts = explode('.', $path);
        $current = &$schema;

        // Navigate to the target property
        foreach ($pathParts as $part) {
            if (!isset($current[$part])) {
                throw new \Exception("Property path not found: {$path}");
            }
            $current = &$current[$part];
        }

        // Merge the modifications
        $current = array_merge($current, $propertyData);

        return $schema;
    }

    protected function removePropertyFromSchema(array $schema, string $path): array
    {
        $pathParts = explode('.', $path);
        $propertyName = array_pop($pathParts);
        $current = &$schema;

        // Navigate to the parent of the target property
        foreach ($pathParts as $part) {
            if (!isset($current[$part])) {
                throw new \Exception("Property path not found: {$path}");
            }
            $current = &$current[$part];
        }

        // Remove the property
        unset($current[$propertyName]);

        return $schema;
    }

    protected function restructureSchema(array $schema, array $restructureData): array
    {
        // This would implement more complex schema restructuring
        // For now, just return the provided structure
        return array_merge($schema, $restructureData);
    }

    protected function validateSchemaStructure(array $schema): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];

        // Check for required JSON Schema properties
        if (!isset($schema['type'])) {
            $errors[] = 'Schema is missing required "type" property';
        }

        // Check for title and description
        if (!isset($schema['title'])) {
            $warnings[] = 'Schema should have a "title" for better documentation';
        }

        if (!isset($schema['description'])) {
            $warnings[] = 'Schema should have a "description" for better documentation';
        }

        // Validate object properties
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (!isset($schema['properties'])) {
                $warnings[] = 'Object schema should define "properties"';
            } else {
                // Check each property
                foreach ($schema['properties'] as $propName => $propDef) {
                    if (!isset($propDef['type']) && !isset($propDef['$ref'])) {
                        $warnings[] = "Property '{$propName}' should have a type or \$ref";
                    }
                }
            }

            if (!isset($schema['required'])) {
                $suggestions[] = 'Consider adding "required" array to specify which properties are mandatory';
            }

            if (!isset($schema['additionalProperties'])) {
                $suggestions[] = 'Consider setting "additionalProperties" to control object flexibility';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
        ];
    }
}