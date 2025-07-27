<?php

namespace App\Http\Controllers;

use App\Models\UiDemand;
use App\Repositories\UiDemandRepository;
use App\Resources\UiDemandResource;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Http\Request;
use Newms87\Danx\Http\Controllers\ActionController;

class UiDemandsController extends ActionController
{
    public static ?string $repo = UiDemandRepository::class;
    public static ?string $resource = UiDemandResource::class;

    public function submit(UiDemand $uiDemand)
    {
        if (!$uiDemand->canBeSubmitted()) {
            return response()->json([
                'message' => 'Demand cannot be submitted. Please ensure it has files attached.',
            ], 400);
        }

        $uiDemand->submit();

        // TODO: Trigger "Write Demand" workflow here
        // For now, we'll just return success
        
        return UiDemandResource::make($uiDemand->fresh(['storedFiles']));
    }

    public function extractData(UiDemand $uiDemand)
    {
        try {
            $workflowRun = app(UiDemandWorkflowService::class)->extractData($uiDemand);
            
            return UiDemandResource::make($uiDemand->fresh(['storedFiles', 'teamObject', 'workflowRun']));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start extract data workflow.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function writeDemand(UiDemand $uiDemand)
    {
        try {
            $workflowRun = app(UiDemandWorkflowService::class)->writeDemand($uiDemand);
            
            return UiDemandResource::make($uiDemand->fresh(['storedFiles', 'teamObject', 'workflowRun']));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start write demand workflow.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}