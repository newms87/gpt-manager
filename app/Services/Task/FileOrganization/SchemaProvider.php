<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use App\Traits\HasDebugLogging;

/**
 * Provides JSON Schema definitions for file organization task responses.
 */
class SchemaProvider
{
    use HasDebugLogging;

    /**
     * Get or create the file organization response schema definition.
     *
     * @param  int  $teamId  Team ID for schema ownership
     * @param  TaskDefinition  $taskDefinition  Task definition for dynamic schema description
     */
    public function getFileOrganizationSchema(int $teamId, TaskDefinition $taskDefinition): SchemaDefinition
    {
        static::logDebug("Getting file organization schema for team $teamId");

        // Get dynamic name description based on task definition
        $nameDescription = $this->getNameDescription($taskDefinition);

        $schema = $this->buildFileOrganizationSchema($nameDescription);

        $schemaDefinition = SchemaDefinition::updateOrCreate([
            'team_id' => $teamId,
            'name'    => 'File Organization Response',
            'type'    => 'FileOrganizationResponse',
        ], [
            'description'   => 'JSON schema for file organization task responses',
            'schema'        => $schema,
            'schema_format' => SchemaDefinition::FORMAT_JSON,
        ]);

        static::logDebug("Schema definition created/updated: {$schemaDefinition->id}");

        return $schemaDefinition;
    }

    /**
     * Get dynamic name description for the schema based on TaskDefinition.
     * Uses SchemaDescriptionGeneratorService to generate context-specific descriptions.
     *
     * @param  TaskDefinition  $taskDefinition  Task definition with prompt
     * @return string Name description for the schema
     */
    public function getNameDescription(TaskDefinition $taskDefinition): string
    {
        static::logDebug("Getting name description for TaskDefinition {$taskDefinition->id}");

        $generator = app(SchemaDescriptionGeneratorService::class);

        return $generator->getSchemaNameDescription($taskDefinition);
    }

    /**
     * Get or create the duplicate group resolution schema definition.
     *
     * @param  int  $teamId  Team ID for schema ownership
     */
    public function getDuplicateGroupResolutionSchema(int $teamId): SchemaDefinition
    {
        static::logDebug("Getting duplicate group resolution schema for team $teamId");

        $schema = $this->buildDuplicateGroupResolutionSchema();

        $schemaDefinition = SchemaDefinition::updateOrCreate([
            'team_id' => $teamId,
            'name'    => 'Duplicate Group Resolution Response',
            'type'    => 'DuplicateGroupResolutionResponse',
        ], [
            'description'   => 'JSON schema for duplicate group resolution responses',
            'schema'        => $schema,
            'schema_format' => SchemaDefinition::FORMAT_JSON,
        ]);

        static::logDebug("Duplicate group resolution schema definition created/updated: {$schemaDefinition->id}");

        return $schemaDefinition;
    }

    /**
     * Build the JSON Schema for file organization responses.
     *
     * @param  string|null  $nameDescription  Optional custom description for the name field
     * @return array JSON schema definition
     */
    protected function buildFileOrganizationSchema(?string $nameDescription = null): array
    {
        // Use provided description or fall back to default
        $nameDescription = $nameDescription ?? 'Name of this group (e.g., "Section A", "Category 1", "Entity Name"). Use empty string "" if no clear identifier exists.';

        return [
            'type'                 => 'object',
            'properties'           => [
                'groups' => [
                    'type'        => 'array',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'name'        => [
                                'type'        => 'string',
                                'description' => $nameDescription,
                            ],
                            'description' => [
                                'type'        => 'string',
                                'description' => 'High-level description of the contents of this group',
                            ],
                            'files'       => [
                                'type'        => 'array',
                                'items'       => [
                                    'type'                 => 'object',
                                    'properties'           => [
                                        'page_number' => [
                                            'type'        => 'integer',
                                            'description' => 'Page number of the file in the original document',
                                        ],
                                        'confidence'  => [
                                            'type'        => 'integer',
                                            'minimum'     => 0,
                                            'maximum'     => 5,
                                            'description' => 'Confidence score (0-5) for this file assignment',
                                        ],
                                        'explanation' => [
                                            'type'        => 'string',
                                            'description' => 'Brief explanation for this assignment and confidence level',
                                        ],
                                    ],
                                    'required'             => ['page_number', 'confidence', 'explanation'],
                                    'additionalProperties' => false,
                                ],
                                'description' => 'Array of file assignments with confidence scores',
                            ],
                        ],
                        'required'             => ['name', 'description', 'files'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Groups of related files with confidence scores. Each page must appear in EXACTLY ONE group. If uncertain about placement, use a low confidence score (0-2) to trigger automatic resolution.',
                ],
            ],
            'required'             => ['groups'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Build JSON schema for duplicate group resolution responses.
     *
     * @return array JSON schema definition
     */
    protected function buildDuplicateGroupResolutionSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'decisions' => [
                    'type'        => 'array',
                    'description' => 'Array of decisions for each duplicate candidate pair',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'group1_name'      => [
                                'type'        => 'string',
                                'description' => 'First group name from the pair',
                            ],
                            'group2_name'      => [
                                'type'        => 'string',
                                'description' => 'Second group name from the pair',
                            ],
                            'are_duplicates'   => [
                                'type'        => 'boolean',
                                'description' => 'True if these groups represent the same entity and should be merged',
                            ],
                            'canonical_group'  => [
                                'type'        => 'string',
                                'description' => 'If are_duplicates is true, which group name to use as the canonical (primary) name. Must be exactly one of the two group names.',
                            ],
                            'confidence'       => [
                                'type'        => 'integer',
                                'description' => 'Confidence level for this decision (1=very uncertain, 5=absolutely certain)',
                                'minimum'     => 1,
                                'maximum'     => 5,
                            ],
                            'reason'           => [
                                'type'        => 'string',
                                'description' => 'Detailed explanation of why you determined these are duplicates or different entities',
                            ],
                        ],
                        'required'   => ['group1_name', 'group2_name', 'are_duplicates', 'confidence', 'reason'],
                    ],
                ],
            ],
            'required'   => ['decisions'],
        ];
    }
}
