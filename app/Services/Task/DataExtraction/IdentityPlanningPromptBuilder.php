<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use Symfony\Component\Yaml\Yaml;

class IdentityPlanningPromptBuilder
{
    /**
     * Build LLM prompt for identifying identity and skim fields for an object type.
     */
    public function buildIdentityPrompt(array $objectTypeInfo, array $config): string
    {
        $name         = $objectTypeInfo['name']          ?? 'Object';
        $path         = $objectTypeInfo['path']          ?? '';
        $level        = $objectTypeInfo['level']         ?? 0;
        $parentType   = $objectTypeInfo['parent_type']   ?? null;
        $isArray      = $objectTypeInfo['is_array']      ?? false;
        $simpleFields = $objectTypeInfo['simple_fields'] ?? [];

        $template = file_get_contents(resource_path('prompts/extract-data/identity-field-selection.md'));

        $parentTypeLine = $parentType ? "**Parent Type:** $parentType\n" : '';

        return strtr($template, [
            '{{name}}'             => $name,
            '{{path}}'             => $path,
            '{{level}}'            => $level,
            '{{parent_type_line}}' => $parentTypeLine,
            '{{is_array}}'         => $isArray ? 'Yes' : 'No',
            '{{fields_yaml}}'      => $this->convertSimpleFieldsToYaml($simpleFields),
            '{{group_max_points}}' => $config['group_max_points'],
        ]);
    }

    /**
     * Build LLM prompt for grouping remaining fields that weren't included in identity skim group.
     */
    public function buildRemainingPrompt(array $objectTypeInfo, array $remainingFields, array $config): string
    {
        $name  = $objectTypeInfo['name']  ?? 'Object';
        $path  = $objectTypeInfo['path']  ?? '';
        $level = $objectTypeInfo['level'] ?? 0;

        $template = file_get_contents(resource_path('prompts/extract-data/remaining-fields-grouping.md'));

        return strtr($template, [
            '{{name}}'             => $name,
            '{{path}}'             => $path,
            '{{level}}'            => $level,
            '{{fields_yaml}}'      => $this->convertSimpleFieldsToYaml($remainingFields),
            '{{group_max_points}}' => $config['group_max_points'],
        ]);
    }

    /**
     * Build follow-up prompt for remaining fields that were missed in previous response.
     */
    public function buildRemainingFollowUpPrompt(
        array $objectTypeInfo,
        array $missingFields,
        array $config,
        int $attemptNumber
    ): string {
        $name = $objectTypeInfo['object_type'] ?? $objectTypeInfo['name'] ?? 'Object';
        $path = $objectTypeInfo['path']        ?? '';

        $template = file_get_contents(resource_path('prompts/extract-data/remaining-fields-followup.md'));

        return strtr($template, [
            '{{name}}'             => $name,
            '{{path}}'             => $path,
            '{{attempt_number}}'   => $attemptNumber,
            '{{fields_yaml}}'      => $this->convertSimpleFieldsToYaml($missingFields),
            '{{group_max_points}}' => $config['group_max_points'],
        ]);
    }

    /**
     * Create a transient SchemaDefinition for the identity response format.
     * This schema is NOT saved to the database.
     */
    public function createIdentityResponseSchema(): SchemaDefinition
    {
        $schema = [
            'type'       => 'object',
            'properties' => [
                'identity_fields' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => 'Field names that uniquely identify this object',
                ],
                'skim_fields' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'string'],
                    'description' => 'All fields to extract in skim mode (includes identity_fields)',
                ],
                'search_mode' => [
                    'type'        => 'string',
                    'enum'        => ['skim', 'exhaustive'],
                    'description' => 'skim: for singular values resolved once (names, dates, IDs). exhaustive: for values that accumulate across pages (lists, findings, assessments)',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'A clear description of what type of document content or page sections would contain the identity fields for this object type. This is used to classify pages by relevance.',
                ],
                'reasoning' => [
                    'type'        => 'string',
                    'description' => 'Brief explanation of identity field selection',
                ],
            ],
            'required' => ['identity_fields', 'skim_fields', 'search_mode', 'description'],
        ];

        $schemaDefinition                = new SchemaDefinition();
        $schemaDefinition->name          = 'Identity Planning Response';
        $schemaDefinition->type          = SchemaDefinition::TYPE_AGENT_RESPONSE;
        $schemaDefinition->schema_format = SchemaDefinition::FORMAT_JSON;
        $schemaDefinition->schema        = $schema;

        return $schemaDefinition;
    }

    /**
     * Create a transient SchemaDefinition for the remaining fields grouping response format.
     * This schema is NOT saved to the database.
     */
    public function createRemainingResponseSchema(): SchemaDefinition
    {
        $schema = [
            'type'       => 'object',
            'properties' => [
                'extraction_groups' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name' => [
                                'type'        => 'string',
                                'description' => 'Group name',
                            ],
                            'description' => [
                                'type'        => 'string',
                                'description' => 'A clear description of what type of document content or page sections would contain data for these fields. This is used to classify pages by relevance.',
                            ],
                            'fields' => [
                                'type'        => 'array',
                                'items'       => ['type' => 'string'],
                                'description' => 'Field names in this group',
                            ],
                            'search_mode' => [
                                'type'        => 'string',
                                'enum'        => ['skim', 'exhaustive'],
                                'description' => 'skim: for singular values resolved once (names, dates, IDs). exhaustive: for values that accumulate across pages (lists, findings, assessments)',
                            ],
                        ],
                        'required' => ['name', 'description', 'fields', 'search_mode'],
                    ],
                ],
            ],
            'required' => ['extraction_groups'],
        ];

        $schemaDefinition                = new SchemaDefinition();
        $schemaDefinition->name          = 'Remaining Fields Grouping Response';
        $schemaDefinition->type          = SchemaDefinition::TYPE_AGENT_RESPONSE;
        $schemaDefinition->schema_format = SchemaDefinition::FORMAT_JSON;
        $schemaDefinition->schema        = $schema;

        return $schemaDefinition;
    }

    /**
     * Convert simple fields array to minimal YAML format.
     */
    protected function convertSimpleFieldsToYaml(array $simpleFields): string
    {
        if (empty($simpleFields)) {
            return '';
        }

        $properties = [];
        foreach ($simpleFields as $fieldName => $fieldInfo) {
            $properties[$fieldName] = [
                'title' => $fieldInfo['title'] ?? $fieldName,
            ];

            if (!empty($fieldInfo['description'])) {
                $properties[$fieldName]['description'] = $fieldInfo['description'];
            }
        }

        return Yaml::dump(['properties' => $properties], 10, 2);
    }
}
