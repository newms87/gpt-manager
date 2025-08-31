<?php

namespace App\Http\Controllers;

use App\Models\UiDemand;
use App\Repositories\UiDemandRepository;
use App\Resources\UiDemandResource;
use App\Services\UiDemand\UiDemandWorkflowService;
use Newms87\Danx\Http\Controllers\ActionController;

class UiDemandsController extends ActionController
{
    public static ?string $repo     = UiDemandRepository::class;
    public static ?string $resource = UiDemandResource::class;


    public function extractData(UiDemand $uiDemand)
    {
        try {
            app(UiDemandWorkflowService::class)->extractData($uiDemand);

            return UiDemandResource::make($uiDemand->fresh([
                'inputFiles',
                'outputFiles',
                'teamObject',
                'workflowRuns.workflowDefinition.workflowNodes',
                'workflowRuns.taskRuns',
            ]), [
                'team_object'               => true,
                'input_files'               => true,
                'output_files'              => true,
                'extract_data_workflow_run' => true,
                'write_demand_workflow_run' => true,
            ]);
        } catch(\Exception $e) {
            return response()->json([
                'message' => 'Failed to start extract data workflow.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    public function writeDemand(UiDemand $uiDemand)
    {
        try {
            $templateId             = request()->input('template_id');
            $additionalInstructions = request()->input('additional_instructions');

            $workflowRun = app(UiDemandWorkflowService::class)->writeDemand($uiDemand, $templateId, $additionalInstructions);

            return UiDemandResource::make($uiDemand->fresh([
                'inputFiles',
                'outputFiles',
                'teamObject',
                'workflowRuns.workflowDefinition.workflowNodes',
                'workflowRuns.taskRuns',
            ]), [
                'team_object'               => true,
                'input_files'               => true,
                'output_files'              => true,
                'extract_data_workflow_run' => true,
                'write_demand_workflow_run' => true,
            ]);
        } catch(\Exception $e) {
            return response()->json([
                'message' => 'Failed to start write demand workflow.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

}
