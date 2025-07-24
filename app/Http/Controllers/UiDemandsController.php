<?php

namespace App\Http\Controllers;

use App\Models\UiDemand;
use App\Repositories\UiDemandRepository;
use App\Resources\UiDemandResource;
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

    public function runWorkflow(UiDemand $uiDemand)
    {
        try {
            // TODO: Integrate with workflow system
            // Find "Write Demand" workflow and execute it with the demand's files
            
            $uiDemand->update(['status' => UiDemand::STATUS_PROCESSING]);
            
            // Simulate workflow execution for now
            // In real implementation, this would trigger the actual workflow
            
            return UiDemandResource::make($uiDemand->fresh(['storedFiles']));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Write Demand workflow not found or failed to start.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}