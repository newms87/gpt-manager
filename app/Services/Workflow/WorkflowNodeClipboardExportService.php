<?php

namespace App\Services\Workflow;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\Workflow\WorkflowNode;

class WorkflowNodeClipboardExportService implements WorkflowExportServiceInterface
{
    protected array $definitions = [];

    protected array $nodeExportKeys = []; // Maps node ID -> export key (e.g., "node_0")

    /**
     * Export multiple workflow nodes for clipboard copy.
     *
     * @param  WorkflowNode[]  $workflowNodes
     */
    public function exportNodes(array $workflowNodes): array
    {
        $nodes = [];

        // Export each node and generate export keys
        foreach ($workflowNodes as $index => $workflowNode) {
            $exportKey                                  = 'node_' . $index;
            $this->nodeExportKeys[$workflowNode->id]    = $exportKey;

            $nodes[] = [
                'export_key'          => $exportKey,
                'name'                => $workflowNode->name,
                'settings'            => $workflowNode->settings,
                'params'              => $workflowNode->params,
                'task_definition_ref' => $this->registerRelatedModel($workflowNode->taskDefinition),
            ];

            // Register the TaskDefinition and all its dependencies
            if ($workflowNode->taskDefinition) {
                $workflowNode->taskDefinition->exportToJson($this);
            }
        }

        // Export connections between selected nodes
        $connections = $this->exportInterConnections($workflowNodes);

        return [
            'type'        => 'workflow-node-clipboard',
            'version'     => '1.0',
            'nodes'       => $nodes,
            'connections' => $connections,
            'definitions' => $this->definitions,
        ];
    }

    /**
     * Export connections where both source and target nodes are in the selected set.
     *
     * @param  WorkflowNode[]  $workflowNodes
     */
    protected function exportInterConnections(array $workflowNodes): array
    {
        $nodeIds     = collect($workflowNodes)->pluck('id')->toArray();
        $connections = [];

        foreach ($workflowNodes as $node) {
            foreach ($node->connectionsAsSource as $connection) {
                if (in_array($connection->target_node_id, $nodeIds)) {
                    $connections[] = [
                        'source_export_key'  => $this->nodeExportKeys[$connection->source_node_id],
                        'target_export_key'  => $this->nodeExportKeys[$connection->target_node_id],
                        'name'               => $connection->name,
                        'source_output_port' => $connection->source_output_port,
                        'target_input_port'  => $connection->target_input_port,
                    ];
                }
            }
        }

        return $connections;
    }

    /**
     * Register a model's exported data into definitions.
     */
    public function register(ResourcePackageableContract $model, array $data): int
    {
        $this->definitions[$model::class][$model->id] = $data;

        return $model->id;
    }

    /**
     * Register a related model and return reference string.
     */
    public function registerRelatedModel(?ResourcePackageableContract $model = null): ?string
    {
        if (!$model) {
            return null;
        }

        if (empty($this->definitions[$model::class][$model->id])) {
            $this->definitions[$model::class][$model->id] = true;
            $model->exportToJson($this);
        }

        return $model::class . ':' . $model->id;
    }

    /**
     * Register related models so they are exported.
     * NOTE: These models should associate themselves in their exportToJson() method to the model that has called this
     * method.
     *
     * @param  ResourcePackageableContract[]  $models
     */
    public function registerRelatedModels($models): void
    {
        if (!$models) {
            return;
        }

        foreach ($models as $model) {
            if (empty($this->definitions[$model::class][$model->id])) {
                $model->exportToJson($this);
            }
        }
    }
}
