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

        $prompt = "# Identity Field Selection Task\n\n";
        $prompt .= "You are tasked with selecting identity fields for a specific object type in a data extraction schema.\n\n";

        $prompt .= "## Object Type Information\n\n";
        $prompt .= "**Name:** $name\n";
        $prompt .= "**Path:** $path\n";
        $prompt .= "**Level:** $level\n";
        if ($parentType) {
            $prompt .= "**Parent Type:** $parentType\n";
        }
        $prompt .= '**Is Array:** ' . ($isArray ? 'Yes' : 'No') . "\n\n";

        $prompt .= "## Available Simple Fields\n\n";
        $prompt .= "These are the simple (non-nested) fields available for this object type:\n\n";
        $prompt .= $this->convertSimpleFieldsToYaml($simpleFields);
        $prompt .= "\n";

        $prompt .= "## Configuration\n\n";
        $prompt .= '- **Group Max Points:** ' . $config['group_max_points'] . " (maximum fields per group)\n\n";

        $prompt .= "## Your Task\n\n";
        $prompt .= "1. **Select Identity Fields:** Choose fields that uniquely identify this object type.\n";
        $prompt .= "   - Prefer fields like name, date, ID, or unique identifiers\n";
        $prompt .= "   - These fields should help distinguish one instance from another\n";
        $prompt .= "   - For array types, identity is especially important\n";
        $prompt .= "   - Use your judgment to determine how many identity fields are needed based on the schema\n\n";

        $prompt .= "2. **Select Skim Fields:** Choose additional simple fields to extract together with identity fields in \"skim\" mode.\n";
        $prompt .= "   - Include ALL identity fields in skim_fields\n";
        $prompt .= "   - Add other simple fields that are quick to extract\n";
        $prompt .= '   - Total skim fields should not exceed group_max_points (' . $config['group_max_points'] . ")\n";
        $prompt .= "   - These will be extracted in a single pass with the identity fields\n\n";

        $prompt .= "3. **Provide Reasoning:** Briefly explain why you chose these identity fields.\n\n";

        $prompt .= "4. **Describe Identification Content:** Provide a clear description that explains:\n";
        $prompt .= "   - What type of document content or sections would contain the identity fields\n";
        $prompt .= "   - What visual or textual cues to look for on a page\n";
        $prompt .= "   - This description helps classify pages by relevance for identifying this object type\n\n";

        $prompt .= 'Generate your response now.';

        return $prompt;
    }

    /**
     * Build LLM prompt for grouping remaining fields that weren't included in identity skim group.
     */
    public function buildRemainingPrompt(array $objectTypeInfo, array $remainingFields, array $config): string
    {
        $name  = $objectTypeInfo['name']  ?? 'Object';
        $path  = $objectTypeInfo['path']  ?? '';
        $level = $objectTypeInfo['level'] ?? 0;

        $prompt = "# Remaining Fields Grouping Task\n\n";
        $prompt .= "You are tasked with grouping remaining fields for data extraction.\n\n";

        $prompt .= "## Object Type Information\n\n";
        $prompt .= "**Name:** $name\n";
        $prompt .= "**Path:** $path\n";
        $prompt .= "**Level:** $level\n\n";

        $prompt .= "## Remaining Fields to Group\n\n";
        $prompt .= "These fields were NOT included in the identity skim group and need to be organized:\n\n";
        $prompt .= $this->convertSimpleFieldsToYaml($remainingFields);
        $prompt .= "\n";

        $prompt .= "## Configuration\n\n";
        $prompt .= '- **Group Max Points:** ' . $config['group_max_points'] . " (maximum fields per group)\n\n";

        $prompt .= "## Your Task\n\n";
        $prompt .= "Create logical extraction groups for these remaining fields:\n\n";
        $prompt .= "1. **Group Related Fields:** Organize fields into logical groups based on:\n";
        $prompt .= "   - Semantic similarity (e.g., address fields together)\n";
        $prompt .= "   - Document structure (e.g., fields likely found in same section)\n";
        $prompt .= "   - Data type or purpose\n\n";

        $prompt .= "2. **Assign Search Modes:** For each group, choose an appropriate search_mode:\n";
        $prompt .= "   - **skim:** Quick extraction for simple, obvious fields that are easy to find\n";
        $prompt .= "   - **exhaustive:** Thorough search for complex fields, fields with detailed descriptions, or hard-to-find data\n\n";

        $prompt .= '3. **Respect Size Limits:** Each group should not exceed ' . $config['group_max_points'] . " fields.\n\n";

        $prompt .= "4. **Name Groups Descriptively:** Use clear, meaningful names for each group.\n\n";

        $prompt .= "5. **Describe Each Group:** Provide a clear description for each group that explains:\n";
        $prompt .= "   - What type of document content or sections would contain these fields\n";
        $prompt .= "   - What visual or textual cues to look for on a page\n";
        $prompt .= "   - This description helps classify pages by relevance to the group\n\n";

        $prompt .= 'Generate your extraction groups now.';

        return $prompt;
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

        $prompt = "# Follow-up: Missing Fields Grouping (Attempt $attemptNumber)\n\n";
        $prompt .= 'Your previous response did not include all required fields. ';
        $prompt .= "Please group the following **missing fields** that were not included in your previous response.\n\n";

        $prompt .= "## Object Type Information\n\n";
        $prompt .= "**Name:** $name\n";
        $prompt .= "**Path:** $path\n\n";

        $prompt .= "## Missing Fields to Group\n\n";
        $prompt .= "These fields were NOT included in your previous response and MUST be grouped:\n\n";
        $prompt .= $this->convertSimpleFieldsToYaml($missingFields);
        $prompt .= "\n";

        $prompt .= "## Configuration\n\n";
        $prompt .= '- **Group Max Points:** ' . $config['group_max_points'] . " (maximum fields per group)\n\n";

        $prompt .= "## Your Task\n\n";
        $prompt .= "**IMPORTANT:** You MUST include ALL of the missing fields listed above in your response.\n\n";
        $prompt .= "Create logical extraction groups for these missing fields:\n\n";
        $prompt .= "1. **Group Related Fields:** Organize fields into logical groups\n";
        $prompt .= "2. **Assign Search Modes:** Choose 'skim' or 'exhaustive' for each group\n";
        $prompt .= '3. **Respect Size Limits:** Each group should not exceed ' . $config['group_max_points'] . " fields\n";
        $prompt .= "4. **Describe Each Group:** Provide a clear description explaining what document content or sections contain these fields\n\n";

        $prompt .= 'Generate your extraction groups for the missing fields now.';

        return $prompt;
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
                'description' => [
                    'type'        => 'string',
                    'description' => 'A clear description of what type of document content or page sections would contain the identity fields for this object type. This is used to classify pages by relevance.',
                ],
                'reasoning' => [
                    'type'        => 'string',
                    'description' => 'Brief explanation of identity field selection',
                ],
            ],
            'required' => ['identity_fields', 'skim_fields', 'description'],
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
                                'description' => 'How thoroughly to search for these fields (skim for simple/obvious, exhaustive for complex/hard-to-find)',
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
