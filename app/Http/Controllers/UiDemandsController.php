<?php

namespace App\Http\Controllers;

use App\Models\Demand\UiDemand;
use App\Repositories\UiDemandRepository;
use App\Resources\UiDemandResource;
use App\Services\UiDemand\UiDemandWorkflowConfigService;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Http\JsonResponse;
use Newms87\Danx\Http\Controllers\ActionController;

class UiDemandsController extends ActionController
{
    public static ?string $repo     = UiDemandRepository::class;

    public static ?string $resource = UiDemandResource::class;

    /**
     * Generic workflow execution endpoint
     */
    public function runWorkflow(UiDemand $uiDemand, string $workflowKey)
    {
        try {
            $params = request()->only(['output_template_id', 'instruction_template_id', 'additional_instructions']);

            app(UiDemandWorkflowService::class)->runWorkflow($uiDemand, $workflowKey, $params);

            return UiDemandResource::details($uiDemand);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Failed to start workflow '{$workflowKey}'.",
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get workflow configuration for UI
     */
    public function workflowConfig(): JsonResponse
    {
        $config = app(UiDemandWorkflowConfigService::class);

        return response()->json([
            'workflows'         => $config->getWorkflowsForApi(),
            'schema_definition' => $config->getSchemaDefinition(),
        ]);
    }
}
