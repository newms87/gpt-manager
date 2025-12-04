<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use App\Repositories\WorkflowNodeRepository;
use App\Resources\Workflow\WorkflowNodeResource;
use App\Services\Workflow\WorkflowNodeClipboardExportService;
use App\Services\Workflow\WorkflowNodeClipboardImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowNodesController extends ActionController
{
    public static ?string $repo     = WorkflowNodeRepository::class;

    public static ?string $resource = WorkflowNodeResource::class;

    /**
     * Export workflow nodes to clipboard format
     */
    public function clipboardExport(Request $request): JsonResponse
    {
        $nodeIds = $request->validate([
            'node_ids'   => 'required|array|min:1',
            'node_ids.*' => 'integer',
        ])['node_ids'];

        $nodes = WorkflowNode::whereIn('id', $nodeIds)
            ->with([
                'taskDefinition.agent',
                'taskDefinition.schemaDefinition',
                'taskDefinition.taskDefinitionDirectives.directive',
                'taskDefinition.schemaAssociations.schemaDefinition',
                'taskDefinition.schemaAssociations.schemaFragment',
                'connectionsAsSource',
            ])
            ->get();

        if ($nodes->isEmpty()) {
            throw new ValidationError('No nodes found with provided IDs');
        }

        // Verify all nodes belong to same workflow
        $workflowIds = $nodes->pluck('workflow_definition_id')->unique();
        if ($workflowIds->count() !== 1) {
            throw new ValidationError('All nodes must belong to the same workflow');
        }

        // Verify user has access to the workflow
        $workflow = WorkflowDefinition::find($workflowIds->first());
        if (!$workflow || $workflow->team_id !== team()->id) {
            throw new ValidationError('You do not have access to this workflow');
        }

        $service = new WorkflowNodeClipboardExportService();

        return response()->json($service->exportNodes($nodes->all()));
    }

    /**
     * Import workflow nodes from clipboard format
     */
    public function clipboardImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_definition_id' => 'required|integer',
            'clipboard_data'         => 'required|array',
            'paste_position'         => 'required|array',
            'paste_position.x'       => 'required|numeric',
            'paste_position.y'       => 'required|numeric',
        ]);

        $workflow = WorkflowDefinition::find($validated['workflow_definition_id']);
        if (!$workflow) {
            throw new ValidationError('Workflow not found');
        }

        if ($workflow->team_id !== team()->id) {
            throw new ValidationError('You do not have permission to edit this workflow');
        }

        $service      = new WorkflowNodeClipboardImportService();
        $createdNodes = $service->importNodes(
            $workflow,
            $validated['clipboard_data'],
            $validated['paste_position']
        );

        // Load relationships for the response
        $nodeIds = collect($createdNodes)->pluck('id');
        $nodes   = WorkflowNode::whereIn('id', $nodeIds)
            ->with(['taskDefinition'])
            ->get();

        return response()->json([
            'success' => true,
            'nodes'   => $nodes,
        ]);
    }
}
