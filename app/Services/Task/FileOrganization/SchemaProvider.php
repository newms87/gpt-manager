<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskDefinition;
use Newms87\Danx\Traits\HasDebugLogging;

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

        // Get dynamic descriptions based on task definition (single LLM call for both)
        $descriptions = app(SchemaDescriptionGeneratorService::class)->getSchemaDescriptions($taskDefinition);

        $schema = $this->buildFileOrganizationSchema($descriptions['name'], $descriptions['confidence']);

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
     * @param  string|null  $nameDescription  Optional custom description for the group_name field
     * @param  string|null  $confidenceDescription  Optional custom description for the group_name_confidence field
     * @return array JSON schema definition
     */
    protected function buildFileOrganizationSchema(?string $nameDescription = null, ?string $confidenceDescription = null): array
    {
        // Use provided description or fall back to default
        $nameDescription       = $nameDescription       ?? 'Name of the group this file belongs to (e.g., "Acme Corp Invoice #12345", "Section A"). Use empty string "" if no clear identifier exists.';
        $confidenceDescription = $confidenceDescription ?? 'How confident are you in the group name? 0=cannot determine, 5=highly confident';

        return [
            'type'                 => 'object',
            'properties'           => [
                'files' => [
                    'type'        => 'array',
                    'description' => 'Array of files with adjacency signals and group names. Files should be analyzed in sequential order.',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'page_number'                => [
                                'type'        => 'integer',
                                'description' => 'Page number of the file in the original document',
                            ],
                            'belongs_to_previous'        => [
                                'type'        => ['integer', 'null'],
                                'minimum'     => 0,
                                'maximum'     => 5,
                                'description' => 'How confident are you this file belongs with the previous file? 0=definitely not (new group), 5=definitely yes (same group). Use null for the first page in the window (no previous file visible).',
                            ],
                            'belongs_to_previous_reason' => [
                                'type'        => ['string', 'null'],
                                'description' => 'Explanation for the belongs_to_previous score. Use null for the first page in the window.',
                            ],
                            'group_name'                 => [
                                'type'        => 'string',
                                'description' => $nameDescription,
                            ],
                            'group_name_confidence'      => [
                                'type'        => 'integer',
                                'minimum'     => 0,
                                'maximum'     => 5,
                                'description' => $confidenceDescription,
                            ],
                            'group_explanation'          => [
                                'type'        => 'string',
                                'description' => 'Explanation for the group name and why this file belongs to this group',
                            ],
                        ],
                        'required'             => ['page_number', 'belongs_to_previous', 'belongs_to_previous_reason', 'group_name', 'group_name_confidence', 'group_explanation'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required'             => ['files'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Build JSON schema for duplicate group resolution responses.
     * Reviews ALL groups for deduplication and spelling correction.
     *
     * @return array JSON schema definition
     */
    protected function buildDuplicateGroupResolutionSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'group_decisions' => [
                    'type'        => 'array',
                    'description' => 'Array of decisions for all groups, including any merges or corrections needed',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'original_names' => [
                                'type'        => 'array',
                                'description' => 'Array of one or more original group names that should be unified under the canonical name. Single name if no merge needed.',
                                'items'       => [
                                    'type' => 'string',
                                ],
                                'minItems'    => 1,
                            ],
                            'canonical_name' => [
                                'type'        => 'string',
                                'description' => 'The correct, canonical name to use for this group. Should be the best-spelled, most complete version.',
                            ],
                            'reason'         => [
                                'type'        => 'string',
                                'description' => 'Explanation of any changes made. If no changes: "No changes needed". If merged/corrected: explain why.',
                            ],
                        ],
                        'required'   => ['original_names', 'canonical_name', 'reason'],
                    ],
                ],
            ],
            'required'   => ['group_decisions'],
        ];
    }
}
