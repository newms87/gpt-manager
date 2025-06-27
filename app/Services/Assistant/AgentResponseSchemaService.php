<?php

namespace App\Services\Assistant;

class AgentResponseSchemaService
{
    /**
     * Get the JSON schema for agent responses that allows requesting context and actions
     */
    public function getResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message to display to the user'
                ],
                'request_context_for' => [
                    'type' => 'array',
                    'description' => 'Additional context resources to request for this conversation',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'resource_type' => [
                                'type' => 'string',
                                'description' => 'The type of resource to request context for (e.g., SchemaDefinitionResource, AgentResource)'
                            ],
                            'resource_id' => [
                                'type' => ['string', 'integer'],
                                'description' => 'The ID of the resource to request context for'
                            ]
                        ],
                        'required' => ['resource_type', 'resource_id']
                    ]
                ],
                'action' => [
                    'type' => 'string',
                    'description' => 'The name of an action to execute (if any)',
                    'enum' => [
                        'create_schema',
                        'modify_schema', 
                        'validate_schema',
                        'generate_example',
                        'optimize_workflow',
                        'create_agent',
                        'update_agent_config',
                        'run_task',
                        'analyze_data'
                    ]
                ]
            ],
            'required' => ['message'],
            'additionalProperties' => false
        ];
    }

    /**
     * Get the resource type to model class mapping
     */
    public function getResourceTypeMapping(): array
    {
        return [
            'SchemaDefinitionResource' => \App\Models\Schema\SchemaDefinition::class,
            'WorkflowDefinitionResource' => \App\Models\Workflow\WorkflowDefinition::class,
            'AgentResource' => \App\Models\Agent\Agent::class,
            'TaskDefinitionResource' => \App\Models\Task\TaskDefinition::class,
            'TeamObjectResource' => \App\Models\TeamObject\TeamObject::class,
        ];
    }

    /**
     * Load and enhance context resources with name and description
     */
    public function enhanceContextResources(array $resources): array
    {
        $enhanced = [];
        $mapping = $this->getResourceTypeMapping();

        foreach ($resources as $resource) {
            $resourceType = $resource['resource_type'] ?? null;
            $resourceId = $resource['resource_id'] ?? null;

            if (!$resourceType || !$resourceId) {
                continue;
            }

            $modelClass = $mapping[$resourceType] ?? null;
            if (!$modelClass || !class_exists($modelClass)) {
                continue;
            }

            try {
                $model = $modelClass::where('team_id', team()->id)->find($resourceId);
                if ($model) {
                    $enhanced[] = [
                        'resource_type' => $resourceType,
                        'resource_id' => $resourceId,
                        'name' => $model->name ?? 'Unnamed',
                        'description' => $model->description ?? null,
                        'data' => $this->getContextualData($model, $resourceType)
                    ];
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to load context resource: {$resourceType}:{$resourceId}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $enhanced;
    }

    /**
     * Get contextual data for a model based on its type
     */
    private function getContextualData($model, string $resourceType): array
    {
        switch ($resourceType) {
            case 'SchemaDefinitionResource':
                return [
                    'schema' => $model->schema ?? null,
                    'version' => $model->version ?? null,
                    'is_active' => $model->is_active ?? false,
                ];

            case 'AgentResource':
                return [
                    'model' => $model->model ?? null,
                    'temperature' => $model->temperature ?? null,
                    'api' => $model->api ?? null,
                ];

            case 'WorkflowDefinitionResource':
                return [
                    'status' => $model->status ?? null,
                    'max_workers' => $model->max_workers ?? null,
                ];

            default:
                return [];
        }
    }
}